<?php
/* Excluder looks at post titles, urls and media items for things that don't belong */
class excluder {
  public function __construct( $post=NULL, $feed=NULL ) {
    $feed = NULL;
    // make sure that the class is instanciated with a good post
    if (!is_object($post) || (is_null($post)) ) {
      throw new Exception("FEED_CLASS did not get a valid post object");
    }
    else {
      $this->post = $post;
    }

    // set the exclude word array
    $this->title_phrases = array(
      'conference',
      'classified',
      'sign up',
      'signup',
      'giveaway'
    );

    $this->body_phrases = array(
      'contains affiliate links'
    );
  }

  // MAIN FUNTIONALITY
  public function run() {
    return $this->post; 
  }

  // DO NOT TOUCH!!!!!! This is the destructor method
  public function __destruct() {
    $this->post = NULL;
    $this->feed = NULL;
  }
}
