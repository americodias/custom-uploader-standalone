<?php
require_once 'composer/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



class TumblrUploader {
    
    private $consumer_key;
    private $consumer_secret;
	private $oauth_token;
	private $oauth_secret;
	
	private function oauth_gen($method, $url, $iparams, &$headers) {
    
	    $iparams['oauth_consumer_key'] = $this->consumer_key;
	    $iparams['oauth_nonce'] = strval(time());
	    $iparams['oauth_signature_method'] = 'HMAC-SHA1';
	    $iparams['oauth_timestamp'] = strval(time());
	    $iparams['oauth_token'] = $this->oauth_token;
	    $iparams['oauth_version'] = '1.0';
	    $iparams['oauth_signature'] = $this->oauth_sig($method, $url, $iparams);
	    //print $iparams['oauth_signature'];  
	    $oauth_header = array();
	    foreach($iparams as $key => $value) {
	        if (strpos($key, "oauth") !== false) { 
	           $oauth_header []= $key ."=".$value;
	        }
	    }
	    $oauth_header = "OAuth ". implode(",", $oauth_header);
	    $headers["Authorization"] = $oauth_header;
	}
	
	private function oauth_sig($method, $uri, $params) {
    
	    $parts []= $method;
	    $parts []= rawurlencode($uri);
   
	    $iparams = array();
	    ksort($params);
	    foreach($params as $key => $data) {
	            if(is_array($data)) {
	                $count = 0;
	                foreach($data as $val) {
	                    $n = $key . "[". $count . "]";
	                    $iparams []= $n . "=" . rawurlencode($val);
	                    $count++;
	                }
	            } else {
	                $iparams[]= rawurlencode($key) . "=" .rawurlencode($data);
	            }
	    }
	    $parts []= rawurlencode(implode("&", $iparams));
	    $sig = implode("&", $parts);
	    return base64_encode(hash_hmac('sha1', $sig, $this->consumer_secret."&". $this->oauth_secret, true));
	}
	
    function __construct($consumer_key, $consumer_secret, $oauth_token, $oauth_secret) {
		$this->consumer_key = $consumer_key;
		$this->consumer_secret = $consumer_secret;
		$this->oauth_token = $oauth_token;
		$this->oauth_secret = $oauth_secret;
    }
	
	function upload($filename, $message, $keywords, $sourceurl, $blogname) {
		$headers = array("Host" => "http://api.tumblr.com/", "Content-type" => "application/x-www-form-urlencoded", "Expect" => "");
		$params = array("data" => file_get_contents($filename),
						"type" => "photo",
						"caption" => $message,
						'link' => $sourceurl,
						'source' => $sourceurl,
						"tags" => $keywords
					);
		
		$this->oauth_gen("POST", "http://api.tumblr.com/v2/blog/$blogname/post", $params, $headers);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, "PHP Uploader Tumblr v1.0");
		curl_setopt($ch, CURLOPT_URL, "http://api.tumblr.com/v2/blog/$blogname/post");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    "Authorization: " . $headers['Authorization'],
		    "Content-type: " . $headers["Content-type"],
		    "Expect: ")
		);
		
		$params = http_build_query($params);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		$response = curl_exec($ch);
		
		if($response)
			return json_decode($response, true);
		else
			return null;
	}
}

?>
