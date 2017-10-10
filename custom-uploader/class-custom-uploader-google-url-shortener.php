<?php
use Mremi\UrlShortener\Model\Link;
use Mremi\UrlShortener\Provider\Google\GoogleProvider;

class GoogleUrlShortener {

    private $api_key;
    private $connect_timeout;
    private $timeout;
	private $link;
	
    function __construct($api_key, $connect_timeout=1, $timeout=1) {
        $this->api_key = $api_key;
        $this->connect_timeout = $connect_timeout;
        $this->timeout = $timeout;
    }

    function generate_short_url($LongUrl) {
        $this->link = new Link;
	
        $this->link->setLongUrl($LongUrl);

        $googleProvider = new GoogleProvider(
        $this->api_key,
        array('connect_timeout' => $this->connect_timeout, 'timeout' => $this->timeout)
    	);

    	return $googleProvider->shorten($this->link);
        
	}
	
	function get_short_url () {
		if($this->link) {
			return $this->link->getShortUrl();
		}
	}

	function get_short_url_id () {
		if($this->link) {
			$url = $this->link->getShortUrl();
			$pieces = explode("/", $url);
			return end($pieces);
		}
	}
}

?>