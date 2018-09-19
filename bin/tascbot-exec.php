#!/usr/bin/php

<?php
// set default timezone
date_default_timezone_set('America/New_York'); 

define('TASCBOT_ROOT',dirname(__FILE__));
define('MAX_BOTS_TO_RUN',4);

/* ======================================================
 * DEFINE VENDOR and CUSTOM FILES TO INCLUDE 
 * ====================================================== */
require_once TASCBOT_ROOT . '/../vendor/autoload.php';
require TASCBOT_ROOT . '/../config/database.php';

/* ======================================================
 * DEFINE VENDOR, CUSTOM CLASSES OR CONFIGS TO USE
 * ====================================================== */
use Illuminate\Database\Capsule\Manager as DB;

/* ======================================================
 * DEFINE THE TASCBOT CLASS
 * ====================================================== */

class TascBotExec {
  public function __construct( $mode='FG' ) {
    $this->script = new StdClass();
    $this->script->mode = strtoupper($mode);
    $this->true_limit = 0;
  }

  // Function startup
  // ----------------------------------------
  // Initializes services and features that the script needs to 
  // run properly
  public function startup( $job_limit = 0 ) {
    $limit_stmt = "";

    // set up limit directive
    if ($job_limit == 0) {
      $limit_stmt = "";
    }
    else if ($this->true_limit > 0) {
      $limit_stmt = " LIMIT {$this->true_limit}";
    }
    else {
      $limit_stmt = " LIMIT {$job_limit}";
    }

    // get job list
    $results = DB::select(
      "SELECT job_id,job_name,job_status,job_exec_status,job_exec_date,job_modules,job_logfile,job_params" .
      " FROM {$this->script->jobtable} WHERE job_status = 'active' AND job_name LIKE '%tascbot%'" . 
      " ORDER BY job_exec_date ASC" . $limit_stmt
    );
     
    // loop over the jobs that came in
    if (count($results) > 0) {
      //print_r($results); exit;
      $this->script->jobs = $results;
    }
    else {
      echo "NO TASCBOT JOBS TO RUN\n";
      exit; 
    }
  }

  // Function run
  // ----------------------------------------
  // The run function directs the aggregation of content from the sources stored in the database.  
  // In addition to content gathering, this function applies modules, filters and parameters to 
  // the data gathered.
  public function run( $job_id = 0 ) {
    $return_output = array(); $return_code = 0;

    foreach($this->script->jobs as $job) {
      echo ">>> EXECUTING: {$job->job_name}" . "\n";
      $job_name = substr($job->job_name,0,strpos($job->job_name,'-'));
      $cmd_string = TASCBOT_ROOT . "/tascbot.php {$job_name}";

      try {
        if (strcasecmp($this->script->mode,'FG')==0) {
          //print_r($cmd_string . "\n");
          flush();
          exec($cmd_string,$return_output,$return_code);
          print_r($return_output) ;
          flush();
        }
        else {
          // As long as any and all script output is directed to another file or stream, the system command will
          // not wait for the process to finish before exiting.  This has been tested and works
          $cmd_string .= " >/dev/null 2>&1 &"; 
          system("nohup " . $cmd_string);
        }
      }
      catch (Exception $e) {
        echo $e->getMessage();
        var_dump($e);
        exit($e->getCode());
      }
    }
  }

  // Function isReady
  // ----------------------------------------
  // Returns true if tascbot-exec is ready to work.  The function initiates a variety of checks
  // to make sure that everything the script needs is in place.
  public function isReady() {
    //
    //print_r($results);
    //
    $this->script->jobtable = 'tasc_site_jobs';

    // check to see if the job table exists
    if ( ! DB::schema()->hasTable($this->script->jobtable)) { return FALSE; }

    // check to see if there are tascbots running
    $return_output = NULL; $return_code = 0;
    // this returns the number of process running
    exec("pgrep tascbot.php",$return_output,$return_code);
    if (! empty($return_output) ) {
      $true_limit = MAX_BOTS_TO_RUN - count($return_output);
      if ($true_limit <= 0) {
        print_r($return_output);
        return FALSE;
      }
      else if ($true_limit == MAX_BOTS_TO_RUN) {
        print "Ready to run standard number of bots\n";
      }
      else {
        $this->true_limit = $true_limit;
        print "Revising number of bots to kickoff.  True limit is now {$this->true_limit}\n";
      }
    }
    return TRUE;
  }

  public function setLogger($logger) { $this->logger = $logger; }
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
  describedAs('This is the run mode: Foreground(FG) or Background(BG)')->
  must(function($mode) {
    return in_array($mode,array('fg','bg','FG','BG'));
  });
// setup the limit flag.  This will determine how many jobs are run
$tasc_cmd->option('l')->
  aka('limit')->
  describedAs('The value set here will determine how many jobs are kicked off by tascbot-exec')->
  must(function($limit) {
    return ( (is_integer($limit)) && ($limit < MAX_BOTS_TO_RUN) );
  });

if (! empty($tasc_cmd['mode']) ) {
  $mode = $tasc_cmd['mode']; 
}
else {
  $mode = 'BG';
}

if (! empty($tasc_cmd['limit']) ) {
  $job_limit = $tasc_cmd['limit']; 
}
else {
  $job_limit = MAX_BOTS_TO_RUN;
}

$tbe = new TascBotExec( $mode );

if ($tbe->isReady()) {
  $tbe->startup( $job_limit );
  $tbe->run();
  $tbe->shutdown();
}
else {
  print "We aren't ready to run\n";
  $tbe->shutdown();
}
