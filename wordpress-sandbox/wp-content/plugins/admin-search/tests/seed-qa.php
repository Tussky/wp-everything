<?php
/**
 * IA-53 — Seed sandbox fixtures.
 *
 * Run via: wp eval-file /var/www/html/wp-content/plugins/admin-search/tests/seed-qa.php
 *
 * Creates the IA-53 fixture set so the admin-search indexer has at least one
 * record per available source type (settings/users/content). Products source is
 * skipped because WooCommerce is not installed in the sandbox.
 *
 * All seeded items share the substring `qafixturedemo` so a single
 * ?q=qafixturedemo search returns at least one hit per available source type.
 *
 * @package AdminSearch
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "seed-qa.php must be run via wp eval-file\n" );
	exit( 1 );
}

$fixture = 'qafixturedemo';

// Posts.
$posts = array(
	array( 'title' => 'Welcome to the demo', 'content' => "$fixture. Quick walkthrough of the plugin and what it indexes." ),
	array( 'title' => 'Pricing update',        'content' => "$fixture. New tiers land next week, plus a free read-only mode." ),
	array( 'title' => 'Release notes',         'content' => "$fixture. v0.1.0 ships with indexer, REST, and the read-only UI scaffold." ),
);
foreach ( $posts as $p ) {
	$existing = get_page_by_title( $p['title'], OBJECT, 'post' );
	if ( $existing ) {
		WP_CLI::line( "post exists: {$p['title']} (#{$existing->ID})" );
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_title'   => $p['title'],
			'post_content' => $p['content'],
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		)
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'post create failed (' . $p['title'] . '): ' . $id->get_error_message() );
	} else {
		WP_CLI::line( "post created: {$p['title']} (#{$id})" );
	}
}

// Pages.
$pages = array( 'About', 'Contact', 'Roadmap' );
foreach ( $pages as $t ) {
	$existing = get_page_by_title( $t, OBJECT, 'page' );
	if ( $existing ) {
		WP_CLI::line( "page exists: {$t} (#{$existing->ID})" );
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_title'   => $t,
			'post_content' => "$fixture. Sample page: $t.",
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => 1,
		)
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "page create failed ($t): " . $id->get_error_message() );
	} else {
		WP_CLI::line( "page created: $t (#{$id})" );
	}
}

// 2 admin users with distinct display names.
$users = array(
	array(
		'login'   => 'sam_editor',
		'email'   => 'sam@example.com',
		'display' => 'Sam Editor',
	),
	array(
		'login'   => 'mia_author',
		'email'   => 'mia@example.com',
		'display' => 'Mia Author',
	),
);
foreach ( $users as $u ) {
	$existing = get_user_by( 'login', $u['login'] );
	if ( $existing ) {
		// Update display name in case it drifted.
		wp_update_user(
			array(
				'ID'           => (int) $existing->ID,
				'display_name' => $u['display'],
			)
		);
		WP_CLI::line( "user exists: {$u['login']} (#{$existing->ID}) display refreshed to {$u['display']}" );
		continue;
	}
	$id = wp_insert_user(
		array(
			'user_login'   => $u['login'],
			'user_email'   => $u['email'],
			'display_name' => $u['display'],
			'user_pass'    => wp_generate_password( 20, true, true ),
			'role'         => 'administrator',
		)
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'user create failed (' . $u['login'] . '): ' . $id->get_error_message() );
	} else {
		WP_CLI::line( "user created: {$u['login']} (#{$id}) display={$u['display']}" );
	}
}

// Align the fixture query option so smoke-rest.php and AC#1 share one fixture word.
update_option( 'as_fixture_query', $fixture );
WP_CLI::line( "as_fixture_query option updated to: $fixture" );

WP_CLI::success( 'seed-qa done.' );