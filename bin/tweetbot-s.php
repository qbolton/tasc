#!/usr/bin/php

<?php
// set default timezone
date_default_timezone_set('America/New_York'); 

define('TASCBOT_ROOT',dirname(__FILE__));
define('NUM_RUNS_PER_DAY',12);

/* ======================================================
 * DEFINE VENDOR and CUSTOM FILES TO INCLUDE 
 * ====================================================== */
require_once TASCBOT_ROOT . '/../vendor/autoload.php';
require TASCBOT_ROOT . '/../config/database.php';
require TASCBOT_ROOT . '/../bin/bot-config.php';

/* ======================================================
 * DEFINE VENDOR, CUSTOM CLASSES OR CONFIGS TO USE
 * ====================================================== */
use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/* ======================================================
 * DEFINE THE TASCBOT CLASS
 * ====================================================== */

class TweetBot {
  public function __construct( $mode='INTERVAL' ) {
    $this->script = new StdClass();
    $this->script->mode = strtoupper($mode);
    $this->setLogger();
  }

  // Function startup
  // ----------------------------------------
  // Initializes services and features that the script needs to 
  // run properly
  public function startup( $site_name = NULL ) {
    $this->logger->info(">>>> TWEETBOT Startup");

    // if not null, set the site name and verify that site exists
    if (! empty($site_name) ) {
      $this->site_name = strtolower($site_name);
      // verify site name exists
      print_r($this->site_name);
      $verify_site = DB::select("SELECT blog_id, site_id, domain, path FROM wp_blogs WHERE path = '/{$this->site_name}/'");
      if (count($verify_site) == 1) {
        $this->logger->info(">>>> {$this->site_name} VERIFIED as valid site");
        $this->logger->info(">>>> RUNNING IN SINGLE SITE MODE");
        $this->single_site_mode = TRUE;
        $this->site_id = $verify_site[0]->blog_id;
      }
      else {
        $this->logger->error(">>>> {$this->site_name} NOT VERIFIED as valid site");
        exit(0);
      }
    }
    else {
      $this->single_site_mode = FALSE;
      $this->logger->info(">>>> RUNNING IN ALL SITE MODE");
    }

    $site_list = DB::select("SELECT blog_id, site_id, domain, path FROM wp_blogs WHERE blog_id > 1");

    $dm = DB::select("SELECT blog_id, domain FROM wp_domain_mapping WHERE active = 1");
    foreach ($dm as $d) {
      $this->domains[$d->blog_id] = $d->domain;
    }

    $mp = DB::select("SELECT * FROM tasc_tt_mapping");
    foreach ($mp as $m) {
      $this->mapping[$m->site_id] = $m;
    }

    // track the number of times this script has run
    $this->trackRunCount();
  }

  // Function run
  // ----------------------------------------
  // The run function directs the aggregation of content from the sources stored in the database.  
  // In addition to content gathering, this function applies modules, filters and parameters to 
  // the data gathered.
  public function run() {
    global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
    global $_SERVER;

    require(BASE_PATH . 'wp-load.php');
    require_once(BASE_PATH . 'wp-includes/user.php');
    require_once(BASE_PATH . 'wp-admin/includes/post.php');

    $return_output = array(); $return_code = 0;
    // check the run mode
    if ($this->single_site_mode) {
      $site_maps = DB::select("SELECT * FROM {$this->script->maptable} WHERE site_id = {$this->site_id}");
    }
    else {
      $site_maps = DB::select("SELECT * FROM {$this->script->maptable}");
    }
  
    // ########################################
    // loop over the site maps
    // ########################################
    foreach ($site_maps as $site) {
      $this->logger->info(">>>> Fetching posts to send to social media for {$this->domains[$site->site_id]}");
      // grab the posts for the next site
      $posts = DB::select("SELECT * FROM {$this->script->poststable} WHERE status = 'pending' AND site_id = {$site->site_id}");
      // figure out number to tweet based on NUM_RUNS_PER_DAY runs a day
      $total_num_posts = count($posts);
      $num_runs_left = (NUM_RUNS_PER_DAY - $this->run_count);

      if ($num_runs_left == 0) {
        $raw_posts_to_post = $total_num_posts;
      }
      else {
        $raw_posts_to_post = round($total_num_posts / $num_runs_left);
      }

      if ($raw_posts_to_post == 0) { 
        $posts_to_post = 0;
      }
      else if ($raw_posts_to_post < 1) { 
        $posts_to_post = 1; 
      }
      else {
        $posts_to_post = $raw_posts_to_post;
      }

      $this->logger->info(">>>> Based on a total number of {$total_num_posts} posts available as pending");

      if ($posts_to_post > 0) {
        $this->logger->info(">>>> We are going to update social profiles with {$posts_to_post} posts");
        // switch to blog to process properly
        switch_to_blog($site->site_id);

        // loop over array to process
        foreach ( array_slice($posts,0,$posts_to_post) as $p) {
          // use wordpress functions to grab post
          $wp_post = get_post($p->post_id); //print_r($wp_post);

          // ===========================================
          // build mapped url from wp guid 
          // ===========================================

          // if site is dietposts
          if ($site->site_id == 4) {
            $wp_post->oUrl = $this->makeOurl($wp_post->guid,$this->domains[$site->site_id]);
            print_r($wp_post->oUrl); exit;
            //$wp_post->oUrl = str_ireplace('dev.elombre.com',$this->domains[$site->site_id],$wp_post->guid);
          }
          else {
            $wp_post->oUrl = "http://" . $this->domains[$site->site_id] . "/" . $wp_post->post_name . "/"; 
          }

          // ===========================================

          // post to twitter
          if (stristr($site->enabled_profiles,'twitter')) {
            $twitter_result = $this->sendToTwitter($this->mapping[$site->site_id], $site->site_id, $p, $wp_post);
          }
          else {
            $this->logger->info(">>>> {$this->domains[$site->site_id]} does not have twitter enabled");
          }

          // post to facebook
          if (stristr($site->enabled_profiles,'facebook')) {
            $facebook_result = $this->sendToFacebook($this->mapping[$site->site_id], $site->site_id, $p, $wp_post);
          }
          else {
            $this->logger->info(">>>> {$this->domains[$site->site_id]} does not have facebook enabled");
          }

          // update as sent regardless.  Due to the temporal nature of social media posts, we probably won't try to 
          // post older stuff unless we are updating the script
          //DB::table($this->script->poststable)->where('post_id',$p->post_id)->update(['status'=>'sent','last_run'=>DB::raw('NOW()')]);

        }
      }
      else {
        $this->logger->info(">>>> We are NOT going to post anything at this time");
      }
    }
  }

  // Function makeOurl
  // ----------------------------------------
  // This function takes a wordpress guid and turns into a mapped url
  //
  public function makeOurl($guid,$domain) {
    $url_parts = explode("/",$guid);
    // remove parts of the array
    unset($url_parts[count($url_parts)-1]);
    unset($url_parts[1],$url_parts[3]);
    // put the resulting parts back together separated by /
    // and add in the new portions
    $oUrl = str_ireplace('dev.elombre.com',"/{$domain}",implode('/',$url_parts));
    return $oUrl;
  }

  // Function sendToTwitter
  // ----------------------------------------
  // 
  // 
  public function sendToTwitter($api,$site_id,$post,$wp_post) {
    $retval = 0;
    //print_r($post); 
    //print_r($api);
    //print_r($this->domains);

    // use wordpress functions to grab images/media

    // setup Twitter API call
    $twitter = new \TwitterPhp\RestApi(
      $api->consumerKey,
      $api->consumerSecret,
      $api->accessToken,
      $api->accessTokenSecret
    );

    $connectionUser = $twitter->connectAsUser();

    // setup status buffer
    $status_string = 
      html_entity_decode($wp_post->post_title) . " " . $wp_post->oUrl;

    // post to twitter
    $cu = $connectionUser->post('statuses/update',array('status' => $status_string)); //print_r($cu);
  
    // if errors
    if ( (is_array($cu)) && (isset($cu['errors']) ) ) {
      // loop over them and log the stuff
      foreach ($cu['errors'] as $error) {
        // log the message
        $this->logger->error(">>>> TwitterPHP returned an error: " . $error['message']);
        $retval = $error['code'];
      }  
    }
    return $retval;
  }

  // Function sendToFacebook
  // ----------------------------------------
  // 
  // 
  public function sendToFacebook($api,$site_id,$post,$wp_post) {
    $retval = 0;

    //print_r($api); 
    // grab the facebook_id from the api object
    $page_id = $api->facebook_id; 
    $fb_post_url = "{$page_id}/feed"; 
    $fb_page_access_url = "{$page_id}?fields=access_token";
    $link_data = [ 'link' => $wp_post->oUrl ];

    // Quentin's info
    /*
    $q_access_token = 'EAADDAEcG8UQBAN8aZCYoyA1E5llOyxZCmJmZCnOyS3ep3VOk6wGniq2CAAdrRCvwUb1LkerNBGz6J7YAUmOHMjDIEMzSFhwMe8fne6LcCFNXZC0WGC6IuBMkdCBJ0ZArptEjdS5B6Ih1eJsvHzJWLSa4Ig5AAANKnnZBZBI44yN6IfdEE6K6EQ2';

    $fb = new \Facebook\Facebook([
      'app_id' => '214405959053636', 'app_secret' => '50b69a7acbfecde238c5a41eed0f15b5', 'default_graph_version' => 'v2.9'
    ]);
    try {
      $response = $fb->get('/me','EAADDAEcG8UQBAN8aZCYoyA1E5llOyxZCmJmZCnOyS3ep3VOk6wGniq2CAAdrRCvwUb1LkerNBGz6J7YAUmOHMjDIEMzSFhwMe8fne6LcCFNXZC0WGC6IuBMkdCBJ0ZArptEjdS5B6Ih1eJsvHzJWLSa4Ig5AAANKnnZBZBI44yN6IfdEE6K6EQ2');
    }
    catch(Facebook\Exceptions\FacebookResponseException $e) {
      echo 'Graph returned an error: ' . $e->getMessage();
      exit;
    }
    catch(Facebook\Exceptions\FacebookSDKException $e) {
      echo 'Facebook SDK returned an error: ' . $e->getMessage();
      exit;
    }*/

    // Bob's info
    $bob_access_token =
    'EAAeaLZCvWTUYBAPz5T9e4vAbdycyIPROurjsOF9fTIhZC4UAbiKjVSpGAPBzrsWcYMqewGQ4mWPtt660itsdKXDXhBAVNICEzKvy9UTqvIXdbBe92AGfEJ4ZCMTCBW4eWaumHTtlh8SZBZA8ZCD9qy3YOShjw8hYYZD';

    $fb = new \Facebook\Facebook([
      'app_id' => '2139855716240710', 'app_secret' => 'e8fd639c8d45b09f4cbc7eaede0ab132', 'default_graph_version' => 'v2.9'
    ]);

    try {
      // get the page access token
      $response = $fb->get($fb_page_access_url,$bob_access_token);
      // pull graph node
      $graphNode = $response->getGraphNode(); 
      $page_access_token = $graphNode->getProperty('access_token');
      $page_id = $graphNode->getProperty('id');

      // post the link
      $response = $fb->post($fb_post_url, $link_data, $page_access_token);
    }
    catch(Facebook\Exceptions\FacebookResponseException $e) {
      $this->logger->error(">>>> Graph returned an error: " . $e->getMessage());
      $retval = $e->getCode();
    }
    catch(Facebook\Exceptions\FacebookSDKException $e) {
      $this->logger->error(">>>> Facebook SDK returned an error: " . $e->getMessage());
      $retval = $e->getCode();
    }

    return $retval;
  }

  // Function isReady
  // ----------------------------------------
  // Returns true if tascbot-exec is ready to work.  The function initiates a variety of checks
  // to make sure that everything the script needs is in place.
  public function isReady() {

    $this->script->poststable = 'tasc_tt_posts';
    $this->script->maptable = 'tasc_tt_mapping';

    // check to see if the job table exists
    if ( ! DB::schema()->hasTable($this->script->poststable)) { return FALSE; }
    if ( ! DB::schema()->hasTable($this->script->maptable)) { return FALSE; }

    return TRUE;
  }

  public function setLogger($logger=NULL) { 
    if (empty($logger)) {
      $logfile = TASCBOT_ROOT . '/../logs/' . 'tweetbot.log';
      $tweetbot_logger = new Logger('tweetbot_logger');
      $tweetbot_logger->pushHandler(new StreamHandler('php://stdout',Logger::DEBUG));
      $tweetbot_logger->pushHandler(new StreamHandler($logfile,Logger::ERROR));
      $this->logger = $tweetbot_logger;
    }
    else {
      $this->logger = $logger; 
    }
  }

  public function trackRunCount() {
    $filename = TASCBOT_ROOT . '/../logs/' . 'tweetbot.run';
    $this->logger->info(">>>> Updating tweetbot run in {$filename}");
    $run_count = file_get_contents($filename);
    if ($run_count === FALSE) {
      $this->logger->warn("We couldn't get the contents of {$filename}");
      $run_count = 1;
    }
    else {
      $run_count = (int) $run_count;
      if ($run_count == NUM_RUNS_PER_DAY) {
        $run_count = 1;
      }
      else {
        $run_count++;
      }
    }
    //file_put_contents($filename,(string) $run_count);
    $this->logger->info(">>>> Tweetbot runs today: {$run_count}");
    $this->run_count = $run_count;
  }

  public function shutdown() { return 0; }
}

/* ======================================================
 * SETUP THE SCRIPT TO RUN
 * ====================================================== */

//echo implode( ' ', $wpdb->tables() ) . "\n"; exit;
$tasc_cmd = new Commando\Command();
// This determines the script run mode
$tasc_cmd->option('m')->
  aka('mode')->
  describedAs('This is the run mode: clean, all, interval')->
  must(function($mode) {
    return in_array($mode,array('clean','all','interval'));
  });
// setup the limit flag.  This will determine how many jobs are run
$tasc_cmd->option('s')->
  aka('site')->
  describedAs('The name of the site you want to run the bot for')->
  must(function($site) {
    return ( (is_string($site)) );
  });

if (! empty($tasc_cmd['mode']) ) {
  $mode = $tasc_cmd['mode']; 
}
else {
  $mode = 'interval';
}

if (! empty($tasc_cmd['site']) ) {
  $site_name = $tasc_cmd['site']; 
}
else {
  $site_name = NULL;
}

$tbe = new TweetBot( $mode );

if ($tbe->isReady()) {
  $tbe->startup( $site_name );
  $tbe->run();
  $tbe->shutdown();
}
else {
  print "We aren't ready to run\n";
  $tbe->shutdown();
}
