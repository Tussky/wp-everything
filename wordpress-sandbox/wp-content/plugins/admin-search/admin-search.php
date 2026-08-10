<?php
/*
 *	Plugin Name:		Admin Search
 *	Plugin URL:			http://www.andrewstichbury.com
 *	Description:		Admin Search adds a simple, easy-to-use interface to your WordPress admin site that gives you and your WordPress admin users the ability to search across multiple post types, taxonomies and more in one place.
 *	Version:			1.5.0
 *	Requires at least:	4.9.2
 *	Requires PHP:		5.2
 *	Author:				Andrew Stichbury
 *	Author URI:			http://www.andrewstichbury.com
 *	Text Domain:		admin-search
 *	License:			GPL v2 or later
 *	License URI:		https://www.gnu.org/licenses/gpl-2.0.html
 */



/*
 *	Abort if this file is accessed directly
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}



define( 'ADMIN_SEARCH_VERSION', '1.5.0' );
define( 'ADMIN_SEARCH_VERSION_INT', 150 );



require_once plugin_dir_path( __FILE__ ) . 'settings.php';
require_once plugin_dir_path( __FILE__ ) . 'ui.php';
require_once plugin_dir_path( __FILE__ ) . 'ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'woocommerce.php';



function admin_search_setup() {

	// Only perform setup if not the current version
	if ( get_option( 'admin_search_version' ) != ADMIN_SEARCH_VERSION_INT ) {
		update_option( 'admin_search_version', ADMIN_SEARCH_VERSION_INT );

		global $wpdb;

		$table_name = $wpdb -> prefix . 'admin_search__searches';

		// Only create `searches` table if it doesn't exist
		if ( $wpdb -> get_var( $wpdb -> prepare( "SHOW TABLES LIKE %s", $table_name ) ) != $table_name ) {
			$charset_collate = $wpdb -> get_charset_collate();

			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				query tinytext NOT NULL,
				results mediumint(9) NOT NULL,
				occurrences mediumint(9) DEFAULT 1 NOT NULL,
				PRIMARY KEY (id)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta( $sql );
		}
	}

}

add_action( 'admin_init', 'admin_search_setup' );

register_activation_hook( __FILE__, 'admin_search_setup' );



function admin_search_add_query_vars_filter( $vars ) {
	$vars[] = 'admin_search_preview';

	return $vars;
}

add_filter( 'query_vars', 'admin_search_add_query_vars_filter' );

