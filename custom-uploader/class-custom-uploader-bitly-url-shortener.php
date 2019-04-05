<?php
use Mremi\UrlShortener\Model\Link;
use Mremi\UrlShortener\Provider\Bitly\BitlyProvider;
use Mremi\UrlShortener\Provider\Bitly\GenericAccessTokenAuthenticator;

class BitlyUrlShortener {

  private $api_key;
  private $connect_timeout;
  private $timeout;
  private $link;

  function __construct($generic_access_token, $connect_timeout=1, $timeout=1) {
    $this->generic_access_token = $generic_access_token;
    $this->connect_timeout = $connect_timeout;
    $this->timeout = $timeout;
  }

  function generate_short_url($LongUrl) {
    $this->link = new Link;

    $this->link->setLongUrl($LongUrl);

    echo $this->generic_access_token;

    $bitlyProvider = new BitlyProvider(
        new GenericAccessTokenAuthenticator("5ad626d8da65ee2dc5ace4ea215781731759ac9f"),
        array('connect_timeout' => $this->connect_timeout, 'timeout' => $this->timeout)
    );

    return $bitlyProvider->shorten($this->link);

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
