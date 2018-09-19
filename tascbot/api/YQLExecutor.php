<?php 

class YQLExecutor {
  private $config = NULL;
  private $yql_stmt = NULL;
  private $yql_querystring = NULL;
  private $yql_url_root = NULL;

  public function __construct($config=null) {
    // set the yql querystring
    $this->yql_querystring = $this->queryDefaults();

    $this->config = $config;

    if (is_null($this->config)) {
      $this->config = new StdClass();
      $this->config->yql_method = 'GET';
      $this->config->yql_request_type = 'public';
      $this->config->yql_options = array('format' => 'json');
    }

    // check for passed in config
    if ((!is_null($this->config)) && (is_object($this->config))) {
      if (isset($this->config->yql_method)) {
        if (strcasecmp($this->config->yql_method,'post') == 0) {
          $this->yql_method = "POST";
        }
        else {
          $this->yql_method = "GET";
        }
      }
      if (isset($this->config->yql_request_type)) {
        if (strcasecmp($this->config->yql_request_type,'private') == 0) {
          $this->yql_url_root = "";
        }
        else {
          $this->yql_url_root = 'http://query.yahooapis.com/v1/public/yql?';
        }
      }
      if (isset($this->config->yql_options)) {
        foreach ($this->config->yql_options as $key => $value) {
          $this->yql_querystring[$key] = urlencode(trim($value));
        }
      }
    }
    else {
      throw new Exception('The config parameter is not an object');
    }

    return $this;
  }

  public function yql($query) {
    //$this->yql_query = urlencode(trim($query));
    $this->yql_query = trim($query);
    $this->yql_querystring['q'] = $this->yql_query; 
    return $this;
  }

  public function run($return_array=FALSE,$options=null) {
    $results = NULL;
    $post_fields = NULL;

    // reconcile additional options
    if (!is_null($options)) {
      foreach ($options as $key => $value) {
        $this->yql_querystring[$key] = urlencode(trim($value));
      }
    }

    if (strcasecmp($this->config->yql_method,"post") == 0) {
      // setup request url
      $request_url = $this->yql_url_root; 
      $post_fields = http_build_query($this->yql_querystring,'PHP_QUERY_RFC3986');
    }
    else {
      // setup request url
      $request_url = $this->yql_url_root . http_build_query($this->yql_querystring);
    }

    //print_r($request_url);

    // setup CURL Parameters
    $session = curl_init($request_url);

    if (strcasecmp($this->config->yql_method,"post") == 0) {
      curl_setopt($session, CURLOPT_POST, 1);
      curl_setopt($session, CURLOPT_POSTFIELDS, $post_fields);
      curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);  
      curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
    }

    curl_setopt($session,CURLOPT_RETURNTRANSFER,true);

    $contents = $json = curl_exec($session);
   
    $data = json_decode($contents,$return_array);

    // check the return
    if (!is_null($data)) {
      if ($return_array) {
        if(!is_null($data['query']['results'])) {
          if (array_key_exists('error',$data['query']['results'])) {
            $results = new StdClass(); $results->success = FALSE; $results->contents = $data;
          }
          else {
            $results = new StdClass(); $results->success = TRUE; $results->contents = $data;
          }
        }
      }
      else if (isset($data->error)) {
        $results = new StdClass(); $results->success = FALSE; $results->contents = $data;
      }
      else {
        if(!is_null($data->query->results)) {
          if (isset($data->query->results->error)) {
            $results = new StdClass(); $results->success = FALSE; $results->contents = $data;
          }
          else {
            $results = new StdClass(); $results->success = TRUE; $results->contents = $data;
          }
        }
      }
    }
    else {
      $results = new StdClass(); $results->success = FALSE; $results->contents = null;
    }
    
    //print_r($results);
    return $results;
  }

  //===================================================================
  // PRIVATE CLASS METHODS
  //===================================================================
  private function queryDefaults() {
    $defaults = array();
    $defaults['format'] = 'json';
    return $defaults;
  }
}
