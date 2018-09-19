<?php
// =============================================
// Class DOMSearch
// This is a static helper class that defines
// functions for grabbing certain pieces of data
// out of field elements.
// =============================================
class DOMSearch {

  // gets any images from a content:encoded tag within RSS 2.0 feed
  static public function images($content) {
    // array of image names that should be excluded
    $exclude_image_names = array(
      'spacer',
      'share',
      '.trans.',
      '.trans_'
    );

    // array of images
    $image_array = array();

    $html = new FastSimpleHTMLDom\Document();
    // load the content
    $html->loadHtml($content);

    // find img tags
    $images = $html->find('img');

    // if images then begin the work
    foreach($images as $img) {
      $exclude_image = FALSE;

      // setup image attributes
      $src = $img->src;
      $alt = (isset($img->alt)) ? $img->alt : "";
      $width = (isset($img->width)) ? $img->width : 0;
      $height = (isset($img->height)) ? $img->height : 0;
      $title = (isset($img->title)) ? $img->title : "";
      $attributes = json_encode($img->getAllAttributes());
      $type = 'image';

      $name = explode('?',basename($img->src));

      // ==========================================================
      // if src is a relative url, then try to fix it
      //if (stristr($src,"http://") === FALSE) {
        // add url to the src
        // $src = rtrim($article->feed_site_url,'/') . "/" . ltrim($src,'/');
      //}
      // ==========================================================

      // attempt to retrieve image size
      //$image_data = OB::getImageSize($src,UrlKit::getDomain($src)); 
      //print_r($image_data);
      if ( ($width == 0) && ($height == 0) ) {
        $image_data = getimagesize($src);
        if ( (is_array($image_data)) && ($image_data[0] > 100) && ($image_data[1] > 50) ) {
          $exclude_image = FALSE;
          $width = $image_data[0];
          $height = $image_data[1];
        }
        else {
          $exclude_image = TRUE;
        }
      }

      if ($exclude_image == FALSE) {
        // check the image name to see if it needs to be excluded
        foreach ($exclude_image_names as $val) {
          if (stristr($name[0],$val) === FALSE) {
            $exclude_image = FALSE;
          }
          else {
            $exclude_image = TRUE; break;
          }
        }
      }

      // if all good
      if ($exclude_image == FALSE) {
        // to hopefully get rid of ads and icons and other crap
        if ( ($width > 100) && ($height > 100) ) {
          array_push($image_array,
            array("embed_type"=>"img","media_src"=>$src,"media_name"=>$name[0],"media_alt"=>$alt,"media_width"=>$width,"media_height"=>$height,"media_title"=>$title,"media_type"=>$type,"media_attributes"=>$attributes)
          );
        }
        /*else {
          array_push($image_array,
            array("embed_type"=>"img","media_src"=>$src,"media_name"=>$name[0],"media_alt"=>$alt,"media_width"=>$width,"media_height"=>$height,"media_title"=>$title,"media_type"=>$type,"media_attributes"=>$attributes)
          );
        }*/
      }
    }

    // get rid of variables we don't need anymore
    $html = NULL; $images = NULL;

    // if we didn't find no images then make it null
    if (count($image_array) == 0) {
      $image_array = NULL;
    }

    return $image_array;
  }

  // gets any video from a content:encoded tag within RSS 2.0 feed
  // only supports iframes right now
  static public function video($content) {
    $html = new FastSimpleHTMLDom\Document();
    // array of videos
    $video_array = array();
    // load the content
    $html->loadHtml($content);

    // ================================
    // find embeds
    // ================================
    $objects = $html->find('iframe');
    foreach ($objects as $embed) {
      $video = array();
      $video['media_type'] = 'video';
      $video['embed_type'] = 'iframe';
      $video['media_src'] = $embed->src;
      $video['media_height'] = (isset($embed->height)) ? $embed->height : 0;
      $video['media_width'] = (isset($embed->width)) ? $embed->width : 0;
      $video['media_attributes'] = json_encode($embed->getAllAttributes());
      // add item to video array
      $video_array[] = $video;
    }

    $html = NULL; $objects = NULL;

    if (count($video_array) == 0) {
      $video_array = NULL;
    }

    return $video_array;
  }
}
