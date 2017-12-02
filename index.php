<?php

require 'vendor/autoload.php';
require 'RobloxAPIService.php';

function original_example() {
	$userid = htmlentities($_GET['userid'], ENT_QUOTES);

	$assettypes = array("Hat", "Face", "Gear", "HairAccessory", "FaceAccessory", "NeckAccessory", "ShoulderAccessory", "FrontAccessory", "BackAccessory", "WaistAccessory");

	$rap2 = 0;

	$exec_time_start = microtime(true);
	$items_total = 0;
	$pages_total = 0;
	foreach($assettypes as $assettype){
	    $url = "https://inventory.roblox.com/v1/users/" . $userid . "/assets/collectibles?assetType=" . $assettype . "&sortOrder=Asc&limit=100&cursor=";
	    $get = file_get_contents($url);
	    $json = json_decode($get);
	    $rap = 0;

	    //print_r($json);

	    foreach($json->data as $val){
	        $rap += $val->recentAveragePrice;
	        $items_total += 1;
	    }

	    $rap2 += $rap;
	    $pages_total += 1;
	}
	echo json_encode([
		'total_rap' => $rap2,
		'items' => $items_total,
		'pages' => $pages_total,
		'rendertime' => (microtime(true) - $exec_time_start)
	]);
}

function basic_solution() {
	function get_page($userid, $assettype, $page, $cursor){
		$url = "https://inventory.roblox.com/v1/users/" . $userid . "/assets/collectibles?assetType=" . $assettype . "&sortOrder=Asc&limit=100&cursor=" . $cursor . "&page=" . $page;
	    $get = file_get_contents($url);
	    $json = json_decode($get);
	    $rap = 0;
	    $items = 0;

	    foreach($json->data as $val){
	        $rap += $val->recentAveragePrice;
	        $items += 1;
	    }

	    return [$rap, $items, $json->nextPageCursor];
	}

	function get_all_pages_for_asset_type($userid, $assettype){
		$keep_fetching = true;
		$cursor = '';
		$page = 1;
		$rap = 0;
		$items = 0;

		while($keep_fetching){
			$results = get_page($userid, $assettype, $page, $cursor);
			$rap += $results[0];
			$items += $results[1];
			if(!$results[2] || empty(trim($results[2]))){
				// nextPageCursor empty, break
				$keep_fetching = false;
				break;
			}else{
				$page+=1;
				$cursor = $results[2];
				error_log($cursor);
			}
		}

		return [$page, $items, $rap];
	}

	$userid = htmlentities($_GET['userid'], ENT_QUOTES);

	$assettypes = array("Hat", "Face", "Gear", "HairAccessory", "FaceAccessory", "NeckAccessory", "ShoulderAccessory", "FrontAccessory", "BackAccessory", "WaistAccessory");

	$rap2 = 0;

	$exec_time_start = microtime(true);
	$items_total = 0;
	$pages_total = 0;

	foreach($assettypes as $assettype){
	    $results = get_all_pages_for_asset_type($userid, $assettype);
	    $pages_total += $results[0];
	    $items_total += $results[1];
	    $rap2 += $results[2];
	}
	echo json_encode([
		'total_rap' => $rap2,
		'items' => $items_total,
		'pages' => $pages_total,
		'rendertime' => (microtime(true) - $exec_time_start)
	]);
}

function advanced_solution(){
	$exec_time_start = microtime(true);

	$userid = htmlentities($_GET['userid'], ENT_QUOTES);
	$results = (new RobloxAPIService())->get_total_rap_for_user($userid);

	echo json_encode([
		'results' => $results,
		'rendertime' => microtime(true) - $exec_time_start
	]);
}

// ~4 seconds for 10 pages of 607 items (incomplete)
// original_example();

// ~15 seconds for 31 pages of 2483
//basic_solution();

// ~4 seconds for 31 pages of 2483 items
advanced_solution();

?>