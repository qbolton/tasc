#!/usr/bin/php

<?php
// set default timezone
date_default_timezone_set('America/New_York'); 

define('TASC_ROOT',dirname(__FILE__));
define('NUM_RUNS_PER_DAY',12);

/* ======================================================
 * DEFINE VENDOR and CUSTOM FILES TO INCLUDE 
 * ====================================================== */
require_once TASC_ROOT . '/../vendor/autoload.php';
require TASC_ROOT . '/../config/database.php';
require TASC_ROOT . '/../bin/bot-config.php';

/* ======================================================
 * DEFINE VENDOR, CUSTOM CLASSES OR CONFIGS TO USE
 * ====================================================== */
use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/* ======================================================
 * DEFINE THE JANITORBOT CLASS
 * ====================================================== */

class JaniBot {
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

    // grab the modules available to run

    // track the number of times this script has run
    $this->trackRunCount();
  }

  // Function run
  // ----------------------------------------
  // The run function controls the execution of janitor modules
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
      // switch to blog to process properly
      switch_to_blog($site->site_id);
    }
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
      $logfile = TASC_ROOT . '/../logs/' . 'tweetbot.log';
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
    $filename = TASC_ROOT . '/../logs/' . 'tweetbot.run';
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
    file_put_contents($filename,(string) $run_count);
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

$tbe = new JaniBot( $mode );

if ($tbe->isReady()) {
  $tbe->startup( $site_name );
  $tbe->run();
  $tbe->shutdown();
}
else {
  print "We aren't ready to run\n";
  $tbe->shutdown();
}
