<?php
	require_once('Skoba.php');
	require_once('classes/HighwayIncident.class.php');
	require_once('classes/FreewayIncident.class.php');
	
	// get data
	$url = 'http://127.0.0.1/mto/grab.php';
	$data = file_get_contents($url);
	
	if (!empty($data)) {
		$json = json_decode($data);
		$results = array();
		if (isset($json->results)) {
			if (isset($json->results->highways)) {
				foreach ($json->results->highways as $highway) {
					//$results[] = new HighwayIncident($highway);
				}
			}
			if (isset($json->results->freeways)) {
				foreach ($json->results->freeways as $freeway) {
					$results[] = new FreewayIncident($freeway);
				}
			}
		}
		
		if (count($results) > 0) {
			foreach ($results as $result) {
				if ($result->exists()) {
					if ($result->changed()) {
						echo 'exists & changed, update & alert' . PHP_EOL;
						//$sms = $result->getChangedSms();
						continue;
						$result->sendChanged(); // @todo: build sendChanged
						$result->save();
					}
					else {
						echo 'exists & same, do nothing' . PHP_EOL;
					}
				}
				else {
					$result->save();
					echo 'saved new incident' . PHP_EOL;
					$result->send();
				}
			}
		}
	}
?>
