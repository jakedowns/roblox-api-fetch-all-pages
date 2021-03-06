<?php

require 'UserInventoryWorker.php';

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise\Promise;

class RobloxAPIService
{
	private $url_user_asset_collectibles = 'https://inventory.roblox.com/v1/users/USER_ID/assets/collectibles';
	private $assetTypes = array("Hat", "Face", "Gear", "HairAccessory", "FaceAccessory", "NeckAccessory", "ShoulderAccessory", "FrontAccessory", "BackAccessory", "WaistAccessory");

	private $curlMulti;
	private $client;

	public function __construct(){
		$this->curlMulti = new CurlMultiHandler();
		$this->client = $this->_make_client();
	}
	
	/**
	*
	*	This method calculates a total recent average price
	*	for a given user by looping over the RAP value of 
	*	each collectible asset a user owns.
	*
	* 	For each available asset type,
	*	this script polls the Roblox Web API
	*	and continues to poll for each asset type for as many pages
	*	as necessary by checking for the existence of 
	*	the next_page_cursor property
	*
	**/
	public function get_total_rap_for_user($user_id)
	{
		$this->user_id = $user_id;
		$limiteds = $this->get_all_limiteds_concurrent();

		$total_pages = 0;
		$total_rap = 0;
		$total_rap_avg = 0;
		$total_limiteds = 0;

		foreach($limiteds as $assetType => $assetsForType){
			$total_pages += $assetsForType->pages;
			foreach($assetsForType->items as $asset){
				$total_limiteds++;
				$total_rap += floatval($asset->recentAveragePrice);
			}
		}

		if($total_limiteds){
			$total_rap_avg = $total_rap / $total_limiteds;
		}

		return (object) [
			'total_pages' => $total_pages,
			'total_limiteds' => $total_limiteds,
			'total_rap' => $total_rap,
			'total_rap_avg' => $total_rap_avg,
			'limiteds_by_asset_type' => $limiteds
		];
	}

	private function get_all_limiteds_concurrent()
	{
		$promises = $this->getAssetTypePromises();
		$results = $this->waitForPromiseGroup($promises, function($results, $single_result){
			if (isset($single_result['value'])) {
				$data = [];
				if(isset($single_result['value']->data)){
					$data = $single_result['value']->data;
				}
				$results->{$single_result['value']->assetType} = (object)[
					'total_items' => count($data),
					'pages' => $single_result['value']->pages,
					'items'       => $data
				];
			}
			return $results;
		});

		return $results;
	}

	// Shared Singleton Client
	// Necessary for stepped-async parallelism 
	private function _make_client(){
		$curlMulti = $this->curlMulti;
		$stack = HandlerStack::create($curlMulti);
		$opts = [
			'handler' => $stack,
			'verify' => false,
			'base_uri' => '',
			'allow_redirects' => true,
			'timeout' => 0,
			'http_errors' => false,
			'headers' => [
			  'Cache-Control' => 'no-cache'
			]
		];

		$client = new GuzzleHttp\Client($opts);
		return $client;
	}

	private function getAssetTypePromises()
	{
		$promises = [];
		foreach ($this->assetTypes as $assetType) {
			$reqParams = (object) [
				'userId' => $this->user_id,
				'assetType' => $assetType
			];

			$finalOuterPromise = new Promise();
			$ignorablePromise = $this->getUserAssetCollectiblesByAssetTypePromisified($this->client, $reqParams, $finalOuterPromise);
			$promises[] = $finalOuterPromise;
		}
		return $promises;
	}

	private function getUserAssetCollectiblesByAssetTypePromisified($client, $req, $finalOuterPromise){
		$req_url_base = str_replace('USER_ID', $req->userId, $this->url_user_asset_collectibles);
		$query_obj = [
			'assetType' => $req->assetType,
			'sortOrder' => isset($req->sortOrder) ? $req->sortOrder : 'Asc',
			'limit' => isset($req->limit) ? $req->limit : '100',
			'cursor' => isset($req->cursor) ? $req->cursor : ''
		];

		return $this->getAllResultPagesPromise(
			$req_url_base,
			$query_obj,
			'Roblox_Collectibles',
			function($result_decoded){
				// get_next_page_cursor
				//dd(['get_next_page_cursor', $result_decoded]);
				if(isset($result_decoded->nextPageCursor)){
					return $result_decoded->nextPageCursor;
				}

				return null;
			},
			function($result_decoded){
				// fn_get_nested_data
				if(isset($result_decoded->data)){
					return $result_decoded->data;
				}

				return [];
			},
			$client,
			$finalOuterPromise
		);
	}

	public function getAllResultPagesPromise(
		$req_url_base,
		$query_obj,
		$type,
		$fn_get_next_page_cursor,
		$fn_get_nested_data,
		$client,
		$finalOuterPromise
	){
		return (new UserInventoryWorker())->getAsPromise(
			$req_url_base,
			$query_obj,
			$type,
			$fn_get_next_page_cursor,
			$fn_get_nested_data,
			$client,
			$finalOuterPromise
		);
	}

	public function waitForPromiseGroup($promises, $foldResults_fn) {
	  $this->settled = false;
	  $results = (object) [];

	  $group = \GuzzleHttp\Promise\settle($promises)->then(function ($promise_results) use (&$results, $foldResults_fn) {
		$this->settled = true;
		try {
		  foreach ($promise_results as $result) {
			$results = call_user_func($foldResults_fn, $results, $result);
		  }
		}catch(\Exception $e){
		  //dd($e);
		}
	  });

	  // Debugging loop, ticks in background
	  // if Promises don't resolve within MAX_TICKS, we exit early
	  define('MAX_TICKS_BEFORE_SCRIPT_BREAK', 5000);
	  $ticks = 0;
	  $broken = false;
	  while ($group->getState() === 'pending' && ! $broken) {
		$this->curlMulti->tick();
		//\GuzzleHttp\Promise\queue()->run();

		// just in case, limit to N ticks...
		$ticks++;
		if($ticks >= MAX_TICKS_BEFORE_SCRIPT_BREAK
		  // or empty queue detected
		  // || \GuzzleHttp\Promise\queue()->isEmpty()
		){
		  print("<br/>script broke");
		  $broken = true;
		  break;
		}
		usleep(1);
	  }

	  // DEBUG Helper
	  // print("<br/>waitForPromiseGroup ran for $ticks ticks");
	  // foreach($promises as $promise){
	  // 	print("<br>" . $promise->getState());
	  // }

	  return $results;
	}
}