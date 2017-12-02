<?php

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

class UserInventoryWorker
{
	public function getAsPromise(
		$req_url_base,
		$query_obj,
		$type,
		$fn_get_next_page_cursor,
		$fn_get_nested_data,
		$client,
		$finalOuterPromise
	){
		$this->req_url_base = $req_url_base;
		$this->query_obj = $query_obj;
		$this->type = $type;
		$this->fn_get_next_page_cursor = $fn_get_next_page_cursor;
		$this->fn_get_nested_data = $fn_get_nested_data;
		$this->client = $client;
		$this->full_results = [];
		$this->page_number = 1;
		$this->pages_remaining = true;
		$this->pages_resolved = [];
		$this->finalOuterPromise = $finalOuterPromise;

		$this->result_promise = $this->getResultsPage(
			$this->req_url_base,
			$this->query_obj,
			$this->type,
			$this->client
		);

		$onPageFulfilled = $this->makeOnPageFulfilled();
		$this->result_promise->then($onPageFulfilled);

		return $this->result_promise;
	}

	public function getResultsPage($req_url_base, $query_obj, $type, $client){
		$query = http_build_query($query_obj);
		$req_url = $req_url_base . '?' . $query;

		if(!$client || !isset($client)){
			die('client missing');
		}

		return $client->getAsync($req_url);
	}

	public function makeOnPageFulfilled(){
		$OPF = function($response){
			$result_decoded = $this->decodeResponse($response);

			$this->full_results = array_merge($this->full_results, call_user_func($this->fn_get_nested_data,$result_decoded));

			$next_page_cursor = call_user_func($this->fn_get_next_page_cursor,$result_decoded);

			if($next_page_cursor) {
				$this->page_number++;

				// update $query_obj with new cursor for next while iteration
				$this->query_obj['cursor'] = $next_page_cursor;
				$this->query_obj['pageNumber'] = $this->page_number;

				$next_promise = $this->getResultsPage(
						$this->req_url_base,
						$this->query_obj,
						$this->type,
						$this->client
					);
				$myOnPageFulfilled = $this->makeOnPageFulfilled(); 
				$next_promise->then($myOnPageFulfilled);
				$this->result_promise->resolve($next_promise);
			}else {
				$this->pages_remaining = false;

				$resolution_object = (object) [
				  'data' => $this->full_results
				];
				if(isset($this->query_obj['assetType'])){
				  $resolution_object->{'assetType'} = $this->query_obj['assetType'];
				}
				$resolution_object->{'pages'} = $this->page_number; 
				$this->finalOuterPromise->resolve($resolution_object);
			}

			\GuzzleHttp\Promise\queue()->run();
		};
		return $OPF;
	}

	public function decodeResponse($response){
		$response_raw = (string) $response->getBody();
		$type = isset($this->type) ? $this->type : 'Unknown';

		$response_decoded = false;
		try{
			$response_decoded = json_decode($response_raw);
		}catch(\Exception $e) {
			$response_decoded = (object) [
				$type . '_Query_Error' => $e->getMessage()
			];
		}
		if(!$response_decoded){
			$response_decoded = (object) [
				$type . '_Query_Error' => 'Unknown Error: ' . $response_raw
			];
		}
		return $response_decoded;
	}
}

?>