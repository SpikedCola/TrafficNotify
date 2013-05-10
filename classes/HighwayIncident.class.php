<?php
	require_once(__DIR__ . '/Incident.class.php');
	
	class HighwayIncident extends Incident {
		function __construct($data = null) {
			parent::__construct($data);
		}
		
		protected function load($row) {
			$this->data = (array)$row;
			
			// split off highway
			$q = new Query();
			$q->addTable('highways');
			$q->addWhere('highway', $this->highway);
			$q->addColumn('highway_id');
			if (!($this->highway_id = $this->db->getOne($q))) {
				$q->addField('highway', $this->highway);
				$this->highway_id = $this->db->insert($q);
			}
			
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
		
		public function save() {
			if (isset($this->data['highway'])) {
				unset($this->data['highway']);
			}
			if (isset($this->data['direction'])) {
				unset($this->data['direction']);
			}
			
			$q = new Query();
			$q->addTable('highway_incidents');
			$data = $this->data;
			// add key to field names ('to' breaks)
			foreach ($data as $key => $value) {
				$data['highway_incidents.'.$key] = $value;
				unset($data[$key]);
			}
			$q->addFields($data);
			$this->data['id'] = $this->db->insert($q);
		}
		
		public function parse($rowNodes, $xpath) {
			foreach ($rowNodes as $key => $row) {
				$cellNodes = $xpath->query('td', $row);
				if ($cellNodes->length == 2) {
					$value = trim($cellNodes->item(1)->nodeValue);
				}
				else if (!empty($row->nodeValue)) {
					$value = trim($row->nodeValue); // for summary row (no td's)
				}
				else {
					throw new Exception('Highway Table row didnt have 2 cells (had ' . $cellNodes->length . ')');
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
						$field = 'location';
						$cap = true;
						break;
					case 3:
						$field = 'traffic_impact';
						$cap = true;
						break;
					case 4:
						$field = 'description';
						$cap = true;
						break;
					case 5:
						$field = 'detour';
						$cap = true;
						break;
					case 6:
						$field = 'event_start';
						if (!empty($value)) {
							$value = strtotime($value);
						}
						break;
					case 7:
						$field = 'event_end';
						if (!empty($value)) {
							$value = strtotime($value);
						}
						break;
					case 8: 
						$field = 'last_updated';
						break;
					case 9: 
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
						throw new Exception('Got to parseHighway with more than 10 nodes...');
				}
				if ($cap) {
					$value = $this->cap($value);
				}
				$this->$field = $value;
			}
		}
	}
?>
