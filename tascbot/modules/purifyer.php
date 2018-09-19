<?php
class purifyurl {
  public function __construct( $post=NULL, $feed=NULL ) {
    $feed = NULL;
    // make sure that the class is instanciated with a good post
    if (!is_object($post) || (is_null($post)) ) {
      throw new Exception("FEED_CLASS did not get a valid post object");
    }
    else {
      $this->post = $post;
    }
  }

  // MAIN FUNTIONALITY
  public function run() {
    if ($start_pos !== FALSE) {
      // get string length
      $length = strlen($this->post->post_url);
      // get the position of the funky stuff on the url
      $start_pos = strpos($this->post->post_url,"?"); 
      $new_url = substr($this->post->post_url,0,$start_pos);
      print_r($new_url);
      $this->post->post_url = $new_url;
    }
    return $this->post; 
  }

  // DO NOT TOUCH!!!!!! This is the destructor method
  public function __destruct() {
    $this->post = NULL;
    $this->feed = NULL;
  }
}
