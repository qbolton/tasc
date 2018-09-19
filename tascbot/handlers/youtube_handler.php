<?php
class youtube_handler {
  public function __construct( $post=NULL, $feed=NULL ) {
    // make sure that the class is instanciated with a good post
    if (!is_object($post) || (is_null($post)) ) {
      throw new Exception("FEED_CLASS did not get a valid post object");
    }
    else {
      $this->post = $post;
    }
    // make sure that the class is instanciated with a good feed 
    if (!is_object($feed) || (is_null($feed)) ) {
      throw new Exception("FEED_CLASS did not get a valid feed object");
    }
    else {
      $this->feed = $feed;
    }
  }

  // MAIN FUNTIONALITY
  public function run() {
    // url to get youtube video images (0-3)
    // http://img.youtube.com/vi/huLY0RnvkoQ/1.jpg

    // get the id off of the post_url
    $id = stristr($this->post->post_url,"="); 
    // then we know that what we want is found
    if ($id !== FALSE) {
      // id of the video for embedding
      $youtube_id = substr($id,1);
      $embed_src = "https://www.youtube.com/embed/{$youtube_id}";
      $embed_width = 320;
      $embed_height = 180;
      $embed_html = '
        <iframe width="'.$embed_width.
        '" height="'.$embed_height.
        '" src="'.$embed_src.
        '" frameborder="0" scrolling="no" allowtransparency="true" allowfullscreen></iframe>';

      // add this info the the post->media->video array
      $this->post->media->video[] = array(
        'html'=>$embed_html,
        'media_width'=>$embed_width,
        'media_height'=>$embed_height,
        'media_src'=>$embed_src
      );
      //print_r($this->post->media->video);

      // add images
      $image = array();
      $image['media_src'] = 'http://img.youtube.com/vi/' . $youtube_id . '/0.jpg';
      $image['media_width'] = 0;
      $image['media_height'] = 0;
      $image['media_alt'] = $this->post->post_title;
      $image['media_title'] = "";

      if (is_array($this->post->media->images)) {
        $this->post->media->images[0] = $image;
      }
      else {
        $this->post->media->images = array();
        $this->post->media->images[0] = $image;
      }
    }

    // to ensure we have a nice situation
    if ((is_null($this->post->post_excerpt)) || (strlen($this->post->post_excerpt) == 0) ) {
      // set the excerpt to a space
      $this->post->post_excerpt = " ";
    }

    // set $post->exclude_override so that this will get posted unless it's too 
    // old or doesn't have required post elements.  This ensures that this will pass the content 
    // exclude rule
    $this->post->exclude_override = TRUE;

    return $this->post; 
  }

  // DO NOT TOUCH!!!!!! This is the destructor method
  public function __destruct() {
    $this->post = NULL;
    $this->feed = NULL;
  }
}
