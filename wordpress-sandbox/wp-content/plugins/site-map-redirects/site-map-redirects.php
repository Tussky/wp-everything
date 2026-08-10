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

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName
// The main plugin file is intentionally named site-map-redirects.php (matching the directory slug
// WordPress requires). WPCS expects class-* prefix for files containing a class, but main plugin
// files are an exception. Suppress the sniff here only.

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
define( 'SMR_TRANSIENT_RULES', 'smr_redirect_rules' );
define( 'SMR_TRANSIENT_RULES_TTL', 6 * HOUR_IN_SECONDS );

require_once SMR_DIR . 'includes/class-logger.php';
require_once SMR_DIR . 'includes/class-safe.php';
require_once SMR_DIR . 'includes/class-indexer.php';
require_once SMR_DIR . 'includes/class-redirect-resolver.php';
require_once SMR_DIR . 'includes/class-rest.php';
require_once SMR_DIR . 'admin/class-admin-page.php';

/**
 * Main plugin bootstrap (single instance).
 *
 * @package SiteMapRedirects
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
		SMR_Redirect_Resolver::init();
		SMR_REST::init();
		SMR_Admin_Page::init();
	}

	/**
	 * Activation hook: pre-build an index so the admin page is non-empty on first load.
	 */
	public static function activate() {
		try {
			SMR_Indexer::rebuild();
		} catch ( Throwable $e ) {
			// Activation should never fail the upgrade because of a bad
			// indexer — the plugin can still boot and the user can reindex.
			if ( class_exists( 'SMR_Logger' ) ) {
				SMR_Logger::exception( $e, 'Activation rebuild failed' );
			}
		}
	}

	/**
	 * Deactivation hook: drop the cached transients.
	 */
	public static function deactivate() {
		if ( class_exists( 'SMR_Safe' ) ) {
			SMR_Safe::delete_transient( SMR_TRANSIENT );
			SMR_Safe::delete_transient( SMR_TRANSIENT_RULES );
		} else {
			delete_transient( SMR_TRANSIENT );
			delete_transient( SMR_TRANSIENT_RULES );
		}
	}
}

register_activation_hook( __FILE__, array( 'SiteMap_Redirects', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SiteMap_Redirects', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SiteMap_Redirects', 'instance' ) );