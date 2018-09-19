<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$capsule = new Capsule();

$capsule->addConnection([
'driver'    => 'mysql',
'host'      => 'localhost',
'database'  => 'elombre',
'username'  => 'dbuser',
'password'  => 'Venus8799$',
'charset'   => 'utf8',
'collation' => 'utf8_general_ci'
]);
/*$capsule->addConnection([
'driver'    => 'mysql',
'host'      => 'localhost',
'database'  => 'elombre',
'username'  => 'dbuser',
'password'  => 'Venus8799$',
'charset'   => 'utf8',
'prefix'    => 'prefix_'
]);*/
$capsule->setAsGlobal();
$capsule->bootEloquent();

date_default_timezone_set('UTC');
