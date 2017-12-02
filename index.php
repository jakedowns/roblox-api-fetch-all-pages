<?php

require 'vendor/autoload.php';
require 'RobloxAPIService.php';

function original_example() {
	$userid = htmlentities($_GET['userid'], ENT_QUOTES);

	$assettypes = array("Hat", "Face", "Gear", "HairAccessory", "FaceAccessory", "NeckAccessory", "ShoulderAccessory", "FrontAccessory", "BackAccessory", "WaistAccessory");

	$rap2 = 0;

	$exec_time_start = microtime();
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
		'rendertime' => (microtime() - $exec_time_start)
	]);
}

function parallel_example(){
	$exec_time_start = microtime();

	$userid = htmlentities($_GET['userid'], ENT_QUOTES);
	$results = (new RobloxAPIService())->get_total_rap_for_user($userid);

	die(var_export(['parallel_example',$results]));

	echo json_encode([
		'total_rap' => $results->total_rap,
		'items' => $results->items_total,
		'pages' => $results->pages_total,
		'rendertime' => (microtime() - $exec_time_start)
	]);
}

//original_example();
parallel_example();

?>