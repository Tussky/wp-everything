<?php
/**
 * Plugin Name:       Spotlight Search
 * Plugin URI:        https://example.com/spotlight-search
 * Description:       An Apple Spotlight–style search across WordPress users, plugins, options, and settings. Vanilla JS, no build step.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Northwind
 * License:           GPL-2.0-or-later
 * Text Domain:       wpss
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WPSS_VERSION', '1.0.0' );
define( 'WPSS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register the admin menu entry that hosts the Spotlight screen.
 */
add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'Spotlight Search', 'wpss' ),
		__( 'Spotlight', 'wpss' ),
		'manage_options',
		'wpss-spotlight',
		'wpss_render_admin_page',
		'dashicons-search',
		3
	);
} );

/**
 * Render the mount point. The vanilla JS bootstraps itself into #wpss-root.
 */
function wpss_render_admin_page() {
	echo '<div id="wpss-root"></div>';
}

/**
 * Enqueue the front-end assets only on the Spotlight admin screen.
 *
 * @param string $hook Current admin page hook suffix.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_wpss-spotlight' !== $hook ) {
		return;
	}

	// A single JS file — it injects its own styles, so no stylesheet enqueue.
	wp_enqueue_script(
		'wpss-spotlight',
		WPSS_URL . 'assets/spotlight.js',
		array(),
		WPSS_VERSION,
		true
	);

	/*
	 * The demo ships with self-contained sample data inside spotlight.js so the
	 * UI is fully explorable out of the box. To wire real data, replace the
	 * `window.WPSS_DATA` fallback in spotlight.js with a localized payload:
	 *
	 * wp_localize_script( 'wpss-spotlight', 'WPSS_DATA', array(
	 *     'users'    => wpss_collect_users(),
	 *     'plugins'  => wpss_collect_plugins(),
	 *     'options'  => wpss_collect_options(),
	 *     'settings' => wpss_collect_settings(),
	 * ) );
	 */
} );
