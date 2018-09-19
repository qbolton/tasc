<?php
class OB {
  static public function excludeRules($post,$feed=NULL,$options=NULL) {
    $retval = "NO_EXCLUDE";

    // set exclusion factors
    $acceptable_item_age = NULL;
    $feed_domain_only = NULL;

    // This is an exlusion based on something set in the parser itself
    if ( (isset($post->exclude)) && ($post->exclude == TRUE) ) {
      if ( (isset($post->exclude_reason)) && (! is_null($post->exclude_reason)) ) {
        $retval = $post->exclude_reason; 
      }
      else {
        $retval = "SIMPLE_POST_EXCLUDE"; 
      }
      return $retval;
    }
    
    // ====================================================
    // NO TITLE EXCLUSION
    // ====================================================
    if ( (is_null($post->post_title)) || (strlen($post->post_title) == 0) ) {
      // skip this post
      $retval = "NO_TITLE_FOUND"; return $retval;
    }

    // ====================================================
    // NO PERMALINK EXCLUSION
    // ====================================================
    if ( (is_null($post->post_url)) || (strlen($post->post_url) == 0) ) {
      // skip this post
      $retval = "NO_PERMALINK_FOUND"; return $retval;
    }

    // Check for options class
    if (!is_null($options)) {

      // ====================================================
      // MIN_CONTENT_LENGTH
      // ====================================================
      if ( (!$post->exclude_override) && (isset($options->minimum_content_length))) {
        if ( (strlen($post->post_body) < $options->minimum_content_length) &&
             (strlen($post->post_excerpt) < $options->minimum_content_length) ) {
          $retval = "CONTENT_LENGTH_EXCLUDE"; return $retval;
        }
      }

      // ====================================================
      // ACCEPTABLE_ITEM_AGE
      // ====================================================
      if (isset($options->acceptable_item_age)) {
        $acceptable_item_age = $options->acceptable_item_age;

        //print_r("2 days ago=" . strtotime($acceptable_item_age) . "|| publish date=" . $post->post_pubdate_int);
        if (strtotime($acceptable_item_age) >= $post->post_pubdate_int) {
          $retval = "ITEM_AGE_EXCLUDE"; return $retval; 
        }
      }

      // ====================================================
      // FEED_DOMAIN_ONLY (requires feed information)
      // ====================================================
      if (isset($options->feed_domain_only)) {
        $feed_domain_only = $options->feed_domain_only;
        if ($options->feed_domain_only) {
        // automatically exclude any item that has a domain not on the feed domain
        if ( (!is_null($feed)) && (isset($feed->web_url)) ) {
          $item_domain = str_replace('www.','',UrlKit::getDomain($post->post_url,FALSE));
          $feed_domain = str_replace('www.','',UrlKit::getDomain($feed->web_url,FALSE));
          if ( strcasecmp($item_domain,$feed_domain) != 0) {
            //print_r($post->post_url . " >>> " . $feed->web_url);
            $retval = "FEED_DOMAIN_EXCLUDE"; return $retval;
          }
        }
        }
      }
    }
    return strtoupper($retval);
  }

  static public function getImageSize($url, $referer = '')
  {
    $headers = array('Range: bytes=0-32768');

    /* Hint: you could extract the referer from the url */
    if (!empty($referer)) array_push($headers, 'Referer: '.$referer);

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    curl_close($curl);

    $image = imagecreatefromstring($data);

    $resource_type = get_resource_type($image);

    if (is_resource($image)) {
      $return = array(imagesx($image), imagesy($image));
    }
    else {
      $return = array(0,0);
    }

    imagedestroy($image);

    //list($width, $heigth) = getimgsize('http://cor-forum.de/forum/images/smilies/zombie.png', 'http://cor-forum.de/forum/');
    //echo $width.' x '.$heigth;
    return $return;
  }
}
