<?php

class FacebookUploader {
    
	private $app_id;
	private $app_secret;
	private $api_key;
	private $default_graph_version;
    
	function __construct($app_id, $app_secret, $api_key, $default_graph_version='v2.10') {
		$this->app_id = $app_id;
		$this->app_secret = $app_secret;
		$this->api_key = $api_key;
		$this->default_graph_version = $default_graph_version;
	}
    
	function upload($filename, $message, $api_url) {
			
		$fb = new Facebook\Facebook([
			'app_id' => $this->app_id,
			'app_secret' => $this->app_secret,
			'default_graph_version' => $this->default_graph_version ]);
				
		$data = [
			'message' => $message,
			'source' => $fb->fileToUpload($filename) ];

		$imageid = 0;
    
		try {
			$response = $fb->post($api_url, $data, $this->api_key);
			$graphNode = $response->getGraphNode();
			$imageid = $graphNode['id'];
      
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			echo '<p>Graph returned an error: ' . $e->getMessage(). '</p>';
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			echo '<p>Facebook SDK returned an error: ' . $e->getMessage(). '</p>';
		}

		return $imageid;
	}
}

?>
