<?php
class RSSParser extends OBParser {

  // ==============================================
  // Parse()
  // Breaks the passed in feed item up into it's 
  // fields for saving
  // ==============================================
  public function parse() {
    $this->media = new StdClass();
    $this->tags = new StdClass();

    $this->media->images = NULL;
    $this->media->video = NULL;

    // set site id
    $this->vars->site_id = $this->raw_item->site_id;
    // set feed id
    $this->vars->fid = $this->raw_item->fid;

    // set title
    $this->setTitle();
    // set document url
    $this->setUrl();
    // set permalink
    $this->setPermalink();
    // set author
    $this->setCreator();
    // set description and post_excerpt
    $this->setDescription();
    // set encoded items
    $this->setEncoded();
    // set publication date
    $this->setPubDate();
    // set the thumbnail
    $this->setThumbnail();
    // set commentRSS
    $this->setCommentRSS();
    // get the RSS comments
    $this->setTags();

    // set hash value 
    $this->vars->post_hash = CleanText::hash($this->vars->post_url);

    // check size of post_excerpt
    if ((is_null($this->vars->post_excerpt)) || (strlen($this->vars->post_excerpt) < 30)) {
      $this->exclude = TRUE;
    }
  }

  // set the author/creator
  public function setCreator() {
    // get assumed author/creator info
    if (isset($this->raw_item->creator)) {
      $this->vars->post_creator = trim($this->raw_item->creator);
    }
  }

  // grab the title of the document
  public function setTitle() {
    $post_title = NULL;

    // create document title
    $post_title = preg_replace("/\[([^\[\]]*+|(?R))*\]/","",$this->raw_item->title); 
    $post_title = ucwords( strtolower( CleanText::makeUTF8(trim($post_title)) ) );
    $this->vars->post_title = $post_title;

    // create seo_title value
    $this->vars->post_seo_title = CleanText::sanitize(trim($this->raw_item->title));
  }

  // set the post_url
  public function setUrl() {
    // get the original url if available, otherwise use link
    if (isset($this->raw_item->origLink)) {
      if (isset($this->raw_item->origLink->content)) {
        $this->vars->post_url = trim($this->raw_item->origLink->content);
      }
      else {
        $this->vars->post_url = trim($this->raw_item->origLink);
      }
    }
    else {
      $this->vars->post_url = trim($this->raw_item->link);
    }
  }

  // set the post_desc
  public function setDescription() {
    $html = new SimpleHtmlDom\simple_html_dom();
    if (isset($this->raw_item->description)) {
      // if description is represented as array
      if (is_array($this->raw_item->description)) {
        $html->load(trim($this->raw_item->description[1]));
      }
      else {
        $html->load(trim($this->raw_item->description));
      }
      $this->vars->description = trim( $html->plaintext );
      $this->vars->post_excerpt = trim(CleanText::makeUTF8( $html->plaintext ));
    }
    else if (isset($this->raw_item->encoded)) {
      $html->load(trim($this->raw_item->encoded));
      $this->vars->description = trim( $html->plaintext );
      $this->vars->post_excerpt = trim(CleanText::makeUTF8( $html->plaintext ));
    }
    else {
      $this->vars->description = NULL;
      $this->vars->post_excerpt = NULL;
    }
    $html = NULL;
  }

  // set the publication date of the item
  public function setPubDate() {
    if (isset($this->raw_item->pubDate)) {
      $this->vars->post_pubdate_int = strtotime( $this->raw_item->pubDate );
      $this->vars->post_pubdate = date("Y-m-d H:i:s",$this->vars->post_pubdate_int);
    }
    else {
      // how do you not put pubDate in your feed?
      // accomodate this foolishness by using the current date and time
      $this->vars->post_pubdate_int = strtotime("now");
      $this->vars->post_pubdate = date("Y-m-d H:i:s",$this->vars->post_pubdate_int);
    }

    // if, for example, the pubdate is in the future give it current date and time
    $diff = time() - $this->vars->post_pubdate_int;
    if ($diff < 0) {
      $this->vars->post_pubdate_int = strtotime("now");
      $this->vars->post_pubdate = date("Y-m-d H:i:s",$this->vars->post_pubdate_int);
    }
  }

  // set the comment url for the post (currently not used)
  public function setCommentRSS() {
    if (isset($this->raw_item->commentRss)) {
      if (isset($this->raw_item->commentRss->content)) {
        $this->vars->post_comment_url = $this->raw_item->commentRss->content;
      }
      else {
        $this->vars->post_comment_url = $this->raw_item->commentRss;
      }
    }
  }
  
  // set the post thumbnail
  public function setThumbnail() {
    if (is_null($this->media->images)) {
      $this->media->images = array();
    }

    // add thumbnail item to image media array
    if (isset($this->raw_item->thumbnail)) {
      // create article image array
      $image = array();
      $image['src'] = $this->raw_item->thumbnail->url;
      $image['width'] = ((isset($this->raw_item->thumbnail->width)) ? $this->raw_item->thumbnail->width : 0);
      $image['height'] = ((isset($this->raw_item->thumbnail->height)) ? $this->raw_item->thumbnail->height : 0);
      $image['alt'] = "";
      $image['title'] = "";
      $this->media->images[] = $image;
    }
  }

  // set the post tags
  public function setTags() {
    if (isset($this->raw_item->category)) {

      //print_r($this->raw_item->category); exit;

      if ((is_object($this->raw_item->category)) && (isset($this->raw_item->category->content))) {
          $this->tags->category = explode(',',$this->raw_item->category->content);
      }
      // if category is present but it's not an array
      else if (!is_array($this->raw_item->category)) {
        // break the thing up if comma separated
        $tmp = explode(',',$this->raw_item->category);
        // check to see if tmp has count greater than one
        if (count($tmp) > 1) { 
          $this->tags->category = $tmp; 
        }
        else {
          $this->tags->category = array($this->raw_item->category);
        }
      }
      else {
        $this->tags->category = $this->raw_item->category;
      }
    }
  }

  // use the guid to set the permalink up
  public function setPermalink() {
    if (isset($this->raw_item->guid)) {
      $this->vars->post_permalink = $this->raw_item->guid->content;
    }
  }

  // handle the encoded content of the feed
  public function setEncoded() {
    if (isset($this->raw_item->encoded)) {
      $this->vars->post_encoded = $this->raw_item->encoded;

      // grab images
      $this->media->images = DOMSearch::images($this->vars->post_encoded);

      // grab videos
      $this->media->video = DOMSearch::video($this->vars->post_encoded);
    }
  }

  /*public function savePost() {
    try {
      // get db connection
      $conn = new DBConnection($GLOBALS['cli']->dbinfo);
      // get instance of posts table
      $table = new CgRawPostsTable($conn);

      if (isset($this->tags->category)) {
        // turn the array of tags into json text
        $post_tags_json = json_encode($this->tags->category);
        $this->vars->post_tags = $post_tags_json;
      }

      // turn the array of images into json text
      if ( (isset($this->media->images)) && (count($this->media->images) > 0) ) {
        $this->vars->post_image = json_encode($this->media->images);
      }
      else {
        $this->vars->post_image = NULL;
      }

      // turn the array of video into json text
      if ( (isset($this->media->video)) && (count($this->media->video) > 0) ) {
        $this->vars->post_video = json_encode($this->media->video);
      }
      else {
        $this->vars->post_video = NULL;
      }

      // turn post variables into array
      $save_data = (array) $this->vars;
      // insert data
      $result = $table->insert($save_data)->run();
    } 
    catch (Exception $e) {
      throw $e;
    }
    return $result->getInsertId();
  }*/
}
?>
