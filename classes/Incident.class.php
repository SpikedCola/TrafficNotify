<?php
	require_once(__DIR__ . '/../Skoba.php');
	require_once('Twilio/Twilio.php');
	require_once('Twitter/twitteroauth.php');
	
	class Incident {
		/**
		 * An array of 'from' => 'to' replacements
		 * for the cap() function
		 * 
		 * @see cap()
		 * @var <array> $replacements  
		 */
		protected $replacements = array(
		    'Hov' => 'HOV',
		    'Qew' => 'QEW',
		    '407 Etr' => '407ETR',
		    'Collector(s)' => 'Collectors'
		);
		
		/**
		 * Only send SMS's between these times
		 * (tweets will still be sent regardless)
		 */
		protected $sendBetween = array(
		    '7:30' => '8:30',
		    '16:30' => '17:45'
		);
		
		public $data = array();
		
		protected $original_data = array();
		
		protected $db = null;
		
		protected $twilio = null;
		
		protected $twitter = null;
		
		protected $message = null;
		
		protected $changed_message = null;
	
		function __construct($data = null) {
			$this->db = new Db();
			$this->twilio = new Services_Twilio("", "");
			$this->twitter = new TwitterOAuth('', '', '', '');
			if ($data) {
				$this->load($data); 
			}
		}
		
		function __get($name) {
			if (array_key_exists($name, $this->data)) {
				return $this->data[$name];
			}
			else if (strpos($name, 'original_') !== false) {
				$name = str_replace('original_', '', $name);
				if (array_key_exists($name, $this->original_data)) {
					return $this->original_data[$name];
				}
			}
			return null;
		}
		
		function __set($name, $value) {
			$this->data[$name] = $value;
		}
		
		/**
		 * Attempts to properly capitalize words, including support
		 * for delimiters. Also supports replacements, for things like 'QEW'.
		 * 
		 * @see $this->replacements
		 * @param <string> $text The text to capitalize
		 * @return <string> The properly capitalized text
		 */
		protected function cap($text) {
			$string = ucwords(strtolower($text));
			foreach (array('-', '/') as $delimiter) {
				if (strpos($string, $delimiter) !== false) {
					$string = implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
				}
			}
			return str_replace(array_keys($this->replacements), array_values($this->replacements), $string);
		}
		
		/**
		 * Sends a message saying an incident has been entered
		 */
		public function send() {
			$this->buildMessage();
			$this->sms();
			$this->tweet();
		}
		
		/**
		 * Sends a message saying an incident has been updated/cleared
		 */
		public function sendChanged() {
			$this->buildMessage();
			var_dump($this->message);
			//$this->sms();
			//$this->tweet();
		}
		
		/**
		 * Sends a message via SMS. Automatically breaks
		 * long messages into multiple messages.
		 */
		private function sms() {
			// trial header: 'Sent from your Twilio trial account - '
			// (160-38) = 122 characters max
			
			foreach ($this->sendBetween as $from => $to) {
				if (time() >= strtotime('today '.$from) && time() <= strtotime('today '.$to)) {
					$messages = explode("\n", wordwrap($this->message, 122));
					foreach ($messages as $m) {
						$this->twilio->account->sms_messages->create("+16479311589", "+16479841333", $m);
					}
					break;
				}
			}
		}
		
		/**
		 * Tweets a message. Automatically breaks
		 * long messages into multiple tweets.
		 */
		private function tweet() {
			// a tweet is max 140 chars
			$messages = explode("\n", wordwrap($this->message, 140));
			foreach ($messages as $m) {
				$this->twitter->post('statuses/update', array('status' => $m));
			}
		}
	}
?>
