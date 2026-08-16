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
	$files = array(
		'interface-spotlight-provider.php',
		'class-indexer.php',
		'interface-spotlight-provider.php',
		'class-spotlight.php',
		'class-settings-indexer.php',
		'class-users-indexer.php',
		'class-plugins-indexer.php',
		'class-options-indexer.php',
		'class-menus-indexer.php',
		'class-posts-indexer.php',
		'class-products-indexer.php',
		'class-rest-controller.php',
		'class-admin.php',
	);
	
	foreach ( $files as $file ) {
		$path = WP_SEARCH_PLUGIN_DIR . 'includes/' . $file;
		if ( ! file_exists( $path ) ) {
			error_log( 'wp-search: Missing file ' . $file );
			continue;
		}
		require_once $path;
	}
	
	try {
		$wp_search_indexer = new WP_Search\Settings_Indexer();
		$wp_search_indexer->init();
	} catch ( \Throwable $e ) {
		error_log( 'wp-search: Settings_Indexer init failed: ' . $e->getMessage() );
	}
	
	try {
		$wp_search_menus = new WP_Search\Menus_Indexer();
		$wp_search_menus->init();
	} catch ( \Throwable $e ) {
		error_log( 'wp-search: Menus_Indexer init failed: ' . $e->getMessage() );
	}
	
	try {
		$wp_search_rest = new WP_Search\REST_Controller();
		$wp_search_rest->init();
	} catch ( \Throwable $e ) {
		error_log( 'wp-search: REST_Controller init failed: ' . $e->getMessage() );
	}
	
	try {
		$wp_search_admin = new WP_Search\Admin();
		$wp_search_admin->init();
	} catch ( \Throwable $e ) {
		error_log( 'wp-search: Admin init failed: ' . $e->getMessage() );
	}
}
add_action( 'plugins_loaded', 'wp_search_load' );

/**
 * Run on plugin activation.
 *
 * @since 1.0.0
 * @return void
 */
function wp_search_activate(): void {
	$files = array(
		'interface-spotlight-provider.php',
		'class-indexer.php',
		'class-settings-indexer.php',
		'class-menus-indexer.php',
	);
	
	foreach ( $files as $file ) {
		$path = WP_SEARCH_PLUGIN_DIR . 'includes/' . $file;
		if ( ! file_exists( $path ) ) {
			error_log( sprintf( 'wp-search: Missing file %s during activation', $file ) );
			continue;
		}
		require_once $path;
	}
	
	try {
		( new WP_Search\Settings_Indexer() )->reindex();
		( new WP_Search\Menus_Indexer() )->reindex();
	} catch ( \Throwable $e ) {
		error_log( 'wp-search activation error: ' . $e->getMessage() );
	}
}
register_activation_hook( WP_SEARCH_PLUGIN_FILE, 'wp_search_activate' );

/**
 * Run on plugin deactivation.
 *
 * @since 1.0.0
 * @return void
 */
function wp_search_deactivate(): void {
	$files = array(
		'interface-spotlight-provider.php',
		'class-indexer.php',
		'class-settings-indexer.php',
		'class-menus-indexer.php',
	);
	
	foreach ( $files as $file ) {
		$path = WP_SEARCH_PLUGIN_DIR . 'includes/' . $file;
		if ( ! file_exists( $path ) ) {
			continue;
		}
		require_once $path;
	}
	
	if ( class_exists( 'WP_Search\Settings_Indexer' ) ) {
		delete_transient( WP_Search\Settings_Indexer::INDEX_TRANSIENT_KEY );
	}
	
	if ( class_exists( 'WP_Search\Menus_Indexer' ) ) {
		delete_transient( WP_Search\Menus_Indexer::INDEX_TRANSIENT_KEY );
	}
}
register_deactivation_hook( WP_SEARCH_PLUGIN_FILE, 'wp_search_deactivate' );
