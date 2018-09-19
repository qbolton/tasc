<?php

//use \StdClass;

// =============================================
// Class Fetch
// This is a static helper class that contains 
// basic methods for retrieving files, urls and
// other content from remote locations
// =============================================
class Fetch {
  // ========================================================
  // Function withYQL
  // Handles the yahoo query language GET call to pull in the
  // output of a query
  // ========================================================
  static public function withYQL($query,$options=NULL) {
    $yahoo = NULL;
    $yql_query = NULL;
    // setup yql directives
    $yql = new StdClass();
    $yql->yql_method = 'GET';
    $yql->yql_request_type = 'public';
    $yql->yql_options = array('format'=>'json');

    // setup actual yql query
    $yql_query = $query;

    $yahoo = new YQLExecutor($yql);
    // issue query
    $yahoo->yql($yql_query);
    // pull the results via YQL
    $json = $yahoo->run(); //print_r($json);

    return $json;
  }

  // ========================================================
  // Function withCurl
  // Fetches the given url with the parameters given.  Supports
  // HTTP GET and POST
  // ========================================================
  static public function withCurl($method,$url,$postdata=NULL) {
    $retval = new StdClass();
    
    // Process a curl GET request
    if (strcasecmp($method,'GET') == 0) {
      $ch = curl_init();
      $timeout = 5;

      curl_setopt($ch,CURLOPT_URL,$url);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);

      $result = curl_exec($ch);

      $retval->contents = $result;
      $retval->message = NULL;
      $retval->status = TRUE;
    }
    // Process a curl POST request
    else if (strcasecmp($method,'POST') == 0) {
      $field_string = '';
      // flow over post data and urlencode
      foreach($postdata as $key=>$value) {
        // url-ify the data for the POST
        $field_string .= $key . '=' . urlencode($value) . '&';
      }
      // cut off latent '&'
      rtrim($field_string,'&');

      // open connection
      $ch = curl_init();
      //set the url, number of POST vars, POST data
      curl_setopt($ch,CURLOPT_URL, $url);
      curl_setopt($ch,CURLOPT_POST, count($postdata));
      curl_setopt($ch,CURLOPT_POSTFIELDS, $field_string);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      // execute post
      $result = curl_exec($ch);

      $retval->contents = $result;
      $retval->message = NULL;
      $retval->status = TRUE;
    }
    else {
      $retval->status = FALSE;
      $retval->message = 'Invalid HTTP method ('. $method . ') passed into method';
      $retval->contents = NULL;
    }

    // Handle return/error codes, if necessary
    if ($retval->status) {
      // error code
      $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
      // get the mime type
      $mime_type = curl_getinfo($ch,CURLINFO_CONTENT_TYPE);

      if ($http_code != 200) {
        $retval->status = FALSE;
        $retval->http_code = $http_code;
        $retval->mime_type = $mime_type;
      }
      curl_close($ch);
    }

    return $retval;
  }
  
  // ========================================================
  // Function sharedCount
  // shareCount returns the number of times that a particular
  // url has been shared across a number of social media
  // sites such as 'google+', 'facebook', 'twitter', etc
  // ========================================================
  static public function sharedCount($url,$options=NULL) {
    // set the API key
    $share_API_key = "c1dd9c0fcc85f0b9ddf45a7ab6c4876b4e99cc94";
    // grab the data
    $json = file_get_contents("http://free.sharedcount.com/?url=" . rawurlencode($url) . "&apikey={$share_API_key}");
    // decode the resulting json
    $counts = json_decode($json, true);
    // return the count data
    return $counts;
  }
}
