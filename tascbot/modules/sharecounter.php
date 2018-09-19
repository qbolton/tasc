<?php
class sharecounter {
  private $share_key = "c1dd9c0fcc85f0b9ddf45a7ab6c4876b4e99cc94";

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
    $this->post->post_score = 0.0;
    $share_counts = $this->getShareCounts($this->post->post_url); 
    // add together the counts of: facebook, reddit, twitter, pinterest
    foreach($share_counts as $key => $value) {
      if (strcasecmp($key,'facebook') == 0) {
        $this->post->post_score += $value['total_count'];
      }
      else {
        $this->post->post_score += $value;
      }
    }
    return $this->post; 
  }

  private function getShareCounts($url) {
    $json = file_get_contents("http://free.sharedcount.com/?url=" . rawurlencode($url) . "&apikey={$this->share_key}");
    $counts = json_decode($json, true);
    return $counts;
  }

  // DO NOT TOUCH!!!!!! This is the destructor method
  public function __destruct() {
    $this->post = NULL;
    $this->feed = NULL;
  }
}

/*$sc = new sharecounter(new StdClass());
$sc->post->post_url = $argv[1];
print_r($sc->run());*/
