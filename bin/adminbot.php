#!/usr/bin/php

<?php
// set default timezone
date_default_timezone_set('America/New_York'); 

define('TASCBOT_ROOT',dirname(__FILE__));

/* ======================================================
 * DEFINE VENDOR and CUSTOM FILES TO INCLUDE 
 * ====================================================== */
require_once TASCBOT_ROOT . '/../vendor/autoload.php';
require TASCBOT_ROOT . '/../bin/tascbot-config.php';
require TASCBOT_ROOT . '/../config/database.php';

/* ======================================================
 * DEFINE VENDOR, CUSTOM CLASSES OR CONFIGS TO USE
 * ====================================================== */
use Illuminate\Database\Capsule\Manager as DB;

global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
global $_SERVER;

require(BASE_PATH . 'wp-load.php');
require_once(BASE_PATH . 'wp-includes/user.php');
require_once(BASE_PATH . 'wp-admin/includes/post.php');

/* ======================================================
 * DEFINE THE TASCBOT CLASS
 * ====================================================== */

class AdminBot {
  public function __construct( $site_name ) {
    // set the site id (wp blog_id)
    // should be a class called 'Site' that will handle all site related info
    $this->site = new StdClass();
    $this->site->name = $site_name;
    // WP blog_id
    $this->site->id = 0;
    $this->site->url = '';
    $this->site->feedtable = '';
    $this->site->posttable = '';
    $this->site->jobtable = '';
    $this->site->cachedir = '';
  }

  // Function startup
  // ----------------------------------------
  // Initializes services and features that tascbot needs to 
  // run properly
  public function startup( $feed_id = 0, $feed_limit = 0 ) {
    // get job information for tascbot
    $results = DB::select(
      "SELECT job_id,job_name,job_status,job_exec_status,job_modules,job_logfile,job_params" .
      " FROM {$this->site->jobtable} WHERE job_name = '" .strtolower($this->site->name). "-tascbot'"
    );
      
    if (count($results) == 1) {
      $this->site->job_id = $results[0]->job_id;
      $this->site->job_name = $results[0]->job_name;
      $this->site->feed_limit = $feed_limit;
      $this->site->useragent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36";


      // setup streamhandler for the job logfile
      $this->logger->pushHandler( new Monolog\Handler\StreamHandler($results[0]->job_logfile, Monolog\Logger::ERROR) );
      // setup job parameters
      if (! empty($results[0]->job_params) ) {
        $this->site->parameters = json_decode($results[0]->job_params); //print_r($this->site->parameters); exit;
      }
      else {
        $this->site->parameters = new StdClass();
      }

      // setup job modules
      if (! empty($results[0]->job_modules) ) {
        $this->site->modules = explode(',',$results[0]->job_modules);
      }
      else {
        $this->site->modules = NULL;
      }

      // update jobs table with exec info
      DB::table($this->site->jobtable)->where('job_id',$this->site->job_id)->update(['job_exec_date'=>DB::raw('NOW()'),'job_exec_status'=>'RUNNING']);

    }
    else {
      $this->logger->warning("Could not find site job information for {$this->site->name}::tascbot");
      $this->logger->warning("No tascbot modules or parameters for {$this->site->name}");
      $this->logger->warning("Logging to STDOUT only for {$this->site->name}");
      exit; 
    }

    // get the category mapping data for later lookups
    $results = DB::select("SELECT wp_term_id, tag, strict FROM {$this->site->maptable}"); 
    $this->site->tag_map = array();
    if (count($results) > 0) {
      foreach ($results as $r) {
        $key = CleanText::sanitize($r->tag);
        $this->site->tag_map[ $key ] = new StdClass();
        $this->site->tag_map[ $key ]->wp_term_id = $r->wp_term_id;
        $this->site->tag_map[ $key ]->strict = ((strcasecmp('N',$r->strict)==0) ? FALSE : TRUE);
        /*  $this->site->better_map = array();
        if (array_key_exists($r->wp_term_id,$this->site->better_map)) {
          $this->site->better_map[ $r->wp_term_id ] .= $key . ",";
        }
        else {
          $this->site->better_map[ $r->wp_term_id ] = $key . ",";
        }
        print_r($this->site->better_map);*/
      }
      $this->logger->info("Category Mapping data loaded into memory successfully");
    }


    $this->setRunMode($feed_id);
    $this->logger->info("Running in {$this->run_mode} only");

    $this->logger->info("Starting up TASCBOT session for " . strtoupper($this->site->name));
  }

  // Function run
  // ----------------------------------------
  // The run function directs the aggregation of content from the sources stored in the database.  
  // In addition to content gathering, this function applies modules, filters and parameters to 
  // the data gathered.
  public function run( $feed_id = 0 ) {
    // setup process counts
    $process_counts = new StdClass();
    $process_counts->fetched = 0;
    $process_counts->parsed = 0;
    $process_counts->excluded = 0;
    $process_counts->feed_exclude = 0;
    $process_counts->posted = 0;


        // =============================================================
        // RUN ANY GLOBAL MODULES FOR THE JOB
        // =============================================================
        if ( (! is_null($this->site->modules) ) && (count($this->site->modules)>0) ) {
          // loop over the potential modules and make it happen
          foreach($this->site->modules as $module) {
            $module = strtolower($module);
            $this->logger->info('>>> FIRING GLOBAL MODULE: ' . $module);
            try {
              if (class_exists($module)) {
                $module_obj = new $module ($post,$feed);
                $post = $module_obj->run();
              }
              else {
                throw new Exception("GLOBAL MODULE {$module} DOES NOT EXIST");
              }
            }
            catch (Exception $e) {
              $this->logger->error("<<<>>> {$e->getMessage()} ");
              continue;            
            }
          }
        }

    // update the tasc_site_jobs table with the status and exec_date
    DB::table($this->site->jobtable)->where('job_id',$this->site->job_id)->update(['job_exec_date'=>DB::raw('NOW()'),'job_exec_status'=>'COMPLETE']);
  }

  // Function wordpress_post
  // ----------------------------------------
  // This function creates a new post array record completely ready to be saved to
  // wordpress and returns it.
  public function wordpress_post($post,$default_tags=NULL,$default_categories=NULL) { 
  // This function assumes that the $post object contains the required bare minimum fields
  // and therefore does not check to see if post_title and other things are present.
  // If everything is functioning well, all of these things should be present.

    // make sure everything in this list is lowercase
    if (count($post->tags->category) > 0) {
      $post->tags->category = array_map('strtolower', $post->tags->category);
    }

    // remove unwanted tags
    if (isset($this->site->parameters->exclude_tags)) {
      if (is_array($this->site->parameters->exclude_tags)) {
        $result = array_diff($post->tags->category,$this->site->parameters->exclude_tags);
        $post->tags->category = $result;
      }
    }

    // handle default tags and add them to overall tag list
    if (!empty($default_tags)) {
      $default_tag_array = explode(',',$default_tags);
      foreach($default_tag_array as $t) {
        $post->tags->category[] = $t;
      }
      //if (in_array(strtolower($needle), array_map('strtolower', $haystack)))
    }

    // add default categories
    $post->post_category = array();
    if (!empty($default_categories)) {
      // break them out into an array
      $default_cat_array = explode(',',$default_categories);
      foreach($default_cat_array as $cat) {
        $cat_id = category_exists($cat);  
        if (!is_null($cat_id)) {
          $post->post_category[] = $cat_id;
        }
      }
    }

    // ----------------------------------------
    // setup term extraction for post text
    // ----------------------------------------
    try {
    $query = 'select * from contentanalysis.analyze where text="' . addslashes($post->post_title . $post->post_body) . '"';
    $ca = Fetch::withYQL($query);
    // check to see if something good came back.  If not...just skip it
    if ( (isset($ca->success)) && ($ca->success == 1) ) {
      if (isset($ca->contents->query->results->entities)) {
        $ca_terms = $ca->contents->query->results->entities;
        foreach ($ca_terms->entity as $term) {
          // check term score and add if qualified
          if ( (is_object($term)) && (isset($term->score)) && ($term->score > 0.599) ) {
            $post->tags->category[] = $term->text->content;  
          }
        }
      }
    }
    }
    catch (Exception $e) {
      $this->logger->error("<<<>>> {$e->getMessage()}");
    }

    /*require OPTSHARE_TERMEXTRACT . '/TermExtractor.php';
    require OPTSHARE_TERMEXTRACT . '/DefaultFilter.php';
    $de = new DefaultFilter(4,2);
    $te = new TermExtractor(NULL,$de);
    $te_terms = $te->extract( strip_tags($post->post_body) );
    print_r($te_terms); exit; */

    // ----------------------------------------
    // setup categories by mapping tags and using the category_mapping table
    // ----------------------------------------
    foreach($post->tags->category as $tag) {
      $cleaned_tag = CleanText::sanitize($tag);
      /*foreach($this->site->tag_map as $wp_term_id => $tag_string) {
        if (stristr($tag_string,$cleaned_tag) !== FALSE) {
          $post->post_category[] = $wp_term_id;
        }
      }*/
      if (array_key_exists($cleaned_tag,$this->site->tag_map)) {
        $post->post_category[] = $this->site->tag_map[$cleaned_tag]->wp_term_id;
      }
    }
    // now loop over items in tag map, locate the non-strict tags and search for them in
    foreach ($this->site->tag_map as $key => $value) {
      // if this ain't strict
      if (! $value->strict) {
        // try to find a piece of the value in the array
        foreach($post->tags->category as $k => $v) {
          //print_r($key . "=" . $v . "\n");
          if (stristr($v,$key) !== FALSE) {
            $post->post_category[] = $value->wp_term_id;
          }
        }
      }
    }

    // setup post images
    if (count($post->media->images) > 0) {
      // if images then use the first one...
      $post->post_image = $post->media->images[0]; 
      // clear out the post->media->images array
      $post->media->images = NULL;
      // we may need code here to deal with other images...but for now, let's not
    }
    else {
      $post->post_image = NULL;
    }

    // setup post video
    if (count($post->media->video) > 0) {
      // loop over and build iframes
      foreach ($post->media->video as $key=>$video) {
        /*if (($video['media_width'] == 0) || ($video['media_height']==0)) {
          $video['media_width'] = 320; $video['media_height'] = 180;
        }*/
        $video['media_width'] = 560; $video['media_height'] = 473;
        $post->media->video[$key]['html'] = '<iframe src="'.$video['media_src'].'" width="'.$video['media_width'].'" height="'.$video['media_height'].'" frameborder="0" scrolling="no" allowtransparency="true"></iframe>';
      }
    }
  
    // if one of the default tags is video then we know we have to use embedly's crap to fix it
    
    return $post;
  }

  // Function wordpress_save
  // ----------------------------------------
  // This function saves everything necessary to wordpress for a post
  public function wordpress_save($array_of_posts,$meta_keys=NULL) { 

    global $wpdb;
    $posts_posted = 0;
    //$new_posts = array();

    if (! isset($this->site->parameters->wp_allow_post_update)) {
      $this->site->parameters->wp_allow_post_update = FALSE;      
    }

    // setup stuff so that wordpress will do this alittle faster
    remove_action('do_pings', 'do_all_pings', 10, 1);

    if (! defined('WP_IMPORTING') ) {
      define( 'WP_IMPORTING', true );
    }
    ini_set("memory_limit",-1);
    set_time_limit(0);
    ignore_user_abort(true);

    wp_defer_term_counting( true );
    wp_defer_comment_counting( true );
    $wpdb->query( 'SET autocommit = 0;' );

    // switch to the right site
    switch_to_blog($this->site->id);

    // remove content filters
    kses_remove_filters();

    // loop over the post array and insert each one
    foreach($array_of_posts as $post) {
      $wp_post_id = 0;
      // last ditch to make sure that the post body isn't empty 
      if ( (is_null($post->post_body)) || (strlen($post->post_body) == 0) ) {
        $post->post_body = $post->post_excerpt;
      }

      // =================================================
      // SETUP WORDPRESS POST SIMPLE LAYOUT
      // =================================================

      // if there is video
      if (count($post->media->video) == 1) {
        //$post_template = "<p>{$post->media->video[0]['html']}</p>" . CleanText::makeExcerpt($post->post_body,500) . 
        //"<p>To view the full post visit:<br/><a target=\"_blank\" href=\"{$post->post_url}\">{$post->post_url}</a></p>";
        $post_template = "<p>{$post->media->video[0]['html']}</p>" . CleanText::makeExcerpt($post->post_body,500) . 
        "<p>To view the full post visit:<br/><a target=\"_blank\" href=\"{$post->post_url}\">{$post->post_url}</a></p>";
      }
      // if there is more than one
      else if (count($post->media->video) > 1) {
        $post_template = "<p>{$post->media->video[0]['html']}</p>" . CleanText::makeExcerpt($post->post_body,500) . 
        "<p>To view the full post visit:<br/><a target=\"_blank\" href=\"{$post->post_url}\">{$post->post_url}</a></p>";
      }
      // if no video
      else {
        $post_template = CleanText::makeExcerpt($post->post_body,500) . 
        "<p>To read the full post visit:<br/><a target=\"_blank\" href=\"{$post->post_url}\">{$post->post_url}</a></p>";
      }

      // add the post meta to the new_post
      $meta = array(
        'post_url' => $post->post_url,
        'post_hash' => $post->post_hash,
        'post_image' => $post->post_image,
        'post_score' => 0
      );

      //$new_posts[] = array(
      $new_post = array(
        'post_title' => $post->post_title,
        'post_content' => $post_template,
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_author' => $post->author_id,
        'ping_status' => get_option('default_ping_status'),
        'post_date' => $post->post_pubdate,
        'post_category' => wp_slash($post->post_category),
        'tags_input' => wp_slash($post->tags->category),
        'post_parent' => 0,
        'menu_order' => 0,
        'to_ping' => '',
        'pinged' => '',
        'post_password' => '',
        'guid' => '',
        'post_content_filtered' => '',
        'post_excerpt' => CleanText::makeExcerpt($post->post_excerpt,200),
        'import_id' => 0,
        'filter' => TRUE
      );

      //$post_id_check = post_exists($post->post_title,'',$post->post_pubdate);
      //$post_id_check = post_exists($post->post_title);
      $post_id_check = post_exists($post->post_title);
      if ($post_id_check == 0) {
        $wp_post_id = wp_insert_post(wp_slash($new_post),TRUE);
        if( is_wp_error( $wp_post_id ) ) {
          $this->logger->error("<<<>>> " . $wp_post_id->get_error_message());
        }
        else {
          $posts_posted++;
          $this->logger->info(">>> POST INSERTED [{$post->post_title}]");
        }
      }
      else if ( ($post_id_check > 0) && ($this->site->parameters->wp_allow_post_update)) {
        $new_post['ID'] = $post_id_check;
        $wp_post_id = wp_update_post(wp_slash($new_post),TRUE); 
        if( is_wp_error( $wp_post_id ) ) {
          $this->logger->error("<<<>>> " . $wp_post_id->get_error_message());
        }
        else {
          $posts_posted++;
          $this->logger->info(">>> POST UPDATED/POST EXISTS [{$post->post_title}]");
        }
      }
      else {
        $this->logger->info(">>> NOT INSERTING/POST EXISTS [{$post->post_title}]");
      }

      // adding post meta
      if ($wp_post_id > 0) {
        // add the post meta
        add_post_meta($wp_post_id, 'post_hash', wp_slash($post->post_hash), TRUE);
        add_post_meta($wp_post_id, 'post_url', wp_slash($post->post_url), TRUE);
        add_post_meta($wp_post_id, 'post_score', 0, TRUE);
        if (!is_null($post->post_image) ) {
          $this->wordpress_image($post->post_image,$wp_post_id);
        }
      }
    }

    /*if (count($new_posts) > 0) {
      // today's date MMDDYYYY
      $file_date = date("mdy");
      // create save filename
      $save_filename = $this->site->datadir . $file_date . '-' . strtolower($this->site->name) . '-data.save';
      // save insert post array with meta items to <site_name>.save
      $save_write = file_put_contents($save_filename,json_encode($new_posts));
      if ($save_write !== FALSE) {
        $this->logger->info(">>> SAVE FILE WRITE {$save_filename}");
      }
      else {
        $this->logger->error(">>> SAVE FILE WRITE FAIL {$save_filename}");
      }
    }*/

    // commit new items
    $wpdb->query( 'COMMIT;' );
    // reinstate filters
    kses_init_filters();

    wp_defer_term_counting( false );
    wp_defer_comment_counting( false );

    return $posts_posted;
    //return count($new_posts);
  }

  // Function wordpress_image
  // ----------------------------------------
  // This function downloads the image, if possible and if no errors sets the 
  // item as the featured image and thumbnail
  public function wordpress_image($post_image,$wp_post_id) {
    $skip_attach = FALSE;  
    $upload_dir = wp_upload_dir();

    //print_r($post_image);
    $image = explode('?',$post_image['media_src']);
    $fileext = explode(".",basename($image[0])); $file_segments = count($fileext);
    $filename = "post-image-{$wp_post_id}.{$fileext[$file_segments-1]}" ;

    //print_r($upload_dir);
    if(wp_mkdir_p($upload_dir['path'])) {
      $file = $upload_dir['path'] . '/' . $filename;
    }
    else {
      $file = $upload_dir['basedir'] . '/' . $filename;
    }

    // grab that remote image
    $response = wp_remote_get($image[0], 
      array(
        'timeout' => 120,
        'stream' => TRUE,
        'filename' => $file,
        'headers' => array(
        'user-agent' => $this->site->useragent
        )
      )
    );

    // we need to check the file size here to make sure no empty images
    // get saved and attached
    $file_size = filesize($file);
    if ( ($file_size == 0) || ($file_size === FALSE) ) {
      $this->logger->error(">>> ZERO SIZE IMAGE {$post_image['media_src']}");
      $this->logger->error(">>> POSSIBLE PERMISSIONS PROBLEM IN SITE UPLOAD DIRECTORY");
      $skip_attach = TRUE;  
    }
    
    // get the response code
    $response_code = wp_remote_retrieve_response_code( $response );

    if ( ($skip_attach == FALSE) && (is_array( $response )) && (! is_wp_error( $response )) ) {
      $wp_filetype = wp_check_filetype($filename, null );
      $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
      );
      $attach_id = wp_insert_attachment( $attachment, $file, $wp_post_id );
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      //print_r($attach_id . "--" . $file);
      $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
      $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
      $res2= set_post_thumbnail( $wp_post_id, $attach_id );

      $this->logger->info(">>> SAVE IMAGE {$file} was successful (code:{$response_code})");
    }
    else {
      $this->logger->error("<<<>>> FAIL SAVE IMAGE {$file} (code:{$response_code})");
    }
  }

  // Function wordpress_author
  // ----------------------------------------
  // This function checks to see if a given username is defined as an author and
  // if not, it creates the author with basic detail
  public function wordpress_author($username,$nicename,$url) {
    // wordpress author id
    $id = 0;
    // author status, true or false
    $status = FALSE;
    // get the default wordpress user role
    //$default_role = get_option('default_role');
    $default_role = 'author';

    // setup the user name 
    $wp_username = CleanText::sanitize( trim($username) );

    $this->logger->info(">>> Checking to see if {$username} exists as an author");

    // use wordpress function to check username
    $user_id = username_exists( $wp_username );

    if (! $user_id ) {
      $random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
      $id = wp_create_user( $wp_username, $random_password );
      // now add the additional information for the new user
      $insert_return = wp_update_user(
        array(
          'ID' => $id,
          'user_url' => $url,
          'user_nicename' => $nicename,
          'display_name' => $nicename,
          'role' => $default_role
        )
      );
      $this->logger->info(">>> {$username} did not exist as an author and has been created");
      $user_id = $id;
      $status = TRUE;
    }
    else {
      $this->logger->info(">>> {$username} is already an author");
      $status = TRUE;
    }

    // make sure the user_id is a user on the right blog
    if ( ($status == TRUE) && (! is_user_member_of_blog( $user_id, $this->site->id )) ) {
      // add the user to the site
      $retval = add_user_to_blog( $this->site->id, $user_id, $default_role );
      if (is_wp_error($retval)) {
        $this->logger->error("<<<>>> Could not add {$username} to {$this->site->name}");
      }
      else {
      $this->logger->info(">>> {$username} added to {$this->site->name} as {$default_role}");
      }

      // remove the user from the main elombre blog
      remove_user_from_blog($user_id,1);

    }
    return array($user_id,$status);
  }

  // Function getFeeds
  // ----------------------------------------
  // This function retrieves feeds from the appropriate table for content
  // processing
  public function getFeeds( $feed_id = 0, $limit=0 ) {
    $results = NULL;
    $output_buffer = "";

    $table = $this->site->feedtable; 
    $status = 'test';

    if (strcasecmp($this->run_mode,'SINGLE_MODE') == 0) {
      $query_stmt = "SELECT * FROM {$table} WHERE feed_status = '{$status}' AND fid = {$feed_id} AND feed_type IN ('rss','atom')";
    }
    else if (strcasecmp($this->run_mode,'MULTI_MODE') == 0) {
      $query_stmt = "SELECT * FROM {$table} WHERE feed_status = '{$status}' AND fetch_date < DATE_SUB(NOW(), INTERVAL fetch_interval SECOND ) AND feed_type IN ('rss','atom')";
    }
    else {
      $query_stmt = "SELECT * FROM {$table} WHERE feed_status = '{$status}' AND fetch_date < DATE_SUB(NOW(), INTERVAL fetch_interval SECOND ) AND feed_type IN ('rss','atom')";
    }

    // add order by to statement
    if ( ($feed_id == 0) && ($limit > 0) ) {
      $query_stmt .= " ORDER BY fetch_date ASC LIMIT {$limit}";
    }
    else {
      $query_stmt .= " ORDER BY fetch_date ASC";
    }

    $results = DB::select( DB::raw( $query_stmt ) );

    foreach ($results as $r) {
      $this->logger->info(">>> {$r->feed_name}({$r->fid}) will be fetched for processing"); 
      //$output_buffer .= ">>> [{$r->feed_name}]\n";
    }

    /*if (strlen($output_buffer) > 0) {
      $this->logger->info("{$output_buffer}>>> The above sources will be fetched for processing"); 
    }*/

  return $results;
  }

  // Function shutdown
  // ----------------------------------------
  // This function executes commands necessary to allow TASCBOT to end gracefully.
  // TASCBOT will clean up work files, database connections, temporary tables, etc.
  public function shutdown() {
    $this->logger->info("Shutting down TASCBOT session for {$this->site->name}");
  }

  // Function setRunMode
  // ----------------------------------------
  // Sets up the logger object for the TascBot class
  public function setRunMode( $id = 0 ) {
    // prepare the right run mode
    if ($id > 0) {
      $this->run_mode = "SINGLE_MODE";
    }
    else {
      $this->run_mode = "MULTI_MODE";
    }
  }

  // Function setLogger
  // ----------------------------------------
  // Sets up the logger object for the TascBot class
  public function setLogger( $logger ) {
    $this->logger = $logger;  
  }

  // Function isReady
  // ----------------------------------------
  // Returns true if tascbot is ready to work.  The function initiates a variety of checks
  // to make sure that everything the script needs is in place.
  public function isReady() {
    $name_to_check = trim( strtolower($this->site->name) );

    // check to see if the site name exists
    $results = DB::select(
      "SELECT blog_id,domain,path FROM wp_blogs WHERE domain LIKE '%{$name_to_check}%' OR path LIKE '%{$name_to_check}%'"
    );
    //
    //print_r($results);
    //
    if (count($results) == 1) {
      // set blog id
      $this->site->id = $results[0]->blog_id;
      $this->site->url = $results[0]->domain . $results[0]->path;
      $this->site->name = strtoupper($name_to_check);
      $this->site->feedtable = 'tasc_' . $this->site->id . '_' . 'feeds';
      $this->site->posttable = 'tasc_' . $this->site->id . '_' . 'posts';
      $this->site->jobtable = 'tasc_site_jobs';
      $this->site->cachedir = TASCBOT_ROOT . '/../cache/';
      $this->site->datadir = TASCBOT_ROOT . '/../data/';
      $this->site->maptable = 'tasc_' . $this->site->id . '_' . 'category_mapping';
    }
    else if (count($results) > 1) {
      // too many results
      print "TOO MANY ENTRIES FOR REQUESTED SITE\n";
      return FALSE;
    }
    else {
      // no results
      print "COULD NOT FIND REQUESTED SITE\n";
      return FALSE;
    }
    // check to see if the feeds table exists
    if ( ! DB::schema()->hasTable($this->site->feedtable)) { return FALSE; }
    // check to see if the post table exists
    //if ( ! DB::schema()->hasTable($this->site->posttable)) { return FALSE; }
    // check to see if the job table exists
    if ( ! DB::schema()->hasTable($this->site->jobtable)) { return FALSE; }
    // check to see if the category_mapping table exists
    if ( ! DB::schema()->hasTable($this->site->maptable)) { return FALSE; }
    // check to see if the post table exists
    if ( ! DB::schema()->hasTable('wp_blogs')) { return FALSE; }

    // check to see if there are any feeds defined for the site
    $results = DB::select(
      "SELECT count(fid) as feed_count FROM {$this->site->feedtable}"
    );
    if ($results[0]->feed_count == 0) { return FALSE; }

    // check to see if the cache dir exists
    if (! file_exists($this->site->cachedir)) { return FALSE; }
    // check to see if the data dir exists
    if (! file_exists($this->site->datadir)) { return FALSE; }

    // check to see if there are previous run dumps leftover
    return TRUE;
  }

  // Function isExcluded
  // ----------------------------------------
  // Returns true if the excludeRules method returns anything other than 'NO_EXCLUDE'
  private function isExcluded($post,$return_reason=FALSE,$feed=NULL,$options=NULL) {
    $return_object = new StdClass();
    $exclude_result = OB::excludeRules($post,$feed,$options);
    // return the appropriate result based on the results of the exclusion routine
    if (strcasecmp($exclude_result,"NO_EXCLUDE")==0) { 
      $return_object->value = FALSE;
      $this->logger->info('>>> NOT EXCLUDING post is eligible for saving');
    }
    else {
      // the item is excluded
      $return_object->value = TRUE;
      $this->logger->warning(">>> EXCLUDING because post data met {$exclude_result} criteria");
    }

    if ($return_reason) {
      $return_object->reason = $exclude_result;
    }

    return $return_object;
  }
}

/* ======================================================
 * SETUP THE SCRIPT TO RUN
 * ====================================================== */

//echo implode( ' ', $wpdb->tables() ) . "\n"; exit;
$tasc_cmd = new Commando\Command();
// setup the site argument.  This is required.
$tasc_cmd->option()->require()->describedAs('This should be the site\'s string name');
// setup the individual feed argument.  This is optional.
$tasc_cmd->option('f')->
  aka('feed_id')->
  describedAs('This should be a feed id (fid) from a site\'s feeds table')->
  must(function($feed_id) {
    return is_numeric($feed_id);
  });
// setup the limit flag.  This will cap the number of feeds grabbed in any full run
$tasc_cmd->option('l')->
  aka('limit')->
  describedAs('This is the number of feeds to limit tascbot to in any given run cycle')->
  must(function($limit) {
    return is_numeric($limit);
  });

// get site name
$site_name = $tasc_cmd[0];

if (! empty($tasc_cmd['feed_id']) ) {
  $feed_id = $tasc_cmd['feed_id']; 
}
else {
  $feed_id = 0;
}

if (! empty($tasc_cmd['limit']) ) {
  $feed_limit = $tasc_cmd['limit']; 
}
else {
  $feed_limit = 0;
}

$tb = new TascBot( $site_name );

// set the logger
$tb->setLogger( $tascbot_logger );

if ($tb->isReady()) {
  $tb->startup( $feed_id, $feed_limit );
  $tb->run( $feed_id );
  $tb->shutdown();
}
else {
  //$tb->shutdown();
  print "We aren't ready to run\n";
}
