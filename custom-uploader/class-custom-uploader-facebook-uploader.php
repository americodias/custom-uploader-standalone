<?php

class FacebookUploader {
    
    private $api_key;
    private $fb;
    
    function __construct($app_id, $app_secret, $api_key, $default_graph_version='v2.10') {

        $this->api_key = $api_key;
        
        $this->fb = new Facebook\Facebook([
            'app_id' => $app_id,
            'app_secret' => $app_secret,
            'default_graph_version' => $default_graph_version,
            ]);
        }
    
        function upload($filename, $message, $api_url) {
            $data = [
                'message' => $message,
                'source' => $this->fb->fileToUpload($filename),
            ];

            $imageid = 0;
        
            try {
                $response = $this->fb->post($api_url, $data, $this->api_key);
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
