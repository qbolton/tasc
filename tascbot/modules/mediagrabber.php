<?php
/* This module grabs the full text for the post and checks for media */
class mediagrabber {
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
    $found_iframes = NULL;
    // grab the full text request body
    $ftr_request = sprintf('%s?url=%s&format=%s&html=%s&exc=%s&parser=html5php&summary=%s',
      'http://dev.elombre.com/ftr/makefulltextfeed.php',urlencode($this->post->post_url),'json',1,1,1);
    $full_text = Fetch::withCurl('GET',$ftr_request);
    $full_text = json_decode($full_text->contents);
    //print_r($full_text);
    // if present, search the content_encoded for iframes (video,podcasts,social media objects, etc)
    if ( (is_object($full_text)) && (isset($full_text->rss->channel->item->content_encoded)) ) {
      // execute DOMSearch
      $found_iframes = DOMSearch::video($full_text->rss->channel->item->content_encoded);
    }
    // if we found something and we have no video as of yet, add what we found
    if (!is_null($found_iframes)) {
      if (is_null($this->post->media->video)) {
        $this->post->media->video = $found_iframes;
      }
    }
    return $this->post; 
  }

  // DO NOT TOUCH!!!!!! This is the destructor method
  public function __destruct() {
    $this->post = NULL;
    $this->feed = NULL;
  }
}
