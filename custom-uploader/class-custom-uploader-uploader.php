<?php

require_once('composer/vendor/autoload.php');

class Custom_Uploader_Uploader {

  private $blog_id;
  private $base_path;
  private $version;
  private $upload_dir;

  function __construct($blog_id, $upload_dir=null, $version=null) {
    global $wp, $wpdb;

    if(!is_int($blog_id))
    die('Did you forgot something?');

    $this->blog_id = $blog_id;
    $this->base_path = $this->find_base_path();
    $this->version = $version;

    if($upload_dir !== null)
    $this->upload_dir = $upload_dir;
    else
    $this->upload_dir =  dirname(__FILE__).'/cache/uploads/';

    define("ABSPATH", $this->base_path);

    require_once($this->base_path . 'wp-load.php');

    switch_to_blog($this->blog_id);

    /* Initialize the theme in order the get the custom image sizes */
    /* https://i.imgur.com/SqQQE.png */


    require_once($this->base_path . 'wp-admin/includes/taxonomy.php');
    require_once($this->base_path . 'wp-admin/includes/image.php');
    require_once('class-custom-uploader-bitly-url-shortener.php');
    require_once('class-custom-uploader-facebook-uploader.php');
    require_once('class-custom-uploader-tumblr-uploader.php');
    require_once('class-custom-uploader-instagram-uploader.php');
    require_once('class-custom-uploader-exif-reader.php');
  }

  private function find_base_path() {
    /*
    $dir = dirname(__FILE__);
    do {
    //it is possible to check for other files here
    if( file_exists($dir."/wp-config.php") ) {
    return $dir . '/';
  }
} while( $dir = realpath("$dir/..") );
return null;
*/
return $_SERVER['DOCUMENT_ROOT'] . '/';
}

public function switch_to_blog_cache_clear( $blog_id, $prev_blog_id = 0 ) {
  if ( $blog_id === $prev_blog_id )
  return;

  wp_cache_delete( 'notoptions', 'options' );
  wp_cache_delete( 'alloptions', 'options' );
}

private function get_the_categories(){
  global $wpdb;

  if($this->blog_id)
  $table_prefix='wp_'.$this->blog_id;
  else
  $table_prefix='wp';

  $myrows = $wpdb->get_results( 'SELECT ' . $table_prefix . '_terms.term_id, ' . $table_prefix . '_terms.name
  FROM ' . $table_prefix . '_term_taxonomy
  LEFT JOIN ' . $table_prefix . '_terms
  ON ' . $table_prefix . '_term_taxonomy.term_id=' . $table_prefix . '_terms.term_id
  WHERE ' . $table_prefix . '_term_taxonomy.taxonomy="category"
  ORDER BY ' . $table_prefix . '_terms.name ASC' );

  $categories = array();
  foreach ($myrows as $row)
  $categories[$row->term_id] = $row->name;

  return $categories;

}

private function get_the_galleries() {
  global $wpdb;

  if($this->blog_id)
  $table_prefix='wp_'.$this->blog_id;
  else
  $table_prefix='wp';

  $myrows = $wpdb->get_results( 'SELECT ' . $table_prefix . '_posts.ID, ' . $table_prefix . '_posts.post_title
  FROM ' . $table_prefix . '_posts
  WHERE ' . $table_prefix . '_posts.post_type="eramgallery" AND ' . $table_prefix . '_posts.post_status="publish"
  ORDER BY ' . $table_prefix . '_posts.ID DESC' );

  $galleries = array();
  foreach ($myrows as $row)
  $galleries[$row->ID] = $row->post_title;

  return $galleries;
}

private function upload_form () {
  $values['id'] = '1';
  Form::open ("upload", $values, array ('enctype' => "multipart/form-data", 'view' => 'SideBySide' . $this->version));
  echo '<legend>File upload</legend>';
  Form::Hidden("id");
  Form::File("", "file", array('class' => 'pull-right'));
  Form::Button ("Submit", 'submit', array('class' => 'btn-primary pull-right'));
  Form::close(false);
}

private function edit_form () {
  global $_POST, $_FILES;

  $cup_options = get_option( 'cup_options' );            					// Array of All Options
  $facebook_is_enabled = $cup_options['cup_facebook_is_enabled'];
  $tumblr_is_enabled = $cup_options['cup_tumblr_is_enabled'];
  $instagram_is_enabled = $cup_options['cup_instagram_is_enabled'];
  $mobile_gallery_id = $cup_options['cup_mobile_gallery_id'];
  $dslr_gallery_id = $cup_options['cup_dslr_gallery_id'];

  $check = getimagesize($_FILES["file"]["tmp_name"]);

  if($check === false) {
    echo "Error on file upload.";
    die();
  }

  // Check file size
  if ($_FILES["file"]["size"] > 32000000) {
    echo "Sorry, file is too large.";
    die();
  }

  $target_file = $this->upload_dir . remove_accents(sanitize_file_name( basename($_FILES["file"]["name"])));
  $target_file_url = str_replace($this->base_path,
  (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/",
  $target_file);

  $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);

  // Allow certain file formats
  if(strcasecmp($imageFileType, "jpg") && strcasecmp($imageFileType, "png") &&
  strcasecmp($imageFileType, "jpeg") && strcasecmp($imageFileType, "gif")) {
    echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    die();
  }

  if (!move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
    echo "Sorry, there was an error uploading your file.";
    die();
  }

  $categories = $this->get_the_categories();
  $galleries = $this->get_the_galleries();

  $values['id'] = '2';
  $exifReader = new ExifReader();
  $data = $exifReader->extract_data($target_file);
  $values = array_merge ($values, $data);

  if(!strcmp($values['make'], "Apple" )) {
    $values['galleries'] = array($mobile_gallery_id);
  } else {
    $values['galleries'] = array($dslr_gallery_id);
  }

  $date = DateTime::createFromFormat('Y:m:d H:i:s', $values['date']);

  if($date !== false)
  $values['date_taken'] = strftime('%Y-%m-%d %H:%M:%S', $date->getTimestamp());

  if($values['title'] == "") {
    date_default_timezone_set('UTC');
    setlocale(LC_ALL, "pt_PT.UTF8");
    if($date !== false)
    $values['title'] = strftime('%A, %d de %B de %Y', $date->getTimestamp());
  }

  //$values['post_to_social_networds'] = array(1,2);
  //ExifReaderTest($target_file);
  //print_r($values);

  $post_to_social_networks = array();
  $values['post_to_social_networks'] = array();

  if($facebook_is_enabled) {
    $post_to_social_networks['facebook'] = "Facebook";
    array_push($values['post_to_social_networks'], 'facebook');
  }
  if($tumblr_is_enabled) {
    $post_to_social_networks['tumblr'] = "Tumblr";
    array_push($values['post_to_social_networks'], 'tumblr');
  }
  if($instagram_is_enabled) {
    $post_to_social_networks['instagram'] = "Instragram";
    array_push($values['post_to_social_networks'], 'instagram');
  }

  Form::open ("edit", $values, array ('view' => 'SideBySide' . $this->version));
  echo '<legend>Edit details</legend>';
  Form::Hidden("id");
  echo '<img src="' . $target_file_url . '" width="100%" style="margin-bottom:20px">';
  Form::Hidden ("filename", $target_file);
  Form::Textbox ("Title", "title");
  Form::Textbox ("Description", "description");
  Form::Textbox ("Make", "make", array("readonly" => 1));
  Form::Textbox ("Model", "model", array("readonly" => 1));
  Form::Textbox ("Lens", "lens", array("readonly" => 1));
  Form::Textbox ("Exposure", "exposure", array("readonly" => 1));
  Form::Textbox ("Exposure Bias", "exposure_bias", array("readonly" => 1));
  Form::Textbox ("Aperture", "aperture", array("readonly" => 1));
  Form::Textbox ("Focal Lenght", "focallength", array("readonly" => 1));
  Form::Textbox ("ISO", "iso", array("readonly" => 1));
  Form::Textbox ("Flash", "flash", array("readonly" => 1));
  Form::Textbox ("GPS", "gps", array("readonly" => 1));
  Form::Textbox ("Date Taken", "date_taken");
  Form::Textarea ("Keywords", "keywords");
  if (!empty($categories)) {
    Form::Checkbox ("Categories", "categories", $categories);
  }
  if (!empty($galleries)) {
    Form::Checkbox ("Galleries", "galleries", $galleries);
  }
  Form::Checkbox ("Social networks", "post_to_social_networks", $post_to_social_networks);

  Form::Button ("Submit", 'submit', array('class' => 'btn-primary pull-right'));
  Form::close(false);

  if(!strcmp($values['make'], "Apple"))
  return true;
  else
  return false;
}

private function pretty_shutter_speed($shutter_speed) {
  if ($shutter_speed) {
    if ((1 / $shutter_speed) > 1) {
      $speed = "1/";
      if ((number_format((1 / $shutter_speed), 1)) == 1.3
      or number_format((1 / $shutter_speed), 1) == 1.5
      or number_format((1 / $shutter_speed), 1) == 1.6
      or number_format((1 / $shutter_speed), 1) == 2.5) {
        $speed .= number_format((1 / $shutter_speed), 1, '.', '') . "s";
      }
      else
      $speed .= number_format((1 / $shutter_speed), 0, '.', '') . "s";
    }
    else
    $speed = $shutter_speed."s";

    return $speed;
  }
}

private function is_mobile($attach_id, $mobile_gallery_id) {
  global $wpdb;

  if($this->blog_id)
  $table_prefix='wp_'.$this->blog_id;
  else
  $table_prefix='wp';

  $myrows = $wpdb->get_results( 'SELECT * FROM ' . $table_prefix
  . '_postmeta WHERE post_id = ' . $mobile_gallery_id
  . ' AND meta_key = \'eg_photos\'');

  $is_mobile = false;
  if(!empty($myrows)) {
    // Get current photos
    $photos_array = explode(",", $myrows[0]->meta_value);
    if(in_array($attach_id, $photos_array) )
    $is_mobile = true;
  }

  return $is_mobile;
}

private function get_message($attach_id, $short_url, $disp_keywords = true, $endl = "\r\n") {

  $cup_options = get_option( 'cup_options' );
  $bitly_url_shortener_is_enabled = $cup_options['cup_bitly_url_shortener_is_enabled'];

  $imgmeta = wp_get_attachment_metadata($attach_id);

  $meta_parameters = array();
  if(!empty($imgmeta['image_meta']['camera']))
  array_push($meta_parameters, $imgmeta['image_meta']['camera']);
  if(!empty($imgmeta['image_meta']['focal_length']))
  array_push($meta_parameters, $imgmeta['image_meta']['focal_length'].'mm');
  if(!empty($imgmeta['image_meta']['shutter_speed']))
  array_push($meta_parameters, $this->pretty_shutter_speed($imgmeta['image_meta']['shutter_speed']));
  if(!empty($imgmeta['image_meta']['aperture']))
  array_push($meta_parameters, 'f/'.$imgmeta['image_meta']['aperture']);
  if(!empty($imgmeta['image_meta']['iso']))
  array_push($meta_parameters, 'ISO'.$imgmeta['image_meta']['iso']);

  $metadata = '';
  foreach ($meta_parameters as $meta_parameter) {
    if(!empty($meta_parameter))
    $metadata .= $meta_parameter . ', ';
  }

  $message = html_entity_decode(get_the_title($attach_id)) . $endl;

  if($bitly_url_shortener_is_enabled)
  if(!empty($short_url)) {
    $message .= $short_url . $endl;
  }

  if(strlen($metadata))
  $message .= '(' . trim($metadata, ', ') . ')';

  if($disp_keywords) {
    $keywords = get_the_tags($attach_id);

    if($keywords) {
      $message .= $endl . $endl;
      foreach ($keywords as $keyword)
      $message .= '#' . preg_replace('/\s+/', '', $keyword->name) . ' ';
    }
  }

  return $message;
}

function post_to_tumblr ($attach_id) {
  $cup_options = get_option( 'cup_options' );            					// Array of All Options
  $tumblr_blog_name = $cup_options['cup_tumblr_blog_name'];
  $tumblr_consumer_key = $cup_options['cup_tumblr_consumer_key'];
  $tumblr_consumer_secret = $cup_options['cup_tumblr_consumer_secret'];
  $tumblr_oauth_token = $cup_options['cup_tumblr_oauth_token'];
  $tumblr_oauth_secret = $cup_options['cup_tumblr_oauth_secret'];
  $tumblr_is_enabled = $cup_options['cup_tumblr_is_enabled'];
  $bitly_url_shortener_is_enabled = $cup_options['cup_bitly_url_shortener_is_enabled'];
  $bitly_url_shortener_domain = $cup_options['cup_bitly_url_shortener_domain'];

  if($tumblr_is_enabled) {
    if($bitly_url_shortener_is_enabled)
    $short_url = 'https://' . $bitly_url_shortener_domain . '/' . get_post_meta( $attach_id, 'cup_bitly_short_url_id', true );
    else
    $short_url = null;

    $tumblr_uploader = new TumblrUploader($tumblr_consumer_key, $tumblr_consumer_secret, $tumblr_oauth_token, $tumblr_oauth_secret);
    $filename = get_attached_file( $attach_id );
    $message = $this->get_message($attach_id, $short_url, false);

    $keywords = get_the_tags($attach_id);

    $keyword_names = '';

    if($keywords) {
      foreach ($keywords as $keyword)
      $keyword_names .= $keyword->name . ', ';
    }

    $result = $tumblr_uploader->upload($filename, $message, $keyword_names, $short_url, $tumblr_blog_name);

    if($result['response']['id']) {
      update_post_meta($attach_id, 'cup_tumblr_id', $result['response']['id']);
      return $result['response']['id'];
    }
  }

  return 0;

}

function post_to_facebook ($attach_id) {

  $cup_options = get_option( 'cup_options' );                               // Array of All Options
  $facebook_app_id = $cup_options['cup_facebook_app_id'];                   // Facebook App ID
  $facebook_app_secret = $cup_options['cup_facebook_app_secret'];           // Facebook App Secret
  $facebook_api_key = $cup_options['cup_facebook_api_key'];                 // Facebook API key
  $facebook_mobile_album_id = $cup_options['cup_facebook_mobile_album_id']; // Facebook Mobile Album ID
  $mobile_gallery_id = $cup_options['cup_mobile_gallery_id'];               // Mobile Gallery ID
  $facebook_is_enabled = $cup_options['cup_facebook_is_enabled'];
  $bitly_url_shortener_is_enabled = $cup_options['cup_bitly_url_shortener_is_enabled'];
  $bitly_url_shortener_domain = $cup_options['cup_bitly_url_shortener_domain'];

  if($facebook_is_enabled) {
    if($bitly_url_shortener_is_enabled)
    $short_url = 'https://' . $bitly_url_shortener_domain . '/' . get_post_meta( $attach_id, 'cup_bitly_short_url_id', true );
    else
    $short_url = null;

    $message = $this->get_message($attach_id, $short_url, true);

    $is_mobile = $this->is_mobile($attach_id, $mobile_gallery_id);

    if($is_mobile)
    $api_url = '/' . $facebook_mobile_album_id . '/photos';
    else
    $api_url = '/me/photos';

    $facebookUploader = new FacebookUploader( $facebook_app_id, $facebook_app_secret, $facebook_api_key);
    $filename = get_attached_file( $attach_id );

    $facebook_id = $facebookUploader->upload($filename, $message, $api_url);

    if($facebook_id) {
      update_post_meta($attach_id, 'cup_facebook_id', $facebook_id);
      return $facebook_id;
    }
  }

  return 0;

}

function post_to_instagram ($attach_id) {

  $cup_options = get_option( 'cup_options' );                               // Array of All Options
  $instagram_is_enabled = $cup_options['cup_instagram_is_enabled'];
  $instagram_username = $cup_options['cup_instagram_username'];
  $instagram_password = $cup_options['cup_instagram_password'];
  $bitly_url_shortener_is_enabled = $cup_options['cup_bitly_url_shortener_is_enabled'];
  $bitly_url_shortener_domain = $cup_options['cup_bitly_url_shortener_domain'];

  if($instagram_is_enabled) {
    if($bitly_url_shortener_is_enabled)
    $short_url = 'https://' . $bitly_url_shortener_domain . '/' . get_post_meta( $attach_id, 'cup_bitly_short_url_id', true );
    else
    $short_url = null;

    $message = $this->get_message($attach_id, $short_url, true, "\n");

    $instagramUploader = new InstagramUploader( $instagram_username, $instagram_password);
    $filename = get_attached_file( $attach_id );

    $instagram_code = $instagramUploader->upload($filename, $message);

    if($instagram_code) {
      update_post_meta($attach_id, 'cup_instagram_code', $instagram_code);
      return $instagram_code;
    }
  }

  return null;

}

private function post_to_wordpress () {
  global $_POST;

  // The ID of the post this attachment is for.
  $parent_post_id = 0;
  // Initialization of the attachment id (unattached)
  $attach_id = 0;
  // Get the path to the upload directory.
  $wp_upload_dir = wp_upload_dir();

  $new_filename = $wp_upload_dir['path'] . '/' . remove_accents(sanitize_file_name( basename( $_POST['filename'] )));

  $dir_name = dirname($new_filename);

  //if the directory doesn't exist, create it
  if(!file_exists($dir_name)) {
    mkdir($dir_name);
  }

  if (@fclose(@fopen($_POST['filename'], "r"))) { //make sure the file actually exists
    copy($_POST['filename'], $new_filename);

    // Check the type of file. We'll use this as the 'post_mime_type'.
    $filecheck = wp_check_filetype( basename( $new_filename ), null );

    // Prepare an array of post data for the attachment.
    $attachment = array(
      'guid'			  	=> $wp_upload_dir['url'] . '/' . basename( $new_filename ),
      'post_mime_type'	=> $filecheck['type'],
      'post_title'		=> $_POST['title'],
      'post_name' 		=> sanitize_title_with_dashes(str_replace("_", "-", basename( $new_filename ))),
      'post_content'	  	=> '',
      'post_status'	   	=> 'inherit',
      'post_excerpt'	  	=> $_POST['description'],
      'post_date'		 	=> current_time('mysql'),
      //'post_date_gmt'	 	=> current_time('mysql'),
      //'post_modified_gmt' => current_time('mysql')
    );

    // Insert the attachment.
    $attach_id = wp_insert_attachment( $attachment, $new_filename, $parent_post_id );

    if($attach_id) {
      $attach_data = $this->generate_attachment_metadata($attach_id);

      // Generate the metadata for the attachment, and update the database record.
      //$attach_data = wp_generate_attachment_metadata( $attach_id, $new_filename );
      //$attach_data = wp_get_attachment_metadata($attach_id);

      // Extra metadata fields
      if(!empty($_POST['lens']))
      $attach_data['image_meta']['lens'] = $_POST['lens'];
      if(!empty($_POST['flash']))
      $attach_data['image_meta']['flash'] = $_POST['flash'];
      if(!empty($_POST['exposure_bias']))
      $attach_data['image_meta']['exposure_bias'] = $_POST['exposure_bias'];
      if(!empty($_POST['gps'])) {
        $gps = explode(",", $_POST['gps']);
        if(!empty($gps[0]) && !empty($gps[1])) {
          $latitude = CoordinatesConverter::decimal_to_dms($gps[0], true);
          $latitude_ref = array_pop($latitude);
          $longitude = CoordinatesConverter::decimal_to_dms($gps[1], false);
          $longitude_ref = array_pop($longitude);
          $attach_data['image_meta']['latitude'] = $latitude;
          $attach_data['image_meta']['latitude_ref'] = $latitude_ref;
          $attach_data['image_meta']['longitude'] = $longitude;
          $attach_data['image_meta']['longitude_ref'] = $longitude_ref;
        }
      }

      if(!empty($_POST['keywords'])) {
        //$attach_data['image_meta']['keywords'] = $_POST['keywords'];
        wp_set_post_tags( $attach_id, $_POST['keywords'], true );
      }

      wp_update_attachment_metadata( $attach_id, $attach_data );

      if(!empty($_POST['title']))
      update_post_meta($attach_id, '_wp_attachment_image_alt', $_POST['title']);

      if(!empty($_POST['date_taken']))
      update_post_meta($attach_id, 'cup_date_taken', $_POST['date_taken']);

      if(!empty($_POST['categories']))
      wp_set_post_categories( $attach_id, $_POST['categories'], true );

      set_post_thumbnail( $parent_post_id, $attach_id );

      //$this->regenerate_intermediate_image_sizes($attach_id);
    }
  }

  return $attach_id;
}

private function update_galleries($attach_id, $galleries) {
  global $wpdb;

  if($this->blog_id)
  $table_prefix='wp_'.$this->blog_id;
  else
  $table_prefix='wp';

  if (!empty($galleries)) {
    foreach($galleries as $gallery_id) {
      $myrows = $wpdb->get_results( 'SELECT * FROM ' . $table_prefix
      . '_postmeta WHERE post_id = ' . $gallery_id
      . ' AND meta_key = \'eg_photos\'');

      if(!empty($myrows)) {
        // Get current photos
        $photos_array = explode(",", $myrows[0]->meta_value);
        // Sanitize the array (remove missing photos)
        $new_photo_array = array();
        foreach($photos_array as $photo_id) {
          if ( wp_attachment_is_image( $photo_id ) ) {
            array_push($new_photo_array, $photo_id);
          }
        }
        // Add new photo to the beginning
        array_unshift($new_photo_array, $attach_id);

        $short_by_date=get_post_meta( $gallery_id, 'eg_short_by_date', true );

        if(strcmp($short_by_date, 'on') == 0) {
          $attachments = get_posts(
            array(
              'include' => $new_photo_array,
              'post_type' => 'attachment',
              'post_mime_type' => 'image',
              'orderby'   => 'meta_value',
              'meta_query' => array(array('key' => 'cup_date_taken')),
              'order' => 'DESC'
            ));

            $new_photo_array = array();

            foreach($attachments as $attachment) {
              array_push($new_photo_array, $attachment->ID);
            }
          }

          $photos = implode(",", $new_photo_array);

          if(count($new_photo_array) > 0)
          $query_results = $wpdb->get_results( 'UPDATE ' . $table_prefix
          .'_postmeta SET meta_value = \'' . $photos . '\' WHERE '
          . $table_prefix .'_postmeta.meta_id = ' . $myrows[0]->meta_id);

        }
        else
        return false;
      }
    }

    return true;
  }

  private function delete_files() {
    // Delete files on upload dir
    $files = glob($this->upload_dir . '/*'); // get all file names
    foreach($files as $file) { // iterate files
      if(is_file($file))
      unlink($file); // delete file
    }
  }

  function create_short_url($attach_id) {
    $cup_options = get_option( 'cup_options' );
    $bitly_url_shortener_generic_access_token = $cup_options['cup_bitly_url_shortener_generic_access_token'];
    $bitly_url_shortener_is_enabled = $cup_options['cup_bitly_url_shortener_is_enabled'];

    if($bitly_url_shortener_is_enabled) {
      // Create short URL
      $url_shortner = new BitlyUrlShortener($bitly_url_shortener_generic_access_token);
      $url_shortner->generate_short_url(get_attachment_link($attach_id));
      $short_url = $url_shortner->get_short_url();

      if(strlen($short_url) > 0) {
        update_post_meta($attach_id, 'cup_bitly_short_url_id', $url_shortner->get_short_url_id());
      }

      return $short_url;
    }

    return null;
  }


  private function generate_attachment_metadata($post_id) {

    /*
    * For some reason when we use the switch_to_blog function
    * the image sizes are not updated according to the new blog
    * theme. This is a more clean solution than calling
    * the current theme functions.php
    */
    $cup_options = get_option( 'cup_options' );
    $image_sizes = $cup_options['image_sizes'];


    foreach($image_sizes as $key=>$image_size) {
      if($key == 'thumbnail') {
        update_option( 'thumbnail_size_w', $image_size['width'] );
        update_option( 'thumbnail_size_h', $image_size['height'] );
        update_option( 'thumbnail_crop', $image_size['crop']);
      }
      elseif($key == 'medium') {
        update_option( 'medium_size_w', $image_size['width'] );
        update_option( 'medium_size_h', $image_size['height'] );
      }
      elseif($key == 'medium_large') {
        update_option('medium_large_size_w', $image_size['width']);
        update_option('medium_large_size_h', $image_size['height']);
      }
      elseif($key == 'large') {
        update_option( 'large_size_w', $image_size['width'] );
        update_option( 'large_size_h', $image_size['height'] );
      }
      else {
        remove_image_size($key);
        add_image_size($key, $image_size['width'], $image_size['height'], $image_size['crop']);
      }
    }

    $attachment_meta = wp_get_attachment_metadata($post_id);

    return wp_generate_attachment_metadata($post_id, get_attached_file($post_id));

  }

  private function upload_and_finish () {
    global $_POST;
    $cup_options = get_option( 'cup_options' );
    $tumblr_blog_name = $cup_options['cup_tumblr_blog_name'];

    $attach_id = $this->post_to_wordpress();
    /*
    var_dump(get_intermediate_image_sizes());
    die();*/

    if($attach_id) {
      // Update categories
      $update_result = $this->update_galleries($attach_id, $_POST['galleries']);

      if($update_result)
      echo '<p>Galleries updated.</p>';
      else
      echo '<p>Error while updating galleries.</p>';

      $short_url = $this->create_short_url($attach_id);

      if(strlen($short_url) > 0) {
        echo '<p>Short url: <a target="_blank" href="' . $short_url . '">' . $short_url . '</a></p>';
      }

      if(isset($_POST['post_to_social_networks'])) {
        if(array_search('facebook', $_POST['post_to_social_networks']) !== false) {
          // Facebook upload
          $facebook_id = $this->post_to_facebook($attach_id);

          if($facebook_id) {
            $facebook_url = 'https://www.facebook.com/photo.php?fbid=' . $facebook_id;
            echo '<p>Facebook url: <a target="_blank" href="' . $facebook_url . '">' . $facebook_url . '</a></p>';
          }
        }
        if(array_search('tumblr', $_POST['post_to_social_networks']) !== false) {
          // Tumblr upload
          $tumblr_id = $this->post_to_tumblr($attach_id);

          if($tumblr_id) {
            $tumblr_url = 'https://' . $tumblr_blog_name . '.tumblr.com/post/' . $tumblr_id;
            echo '<p>Tumblr url: <a target="_blank" href="' . $tumblr_url . '">' . $tumblr_url . '</a></p>';
          }
        }
        if(array_search('instagram', $_POST['post_to_social_networks']) !== false) {
          // Instagram upload
          $instagram_code = $this->post_to_instagram($attach_id);

          if($instagram_code) {
            $instagram_url = 'https://www.instagram.com/p/' . $instagram_code;
            echo '<p>Instagram url: <a target="_blank" href="' . $instagram_url . '">' . $instagram_url . '</a></p>';
          }
        }
      }


      echo '<p>All done!</p>';
    }
    else
    echo '<p>Error posting picture to WordPress.</p>';

    echo '<p><a href="/upload">Go back</a></p>';

    $this->delete_files();

    echo "\n<!--\n";
    var_dump($_POST, $_SESSION);
    echo "\n-->";
  }

  function render() {

    $loader = new Twig_Loader_Filesystem(dirname(__FILE__) . '/templates');
    $twig = new Twig_Environment($loader);

    // Display forms:
    $header_t = $twig->load('header.tpl');
    echo $header_t->render(array('the_post' => $_POST));

    if( !isset($_POST['id']) ) {
      // Upload form:
      $this->upload_form();
    }
    else if ($_POST['id'] == 1) {
      // Edit form:
      $isApple = $this->edit_form();
    }
    else if ($_POST['id'] == 2) {
      // Upload and show result:
      $this->upload_and_finish();
    }

    $footer_t = $twig->load('footer.tpl');
    $select_script = '';

    echo $footer_t->render(array('select_script' => $select_script));
  }

}

?>
