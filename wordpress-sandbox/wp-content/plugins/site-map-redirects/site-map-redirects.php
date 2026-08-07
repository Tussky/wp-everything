<?php
/**
 * Plugin Name:       SiteMap Redirects
 * Plugin URI:        https://preview2.updraftailabs.com/live/isaac-anderson/
 * Description:       Indexes the site into a visual tree map and overlays redirects with their priority order, HTTP status, and destination. Plain-English labels for non-technical users.
 * Version:           0.1.0
 * Author:            Isaac Anderson — AI Labs Cohort #2
 * Author URI:        https://preview2.updraftailabs.com/isaac-anderson/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       site-map-redirects
 * Requires at least: 6.4
 * Requires PHP:      7.4
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'SMR_VERSION', '0.1.0' );
define( 'SMR_FILE', __FILE__ );
define( 'SMR_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMR_URL', plugin_dir_url( __FILE__ ) );
define( 'SMR_BASENAME', plugin_basename( __FILE__ ) );
define( 'SMR_REST_NAMESPACE', 'sitemap-redirects/v1' );
define( 'SMR_TRANSIENT', 'smr_index_tree' );

require_once SMR_DIR . 'includes/class-indexer.php';
require_once SMR_DIR . 'includes/class-redirect-sources.php';
require_once SMR_DIR . 'includes/class-rest.php';
require_once SMR_DIR . 'includes/class-admin.php';

/**
 * Main plugin bootstrap (single instance).
 */
final class SiteMap_Redirects {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		SMR_Indexer::init();
		SMR_Redirect_Sources::init();
		SMR_REST::init();
		SMR_Admin::init();
	}

	public static function activate() {
		// Pre-build an index so the admin page is non-empty on first load.
		SMR_Indexer::rebuild();
	}

	public static function deactivate() {
		delete_transient( SMR_TRANSIENT );
	}
}

register_activation_hook( __FILE__, array( 'SiteMap_Redirects', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SiteMap_Redirects', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SiteMap_Redirects', 'instance' ) );
