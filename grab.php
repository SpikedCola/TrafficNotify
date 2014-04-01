<?php
	require_once(__DIR__ . '/classes/HighwayIncident.class.php');	
	require_once(__DIR__ . '/classes/FreewayIncident.class.php');	
	
	// get url, parse out fields, return json. easy!

	$url = 'http://www.mto.gov.on.ca/english/traveller/trip/road_closures.shtml';
	//$response = file_get_contents($url);
	
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36'
	));
	$response = curl_exec($ch);
	curl_close($ch);
	
	$json = new stdClass();
	
	if (!empty($response)) {
		libxml_use_internal_errors(true); // supress warnings

		$dom = new DOMDocument();
		$dom->loadHTML($response);
		$xpath = new DOMXPath($dom);
		
		try {
			$contentNode = $xpath->query('//div[@class="middleBoxContent"]');
			if ($contentNode->length > 0) {
				$tableNodes = $xpath->query('table', $contentNode->item(0));
				if ($tableNodes->length > 0) {
					foreach ($tableNodes as $table) {
						$rowNodes = $xpath->query('tr', $table);
						if ($rowNodes->length > 0) {
							// table can either have 9 or 11 rows
							if ($rowNodes->length == 9) {
								$highway = new HighwayIncident();
								$highway->parse($rowNodes, $xpath);
								$json->results->highways[] = (object)$highway->data;
							}
							elseif ($rowNodes->length == 12) {
								$freeway = new FreewayIncident();
								$freeway->parse($rowNodes, $xpath);
								$json->results->freeways[] = (object)$freeway->data;
							}
							else {
								throw new Exception('div.middleBoxContent > table does not have 9 or 11 rows (has ' . $rowNodes->length . '): ' . $table->nodeValue);
							}
						}
						else {
							throw new Exception('Failed to find any div.middleBoxContent > table rows');
						}
					}
				}
				else {
					throw new Exception('Failed to find any tables in div.middleBoxContent (this could be okay if there are no closures)');
				}
			}
			else {
				throw new Exception('Failed to find div.middleBoxContent');
			}
			
			$json->success = true;
		}
		catch (Exception $ex) {
			$json = new stdClass();
			$json->error = 'An error occured while parsing the MTO Road Closures website: ' . $ex->getMessage();
		}
	}
	else {
		$json->error = 'Failed to load MTO Road Closures website. Please try again later.';
	}
	
	echo json_encode($json);