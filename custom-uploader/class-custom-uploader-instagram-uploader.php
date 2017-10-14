<?php

class InstagramUploader {
    
    private $username;
    private $password;
	private $debug;
	private $truncatedDebug;
    
    function __construct($username, $password, $debug = false, $truncatedDebug = false) {
        $this->username = $username;
		$this->password = $password;
		$this->debug = $debug;
		$this->truncatedDebug = $truncatedDebug;
	}
    
    function upload($filename, $message) {
		
		$ig = new \InstagramAPI\Instagram($this->debug, $this->truncatedDebug);
		
		$image_code = null;
		
		try {
		    $ig->login($this->username, $this->password);
			
		    // The most basic upload command, if you're sure that your photo file is
		    // valid on Instagram (that it fits all requirements), is the following:
		    // $ig->timeline->uploadPhoto($photoFilename, ['caption' => $captionText]);

		    // However, if you want to guarantee that the file is valid (correct format,
		    // width, height and aspect ratio), then you can run it through our
		    // automatic media resizer class. It is pretty fast, and only does any work
		    // when the input image file is invalid, so you may want to always use it.
		    // You have nothing to worry about, since the class uses temporary files if
		    // the input needs processing, and it never overwrites your original file.
		    // Also note that it has lots of options, so read its class documentation!
		    $resizer = new \InstagramAPI\MediaAutoResizer($filename);
		    $response = $ig->timeline->uploadPhoto($resizer->getFile(), ['caption' => $message]);
			
			$image_code = $response->getMedia()->getCode();
			
		} catch (\Exception $e) {
		    echo 'Something went wrong: '.$e->getMessage()."\n";
		    exit(0);
		}

        return $image_code;
    }
}

?>
