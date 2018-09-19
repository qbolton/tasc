<?php

//$url = "http://dev.elombre.com/allgolfingposts/the-morgan-cup-returns-in-2017/post-image-10-png/";
$url = "http://dev.elombre.com/flowerposts/2016/11/25/inspiration-for-mosaic-paving/";
print_r(explode("/",$url));
print_r("\n");
$url_parts = explode("/",$url);
unset($url_parts[count($url_parts)-1]);
unset($url_parts[1],$url_parts[3]); print_r($url_parts);
print_r("\n");
print_r(implode('/',$url_parts));
print_r("\n");
print_r(str_ireplace('dev.elombre.com','/newdomain.com',implode('/',$url_parts)));
print_r("\n");

$p = 2.12;
print_r(round($p));

$t=NULL;
if (stristr($t,'test')) 
  print "yep";
else
  print "there ain't nothing in there";

