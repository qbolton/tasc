<?php

// ================================================================
// setup up the tascbot logger
// ================================================================
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create the logger
$tascbot_logger = new Logger('tascbot_logger');
// add some handlers
// $logfile = TASCBOT_ROOT . '/../logs/' . 'tascbot-' . $argv[1] . '.log';
// $logger->pushHandler(new StreamHandler($logfile,Logger::DEBUG));
//$tascbot_logger->pushHandler(new StreamHandler('php://stdout',Logger::WARNING));
$tascbot_logger->pushHandler(new StreamHandler('php://stdout',Logger::ERROR));

// ================================================================
// setup wordpress paths in order to load the right environment
// ================================================================
function find_wordpress_base_path() {
  $dir = dirname(__FILE__);
  do {
   //it is possible to check for other files here
   if( file_exists($dir."/wp-config.php") ) {
    return $dir;
   }
  } while( $dir = realpath("$dir/..") );
  return null;
}

if( php_sapi_name() !== 'cli' ) {
  die("This script was meant to be run from command line");
}

define( 'BASE_PATH', find_wordpress_base_path()."/" );
define('WP_USE_THEMES', false);

$_SERVER['HTTP_HOST'] = 'dev.elombre.com';
$_SERVER['REQUEST_URI'] = '/';
