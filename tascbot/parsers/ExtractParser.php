<?php
use FastSimpleHTMLDom\Document;

class ExtractParser {

  // Class storage structure for incoming values
  public $vars = NULL;

  public $odata_item = NULL;

  // excluding the item
  public $exclude = FALSE;
  // don't exclude item
  public $exclude_override = FALSE;

  public function __construct( $rss_item, $odata_item, $extract_type='RSS' ) {
    $this->vars = new StdClass();
    // could be rss, atom or other
    $this->rss_item = $rss_item;    
    // set the odata item...stands for Other Data (embedly, full-text-rss, etc)
    $this->odata_item = $odata_item;    
    $this->extract_type = $extract_type;
  }

  public function __destruct() {
    $this->vars = NULL;
    $this->extract_type = NULL;
    $this->exclude = NULL;
    $this->exclude_override = NULL;
    $this->tags = NULL;
    $this->media = NULL;
    $this->rss_item = NULL;
    $this->odata_item = NULL;
  }

  public function exclude() { return $this->exclude; }
  public function extractType() { return $this->extract_type; }

  // ==============================================
  // Parse()
  // Breaks the passed in feed item up into it's 
  // fields for saving
  // ==============================================
  public function parse($with_tags = TRUE, $with_media = TRUE) {
    $this->media = new StdClass();
    $this->tags = new StdClass();

    $this->media->images = NULL;
    $this->media->video = NULL;

    //print_r($this->odata_item);

    // set site id
    $this->vars->site_id = $this->odata_item->site_id;
    // set feed id
    $this->vars->fid = $this->odata_item->fid;

    // set title
    $this->setTitle(); 

    // set document url
    $this->setUrl();

    // set permalink
    $this->setPermalink();

    // set post body
    $this->setContent();

    // set author
    $this->setCreator();

    // set description and post_excerpt
    $this->setDescription();

    // set encoded items
    $this->setImages();

    // set publication date
    $this->setPubDate();

    // set the thumbnail
    $this->setThumbnail();

    // set commentRSS
    // $this->setCommentRSS();

    // get the RSS comments
    $this->setTags( TRUE );

    // set the video, if there is any
    $this->setVideo();

    // set hash value 
    $this->vars->post_hash = CleanText::hash(
      $this->vars->post_url . $this->vars->post_title
    );

    // check size of post_excerpt
    //if ((is_null($this->vars->post_excerpt)) || (strlen($this->vars->post_excerpt) < 30)) {
    //  $this->exclude = TRUE;
    //}

    // add tags to the vars object structure
    if ($with_tags) {
      $this->vars->tags = $this->tags;
    }

    // add media to the vars object structure
    if ($with_media) {
      $this->vars->media = $this->media;
    }

    // put exclude attributes in fieldset
    $this->vars->exclude = $this->exclude;
    $this->vars->exclude_override = $this->exclude_override;

    return $this->vars;

  }

  // returns the images data that has been parsed
  public function getImages() {
    return $this->media->images;
  }
  // returns the video data that has been parsed
  public function getVideo() {
    return $this->media->video;
  }
  // returns the tags data that has been parsed
  public function getTags() {
    return $this->tags;
  }


  // set the author/creator
  public function setCreator() {
    // get assumed author/creator info
    if ((isset($this->odata_item->author)) && (! is_null($this->odata_item->author))) {
      if (count( array($this->odata_item->author)) > 0) {
        $this->vars->post_creator =
          json_encode( array("name"=>$this->odata_item->author,"url"=>'') );
      }
    }
    else {
      // get assumed author/creator info from RSS
      $authors = $this->rss_item->get_authors();
      if ( (!is_null($authors)) && (is_array($authors)) ) {
        // assigns an array of items, each has [name,link,email]
        $this->vars->post_creator = $authors;
      }
      else {
        $this->vars->post_creator = NULL;
      }
    }
  }

  // grab the title of the document
  public function setTitle() {
    $post_title = NULL;

    if (!is_null($this->rss_item->get_title())) {
      // create document title
      $post_title = preg_replace("/\[([^\[\]]*+|(?R))*\]/","",$this->rss_item->get_title()); 
      //$this->vars->post_title = ucwords( strtolower( CleanText::makeUTF8(trim($post_title)) ) );
      $this->vars->post_title = trim($post_title);
      // create seo_title value
      $this->vars->post_seo_title = CleanText::sanitize(trim($this->vars->post_title));
    }
    else if ((isset($this->odata_item->title)) && (! is_null($this->odata_item->title))) {
      $this->vars->post_title = trim($this->odata_item->title);
      // create seo_title value
      $this->vars->post_seo_title = CleanText::sanitize(trim($this->vars->post_title));
    }
    else {
      $this->vars->post_title = NULL;
      $this->vars->post_seo_title = NULL;
    }

    if (!empty($this->vars->post_title)) { $this->vars->post_title = htmlspecialchars_decode( htmlspecialchars_decode( $this->vars->post_title )); }
  }

  // set the post_url
  public function setUrl() {
    // get the original url if available, otherwise use link
    /*if (isset($this->rss_item->origLink)) {
      if (isset($this->rss_item->origLink->content)) {
        $this->vars->post_url = trim($this->rss_item->origLink->content);
      }
      else {
        $this->vars->post_url = trim($this->rss_item->origLink);
      }
    }*/
    if (!is_null($this->rss_item->get_permalink())) {
      $this->vars->post_url = trim($this->rss_item->get_permalink());
    }
    else if ((isset($this->odata_item->effective_url)) && (! is_null($this->odata_item->effective_url))) {
      $this->vars->post_url = trim($this->odata_item->effective_url);
    }
    else {
      $this->vars->post_url = NULL;
    }
  }

  // set the post_desc
  public function setDescription() {
    // set the description with RSS
    if (!is_null($this->rss_item->get_description())) {
      // get rid of javascript
      $excerpt = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->rss_item->get_description());
      $excerpt = trim( CleanText::makeUTF8($excerpt));
      $excerpt = html_entity_decode($excerpt,ENT_NOQUOTES,'UTF-8'); 
      $this->vars->post_excerpt = strip_tags( trim( $excerpt ) );
    }
    // set the description with the ftr object
    else if ((isset($this->odata_item->excerpt)) && (! is_null($this->odata_item->excerpt))) {
      // get rid of javascript
      $excerpt = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->odata_item->excerpt);
      $excerpt = trim( CleanText::makeUTF8($excerpt));
      $excerpt = html_entity_decode($excerpt,ENT_NOQUOTES,'UTF-8'); 
      $this->vars->post_excerpt = strip_tags( trim( $excerpt ) );
    }
    else if ((isset($this->odata_item->og_description)) && (! is_null($this->odata_item->og_description))) {
      $excerpt = trim( CleanText::makeUTF8($this->odata_item->og_description ));
      $excerpt = html_entity_decode($excerpt,ENT_NOQUOTES,'UTF-8'); 
      $this->vars->post_excerpt = strip_tags( trim( $excerpt ) );
    }
    else {
      $this->vars->post_excerpt = NULL;
    }
  }

  // set the publication date of the item
  public function setPubDate() {
    $publication_date = NULL;
    $this->vars->no_raw_pubdate = FALSE;

    if (! is_null($this->rss_item->get_date())) {
      $publication_date = $this->rss_item->get_date();
    }
    else if ((isset($this->odata_item->date)) && (! is_null($this->odata_item->date))) {
      $publication_date = $this->odata_item->date;
    }
    else {
      $publication_date = NULL;
    }

    if (! is_null($publication_date)) {
      $this->vars->post_pubdate_int = strtotime( $publication_date );
      $this->vars->post_pubdate = date("Y-m-d H:i:s",$this->vars->post_pubdate_int);
    }
    else {
      // how do you not put pubDate in your feed?
      // accomodate this foolishness by using the current date and time
      $this->vars->post_pubdate_int = strtotime("now");
      $this->vars->post_pubdate = date("Y-m-d H:i:s",$this->vars->post_pubdate_int);
      // set this so that it's known that this item had no original publication date
      $this->vars->no_raw_pubdate = TRUE;
    }

    if (! is_null($publication_date)) {
      // if, for example, the pubdate is in the future give it current date and time
      $diff = time() - $this->vars->post_pubdate_int;
      if ($diff < 0) {
        $this->vars->post_pubdate_int = strtotime("now");
        $this->vars->post_pubdate = date("Y-m-d H:i:s",$this->vars->post_pubdate_int);
      }
    }

    // make sure that everything has the right timezone info
    $datetime = new DateTime($this->vars->post_pubdate);
    $est_time = new DateTimeZone('America/New_York');
    $datetime->setTimezone($est_time);
    $this->vars->post_pubdate = $datetime->format('Y-m-d H:i:s');
  }

  // set the comment url for the post (currently not used)
  //public function setCommentRSS() {
  //  $this->vars->post_comment_url = NULL;
  //}
  
  // set the post thumbnail
  public function setThumbnail() {
    $thumbnail = NULL;

    // check to see if we have images in the array already
    if (is_null($this->media->images)) {
      $this->media->images = array();
    }

    if ((isset($this->odata_item->og_image)) && (! is_null($this->odata_item->og_image))) {
      $thumbnail = $this->odata_item->og_image;
    }
    /* we need to work on this array to use the simplepie enclosures class
    else if (! is_null($this->rss_item->get_thumbnails()))  {
      $thumbnail = $this->rss_item->get_thumbnails();
    }*/
    else {
      $thumbnail = NULL;
    }

    // add thumbnail item to image media array
    if (! is_null($thumbnail)) {
      // create article image array
      $image = array();
      $image['media_src'] = $thumbnail;
      $image['media_width'] = ((isset($thumbnail->width)) ? $thumbnail->width : 0);
      $image['media_height'] = ((isset($thumbnail->height)) ? $thumbnail->height : 0);
      $image['media_alt'] = "";
      $image['media_title'] = "";
      $this->media->images[] = $image;
    }
  }

  // set the post tags
  public function setTags( $use_all = FALSE ) {
    $this->tags->category = array();
    // set tags by using the RSS
    if (!is_null($this->rss_item->get_categories())) {
      foreach ($this->rss_item->get_categories() as $oTag) {
        // if content element is present, add tag to list
        if (isset($oTag->term)) {
          $this->tags->category[] = trim($oTag->term);
        }
      }
    }

    // if use_all OR no previous tags entered from RSS
    /*if ( ( $use_all ) || (! isset($this->tags->category))) {

      // check to see if categories is already an array
      if (! isset($this->tags->category)) { 
        $this->tags->category = array();
      }
      else if (! is_array($this->tags->category)) {
        $this->tags->category = array();
      }

      if (isset($this->odata_item->keywords)) {
        // loop over the entities
        foreach($this->odata_item->keywords as $keyword) {
          if ($keyword->score >= 7) {
            $this->tags->category[] = $keyword->name;
          }
        }
      }
      else if (isset($this->odata_item->entities)) {
        // loop over the entities
        foreach($this->odata_item->entities as $entity) {
          $this->tags->category[] = $entity->name;
        }
      }
    } */
  }

  // use the guid to set the permalink up
  public function setPermalink() {
    if (isset($this->odata_item->url)) {
      $this->vars->post_permalink = $this->odata_item->url;
    }
    else if (!is_null($this->rss_item->get_permalink())) {
      $this->vars->post_permalink = $this->rss_item->get_permalink();
    }
  }

  // handle the encoded content of the feed
  // this needs to be retooled at some point to handle grabbing images out
  // of RSS should embedly not be available
  public function setImages() {
    $body_content = $this->rss_item->get_content();
    // we want to locate iframes and grab the source, height and width
    if ((! is_null($body_content) ) && (strlen($body_content) > 0)) {
      $this->media->images = DOMSearch::images($body_content);
    }
    else {
      $this->media->images = NULL;
    }
    /*if (isset($this->odata_item->images)) {
      // grab images
      $this->media->images = array();

      // loop over images
      foreach ($this->odata_item->images as $image) { 
        //print_r($image);
        if (!isset($image->caption)) { $image->caption = ""; }
        // add each to image array
        array_push($this->media->images,
            array("embed_type"=>"img",
                  "media_src"=>$image->url,
                  "media_alt"=>$image->caption,
                  "media_width"=>$image->width,
                  "media_height"=>$image->height,
                  "media_title"=>$image->caption,
                  "media_type"=>"image")
        );
      }
    }*/
    // grab videos
    $this->media->video = NULL;
  }

  // set the post_body
  // we could setup the YQL fetch here or outside in the crawler
  public function setContent() {
    if ((isset($this->odata_item->content)) && (! is_null($this->odata_item->content))) {
      $body = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->odata_item->content);
      $body = trim( CleanText::makeUTF8($body));
      $body = html_entity_decode($body,ENT_NOQUOTES,'UTF-8'); 
      $this->vars->post_body = strip_tags( trim( $body ),'<p>' );
      //$this->vars->post_body = trim(CleanText::makeUTF8( $body ));
      //$this->vars->post_body = $this->odata_item->content;
    }
    else if (! is_null($this->rss_item->get_content())) {
      $body = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->rss_item->get_content());
      $body = trim( CleanText::makeUTF8($body));
      $body = html_entity_decode($body,ENT_NOQUOTES,'UTF-8'); 
      $this->vars->post_body = strip_tags( trim( $body ),'<p>' );
      //$this->vars->post_body = $this->rss_item->encoded;
      /* // grab images
      $this->media->images = DOMSearch::images($this->vars->post_encoded);
      // grab videos
      $this->media->video = DOMSearch::video($this->vars->post_encoded);
      */
    }
    else {
      $this->vars->post_body = NULL;
    }
  }

  // locates video in the parsed data and returns an array
  public function setVideo() {
    $body_content = $this->rss_item->get_content();
    // we want to locate iframes and grab the source, height and width
    if ((! is_null($body_content) ) && (strlen($body_content) > 0)) {
      $this->media->video = DOMSearch::video($body_content); 
    }
    else {
      $this->media->video = NULL;
    }
  }
}
?>
