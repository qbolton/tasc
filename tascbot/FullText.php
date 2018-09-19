<?php
class FullText {
  // =====================================================
  // Function: getMetaTitle
  // This function retrieves the best document title from 
  // the given HTML string.
  // =====================================================
  static public function getMetaTitle($html) {
    $output = new StdClass();
    $xpath_query = NULL;

    // create dom document
    $dom = new DomDocument();
    // load the html
    $dom->loadHTML($html);
    // prepare for xpath queries
    $xpath = new DOMXPath($dom);

    // HTML title from head section
    $results = $xpath->query("//title");
    foreach($results as $obj) {
      $output->head_title = $obj->childNodes->item(0)->nodeValue;
    }

    // HTML meta title from itemprop
    $results = $xpath->query("//meta[@itemprop='name']");
    foreach($results as $obj) {
      $output->itemprop_name = $obj->item(0)->getAttribute('content');
    }

    // twitter title
    $results = $xpath->query("//meta[@name='twitter:title']");
    foreach($results as $obj) {
      $output->twitter_title = $obj->item(0)->getAttribute('content');
    }

    // open graph title
    $results = $xpath->query("//meta[@property='og:title']");
    foreach($results as $obj) {
      $output->og_title = $obj->item(0)->getAttribute('content');
    }

    return $output;
  }

  // =====================================================
  // Function: getMetaDesc
  // This function retrieves the best document description 
  // from the given HTML string.
  // =====================================================
  static public function getMetaDesc($html) {
    $output = new StdClass();
    $xpath_query = NULL;

    // create dom document
    $dom = new DomDocument();
    // load the html
    $dom->loadHTML($html);
    // prepare for xpath queries
    $xpath = new DOMXPath($dom);

    // <meta name="description" content="Page description. No longer than 155 characters." />
    $results = $xpath->query("//meta[@name='description']");
    foreach($results as $obj) {
      $output->head_desc = $obj->item(0)->getAttribute('content');
    }

    // <meta itemprop="description" content="This is the page description">
    $results = $xpath->query("//meta[@itemprop='description']");
    foreach($results as $obj) {
      $output->itemprop_desc = $obj->item(0)->getAttribute('content');
    }

    // <meta name="twitter:description" content="Page description less than 200 characters">
    $results = $xpath->query("//meta[@name='twitter:description']");
    foreach($results as $obj) {
      $output->twitter_desc = $obj->item(0)->getAttribute('content');
    }

    // <meta property="og:description" content="Description Here" />
    $results = $xpath->query("//meta[@property='og:description']");
    foreach($results as $obj) {
      $output->og_desc = $obj->item(0)->getAttribute('content');
    }

    return $output; 
  }

  static public function getMetaImage($html) {
    $output = new StdClass();
    $xpath_query = NULL;

    // create dom document
    $dom = new DomDocument();
    // load the html
    $dom->loadHTML($html);
    // prepare for xpath queries
    $xpath = new DOMXPath($dom);

    //<meta itemprop="image" content=" http://www.example.com/image.jpg">
    $results = $xpath->query("//meta[@itemprop='image']");
    foreach($results as $obj) {
      $output->itemprop_image = $obj->item(0)->getAttribute('content');
    }

    // og image properties
    $results = $xpath->query("//meta[@property='og:image']");
    foreach($results as $obj) {
      $output->og_image = $obj->item(0)->getAttribute('content');
    }

    // no og:image check for og:image:url
    if (!isset($output->og_image)) {
      $results = $xpath->query("//meta[@property='og:image:url']");
      foreach($results as $obj) {
        $output->og_image = $obj->item(0)->getAttribute('content');
      }
    }

    return $output; 
  }

  static public function getMetaAuthor($html) {}
  static public function getMetaVideo($html) {}
}
