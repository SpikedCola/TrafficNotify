<?php
	require_once(__DIR__ . '/Incident.class.php');
	
	class FreewayIncident extends Incident {
		function __construct($data = null) {
			parent::__construct($data);
		}
		
		/**
		 * Parses the freeway incident data from the HTML table
		 * 
		 * Yay xpath
		 * 
		 * @param <DOMNode> $row The row to parse
		 * @param <DOMXPath> $xpath The global DOM's xpath object
		 * @throws <Exception> If row cant be parsed, perhaps because the structure has changed
		 */
		public function parse($row, $xpath) {	
			foreach ($row as $key => $row) {
				$cellNodes = $xpath->query('td', $row);
				if ($cellNodes->length == 2) {
					$value = trim($cellNodes->item(1)->nodeValue);
				}
				else if (!empty($row->nodeValue)) {
					$value = trim($row->nodeValue); // for summary row (no td's)
				}
				else {
					throw new Exception('Freeway Table row didnt have 2 cells (had ' . $cellNodes->length . ')');
				}
				$cap = false;
				switch ($key) {
					case 0: 
						$field = 'summary';
						break;
					case 1:
						$field = 'highway';
						break;
					case 2:
						$field = 'direction';
						$cap = true;
						break;
					case 3:
						$field = 'from_at';
						$cap = true;
						break;
					case 4:
						$field = 'to';
						$cap = true;
						break;
					case 5:
						$field = 'lanes_affected';
						$matches = array();
						$value = strtolower($value);
						preg_match('/(\d).*lane/', $value, $matches);
						// if not set, value is probably "all lanes closed" 
						if (isset($matches[1])) {
							if ($matches[1] == 1) {
								$replace = 'lane';
							}
							else {
								$replace = 'lanes';
							}
							$value = str_replace(array('lane(s)', 'lanes(s)'), $replace, $value); // yes, lanes(s) does exist...
						}
						$cap = true;
						break;
					case 6:
						$field = 'traffic_impact';
						$cap = true;
						break;
					case 7:
						$field = 'reason';
						$value = $this->cap($value); // incase capitalization changes
						if ($value == 'Disable Vehicle') {
							$value = 'Disabled Vehicle';
						}
						break;
					case 8:
						$field = 'event_start';
						if (!empty($value)) {
							$value = strtotime($value);
						}
						break;
					case 9: 
						$field = 'event_end';
						if (!empty($value)) {
							$value = strtotime($value);
						}
						break;
					case 10:
						$field = 'last_change';
						$change = explode(' ', $value);
						if (count($change) > 1) {
							$this->last_change_reason = $this->cap(array_shift($change)); // removes $change[0]
							$value = strtotime(implode(' ', $change));// join time & convert
						}
						else {
							throw new Exception('last_change only exploded into 1 part: ' . print_r($this->last_change, true));
						}
						break;
					default:
						throw new Exception('Got to parseFreeway with more than 11 nodes...');
				}
				if ($cap) {
					$value = $this->cap($value);
				}
				$this->$field = $value;
			}	

			$value = null;
			if ($this->summary) {
				$parts = explode('[', strtolower($this->summary));
				if (count($parts) == 2) {
					$location = trim(str_replace(array(strtolower($this->highway), strtolower($this->direction)), '', $parts[0]));
					// preserve null over empty string
					if (!empty($location)) {
						$value = $this->cap($location);
					}
				}
				else {
					throw new Exception('summary didnt split on [ into 2 parts? : ' . $this->summary);
				}
			}
			$this->location = $value;
		}
		
		/**
		 * Loads a JSON object from the API
		 * 
		 * @param <object> $row The JSON object to load 
		 */
		protected function load($row) {
			$this->data = (array)$row;
			
			if ($this->highway) {
				// split off highway
				$q = new Query();
				$q->addTable('highways');
				$q->addWhere('highway', $this->highway);
				$q->addColumn('highway_id');
				if (!($this->highway_id = $this->db->getOne($q))) {
					$q->addField('highway', $this->highway);
					$this->highway_id = $this->db->insert($q);
				}
			}
			
			if ($this->direction) {
				// split off direction
				$q = new Query();
				$q->addTable('directions');
				$q->addWhere('direction', $this->direction);
				$q->addColumn('direction_id');
				if (!($this->direction_id = $this->db->getOne($q))) {
					$q->addField('direction', $this->direction);
					$this->direction_id = $this->db->insert($q);
				}
			}
		}
		
		/**
		 * Saves the incident
		 */
		public function save() {
			$q = new Query();
			$q->addTable('freeway_incidents');
			$data = $this->data;
			// dont try and save these, but keep around for later
			if (isset($data['highway'])) {
				unset($data['highway']);
			}
			if (isset($data['direction'])) {
				unset($data['direction']);
			}
			// add key to field names ('to' breaks)
			foreach ($data as $key => $value) {
				$data['freeway_incidents.'.$key] = $value;
				unset($data[$key]);
			}
			$q->addFields($data);
			$this->data['id'] = $this->db->insert($q);
		}
		
		/**
		 * Checks to see if an incident already exists
		 * 
		 * @return <boolean> True if the incident already exists
		 */
		public function exists() {
			$matchFields = array(
			    'freeway_incidents.highway_id' => $this->highway_id,
			    'freeway_incidents.direction_id' => $this->direction_id,
			    'freeway_incidents.from_at' => $this->from_at,
			    'freeway_incidents.to' => $this->to,
			    'freeway_incidents.event_start' => $this->event_start
			);
			
			$q = new Query();
			$q->addTable('freeway_incidents');
			foreach ($matchFields as $field => $value) {
				$q->addWhere($field, $value);
			}
			$data = $this->db->getRow($q);
			
			if ($data) {
				$this->original_data = $data;
				return true;
			}
			
			return false;
		}
		
		/**
		 * Checks to see if an incident has changed from what we know of it
		 * 
		 * @return <boolean> True if the incident has changed from what we know of it
		 * @throws <Exception> If original_data hasnt been loaded - call exists() before changed()
		 */
		public function changed() {
			if (!$this->original_data) {
				throw new Exception('call exists() before calling changed()');
			}
			
			if ($this->last_change != $this->original_last_change ||
			    $this->last_change_reason != $this->original_last_change_reason ||
			    $this->lanes_affected != $this->original_lanes_affected ||
			    $this->traffic_impact != $this->original_traffic_impact ||
			    $this->reason != $this->original_reason) {
				return true;
			}
			
			
			return false;
		}
		
		/** 
		 * Builds a freeway-specific incident changed message
		 */
		public function buildChangedMessage() {
			var_dump(array_diff($this->data, $this->original_data));
			$message = array();
			$this->changed_message = implode(' ', $message);
		}
		
		/** 
		 * Builds a freeway-specific incident message
		 * 
		 * @throws <Exception> If there is a logic fail (like no location)
		 */
		public function buildMessage() {
			$message = array();
			
			$message[] = $this->highway;
			$message[] = $this->direction;
			
			if ($this->location) {
				$message[] = $this->location;
			}
			
			if ($this->from_at && $this->to) {
				$message[] = 'From ' . $this->from_at . ' to ' . $this->to;
			}
			else if ($this->from_at && !$this->to) {
				$message[] = 'At ' . $this->from_at;
			}
			else if (!$this->from_at && $this->to) {
				$message[] = 'To ' . $this->to;
			}
			else {
				throw new Exception('from_at and to are both empty?!' . print_r($this, true));
			}
			
			$message[] = '-';
			
			if ($this->reason == 'Other') {
				$reason = 'Incident';
			}
			else {
				$reason = $this->reason;
			}
			
			// "All Lanes Closed due to Serious Collision"
			if ($this->lanes_affected == 'All Lanes Closed') {
				$message[] = 'All Lanes Closed Due to';
				if ($this->traffic_impact) {
					$message[] = $this->traffic_impact;
				}
				$message[] = $reason;
			}
			// "Serious Collision Affecting 1 Right Lane"
			else {
				if ($this->traffic_impact) {
					$message[] = $this->traffic_impact;
				}
				$message[] = $reason;
				$message[] = 'Affecting';
				$message[] = $this->lanes_affected; 
			}
			
			$this->message = implode(' ', $message);
		}
	}
?>
