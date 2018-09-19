<?php

define('TASCBOT_ROOT',dirname(__FILE__));
require TASCBOT_ROOT . '/../vendor/autoload.php';
require TASCBOT_ROOT . '/../config/database.php';

use Illuminate\Database\Capsule\Manager as DB;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/admin
 * @author     Your Name <email@example.com>
 */
class Tasc_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/plugin-name-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/plugin-name-admin.js', array( 'jquery' ), $this->version, false );

	}

  public function tasc_network_menu() {
    add_menu_page( '[TASC] Network Dashboard', 'TASC', 'manage_sites', 'tasc', array($this,'tasc_dashboard_init'));
    add_submenu_page( 'tasc', '[TASC] TweetTools', 'Tweet-Tools', 'manage_sites', 'tasc-tweet-tools', array($this,'tasc_tweettools_init'));
  }

  public function tasc_dashboard_init() {
    //include(TASCBOT_ROOT . '/../admin/TascDashboard.php');
    $dashboard = new Tasc_Dashboard();
    if ($dashboard->isReady()) {
      $dashboard->run();
    }
  }

  public function tasc_tweettools_init() {
    $tweettools = new Tasc_TweetTools();
    // make sure we are ready to run
    if ($tweettools->isReady()) {
      $tweettools->run();
    }
  }
}

/** 
**/
class Tasc_Dashboard {
  public function __construct() {
    // setup class variables 
    $this->blog_table = "wp_blogs"; 
    $this->job_table = "tasc_site_jobs"; 
    // setup database connection
    // setup form object
    //include(TASCBOT_ROOT . '/../admin/TweetToolsForm.php');
    //$this->form = new TweetToolsForm();
  }

  public function isReady() {
    return true;
  }

  public function run() {
    $job_stats = $this->getJobStatus();
    include(TASCBOT_ROOT . '/../admin/view-dashboard.php');
  }

  // returns the list of jobs that last ran
  private function getJobStatus() {
    $job_list = DB::select("
      SELECT * FROM {$this->job_table} 
      WHERE job_exec_date > DATE_SUB(NOW(), INTERVAL 1 DAY )
      AND job_status = 'active'
      ORDER BY job_exec_date DESC 
    ");
    return $job_list;
  } 

  private function getRunningJobs() {}
}

/** 
**/
class Tasc_TweetTools {
  public function __construct() {
    // setup class variables 
    $this->domains = array();
    $this->tt_mapping = array();
    // setup database connection
    // setup form object
    include(TASCBOT_ROOT . '/../admin/TweetToolsForm.php');
    $this->form = new TweetToolsForm();
  }

  public function isReady() {
    $dm = DB::select("SELECT blog_id, domain FROM wp_domain_mapping WHERE active = 1");
    // put the domains into an associative array
    foreach ($dm as $d) {
      $this->domains[$d->blog_id] = $d->domain;
    }
    $mp = DB::select("SELECT * FROM tasc_tt_mapping");
    foreach ($mp as $m) {
      $this->tt_mapping[$m->site_id] = $m->tasc_ttm_id;
    }
    return true;
  }

  public function run() {
    // set as default
    $this->active_blog_id = 0;
    $this->previously_tweeted = array();
    $error_message = NULL;

    $site_list = DB::select("SELECT blog_id, site_id, domain, path FROM wp_blogs WHERE blog_id > 1");

    $this->form->build(); $this->form->populate($_POST);

    // if actual submit from big button
    $form_submit = $this->form->getField('form_submit');
    if (!empty($form_submit->value)) {
      $this->form->submitted(TRUE);
    }

    // grab the form data
    $selected_site = $this->form->getField('tt_sitelist');
    if (empty($selected_site->value)) {
      $this->active_blog_id = $selected_site->default_value;
    }
    else {
      // form must have been submitted
      $this->active_blog_id = $selected_site->value;
    }

    // check to see if the form as posts checked
    $selected_posts = $this->form->getField('tt_selected');
    if ($this->form->isSubmitted()) {
      //&& (!empty($selected_posts->value))) {
      $this->form->validate();
      if ($this->form->isValid()) {
        $this->process();
      }
      else {
        print_r("not valid");
        $error_message = $selected_posts->error;
      }
    }

    $blog_id = $this->active_blog_id;

    $pt = DB::select(
      "SELECT post_id, last_run, status, site_id FROM tasc_tt_posts WHERE site_id = {$blog_id} ORDER BY last_run LIMIT 100"
    );
    foreach ($pt as $p) {
      $this->previously_tweeted[$p->post_id] = $p;
    }
    
    // get the list of posts from the appropriate blog id
    $post_table = "wp_{$blog_id}_posts";
    $meta_table = "wp_{$blog_id}_postmeta";
    $this->post_list = DB::select(
      "SELECT {$post_table}.*, {$meta_table}.post_id, {$meta_table}.meta_key, {$meta_table}.meta_value
      FROM {$post_table}, {$meta_table}
      WHERE {$post_table}.post_type = 'post' 
      AND {$post_table}.ID = {$meta_table}.post_id
      AND ({$meta_table}.meta_key = '_wp_attached_file'
      OR {$meta_table}.meta_key = 'post_url')
      AND {$post_table}.post_date_gmt > DATE_SUB(NOW(), INTERVAL 1 DAY )
      AND {$post_table}.ID NOT IN (SELECT post_id FROM tasc_tt_posts WHERE site_id = {$blog_id})
    ");

    // switch to the appropriate blog
    switch_to_blog($blog_id);

    include(TASCBOT_ROOT . '/../admin/view-tweettools.php');
  }

  public function process() {
    // get the selected posts
    $post_info = $this->form->getField('tt_selected'); 
    // explode into an array
    $posts_array = explode(',',$post_info->value);
    // loop over array and split the data into $blog_id and $post_id
    foreach ($posts_array as $postd) {
      $pipe_pos = strpos($postd,'|');  
      $blog_id = substr($postd,0,$pipe_pos);
      $post_id = substr($postd,$pipe_pos+1);
      $this->active_blog_id = $blog_id;

      // Check existence
      $existing_post = TascTtPost::where( array('site_id'=>$blog_id,'post_id'=>$post_id) )->get();
      if (count($existing_post) == 0) {
        // add these items to the tasc_tt_posts
        $tt_post = new TascTtPost();
        $tt_post->site_id = $blog_id;
        $tt_post->tasc_ttm_id = $this->tt_mapping[$blog_id];
        $tt_post->post_id = $post_id;

        // grab the post
        $post = DB::select("SELECT guid FROM wp_{$blog_id}_posts WHERE ID = {$post_id} AND post_type = 'post'");
        $tt_post->post_url = $post[0]->guid;
        // rewrite the domain name
        //$tt_post->last_run
        $tt_post->status = 'pending';
        //print_r($tt_post);
        // save the item
        $tt_post->save();
      }
    }
  }
  
}
