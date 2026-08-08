<?php
/**
 * Plugin Name:       Admin Search
 * Plugin URI:        https://preview2.updraftailabs.com/live/isaac-anderson/
 * Description:       Unified keyboard-driven admin search across plugin settings pages, admin users, WooCommerce products, and posts/pages. Read-only MVP for the Admin Search hackathon.
 * Version:           0.1.0
 * Author:            Isaac Anderson — AI Labs Cohort #2
 * Author URI:        https://preview2.updraftailabs.com/isaac-anderson/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       admin-search
 * Requires at least: 6.4
 * Requires PHP:      7.4
 *
 * @package AdminSearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'AS_VERSION', '0.1.0' );
define( 'AS_FILE', __FILE__ );
define( 'AS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AS_URL', plugin_dir_url( __FILE__ ) );
define( 'AS_BASENAME', plugin_basename( __FILE__ ) );
define( 'AS_REST_NAMESPACE', 'admin-search/v1' );
define( 'AS_OPTION_INDEX', 'as_index_v1' );
define( 'AS_OPTION_STATS', 'as_stats_v1' );
define( 'AS_OPTION_QUERIES', 'as_last_queries_v1' );
define( 'AS_OPTION_FIXTURE_QUERY', 'as_fixture_query' );

require_once AS_DIR . 'includes/class-indexer.php';
require_once AS_DIR . 'includes/class-query.php';
require_once AS_DIR . 'includes/class-rest.php';
require_once AS_DIR . 'admin/class-admin-page.php';

/**
 * Main plugin bootstrap (single instance).
 */
final class Admin_Search {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		AS_Indexer::init();
		AS_Query::init();
		AS_REST::init();
		AS_Admin_Page::init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'admin-search reindex', array( 'AS_Indexer', 'cli_rebuild' ) );
		}
	}

	public static function activate() {
		// Pre-build an index so the first search call is non-empty.
		AS_Indexer::rebuild();
	}

	public static function deactivate() {
		// Keep the option around on deactivation; only the uninstall hook wipes it.
	}

	public static function uninstall() {
		delete_option( AS_OPTION_INDEX );
		delete_option( AS_OPTION_STATS );
		delete_option( AS_OPTION_QUERIES );
		delete_option( AS_OPTION_FIXTURE_QUERY );
	}
}

register_activation_hook( __FILE__, array( 'Admin_Search', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Admin_Search', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Admin_Search', 'uninstall' ) );

add_action( 'plugins_loaded', array( 'Admin_Search', 'instance' ) );