<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Tasc
 * @subpackage Tasc/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Tasc
 * @subpackage Tasc/includes
 * @author     Your Name <email@example.com>
 */
class Tasc_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate( $networkwide ) {
    // setup and install for multisite WP
    if ( (function_exists('is_multisite')) && (is_multisite()) && $networkwide ) {
      global $wpdb;
      foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
        switch_to_blog($blog_id);
        /* do create tables and all that crap here */
        restore_current_blog();
      }
    }
    // setup and install for single WP
    else {
      $do_nothing = 0;
    }
	}

}
