<?php
/**
 * Plugin Name: wp->search
 * Plugin URI:  https://github.com/paperclip/wp-search
 * Description: A WordPress admin search plugin that indexes registered settings pages and surfaces them through a REST endpoint.
 * Version:     1.0.0
 * Author:      Paperclip
 * Author URI:  https://paperclip.ing
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-search
 * Domain Path: /languages
 *
 * @package WP_Search
 */

// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect -- WordPress coding standard uses tabs.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_SEARCH_VERSION' ) ) {
	define( 'WP_SEARCH_VERSION', '1.0.0' );
}

if ( ! defined( 'WP_SEARCH_PLUGIN_FILE' ) ) {
	define( 'WP_SEARCH_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WP_SEARCH_PLUGIN_DIR' ) ) {
	define( 'WP_SEARCH_PLUGIN_DIR', plugin_dir_path( WP_SEARCH_PLUGIN_FILE ) );
}

if ( ! defined( 'WP_SEARCH_PLUGIN_URL' ) ) {
	define( 'WP_SEARCH_PLUGIN_URL', plugin_dir_url( WP_SEARCH_PLUGIN_FILE ) );
}

/**
 * Fire when the plugin is loaded.
 *
 * @since 1.0.0
 * @return void
 */
function wp_search_load(): void {
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-settings-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-posts-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-products-indexer.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-rest-controller.php';
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-admin.php';

	$wp_search_indexer = new WP_Search\Settings_Indexer();
	$wp_search_indexer->init();

	$wp_search_rest = new WP_Search\REST_Controller();
	$wp_search_rest->init();

	$wp_search_admin = new WP_Search\Admin();
	$wp_search_admin->init();
}
add_action( 'plugins_loaded', 'wp_search_load' );

/**
 * Run on plugin activation.
 *
 * @since 1.0.0
 * @return void
 */
function wp_search_activate(): void {
	require_once WP_SEARCH_PLUGIN_DIR . 'includes/class-settings-indexer.php';
	( new WP_Search\Settings_Indexer() )->reindex();
}
register_activation_hook( WP_SEARCH_PLUGIN_FILE, 'wp_search_activate' );

/**
 * * Run on plugin deactivation.
 *
 * @since 1.0.0
 * @return void
 */
function wp_search_deactivate(): void {
	delete_transient( WP_Search\Settings_Indexer::INDEX_TRANSIENT_KEY );
}
register_deactivation_hook( WP_SEARCH_PLUGIN_FILE, 'wp_search_deactivate' );
