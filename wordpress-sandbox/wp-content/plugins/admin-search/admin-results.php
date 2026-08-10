<?php
global $wp_version;

$admin_results = array(

	array(
		'q' => array(
			__( 'Homepage', 'admin-search' ),
			__( 'Main page', 'admin-search' )
		),
		'url' => admin_url( null ),
		'title' => __( 'Dashboard', 'admin-search' ),
		'status' => NULL,
		'capability' => 'read'
	),

	array(
		'q' => array(
			__( 'Plugin updates', 'admin-search' ),
			__( 'Version', 'admin-search' )
		),
		'url' => admin_url( 'update-core.php' ),
		'title' => __( 'Updates', 'admin-search' ),
		'status' => NULL,
		'capability' => 'update_core'
	),

	array(
		'q' => array(
			__( 'Extensions', 'admin-search' ),
			__( 'Add ons', 'admin-search' )
		),
		'url' => plugins_url(),
		'title' => __( 'Plugins', 'admin-search' ),
		'status' => NULL,
		'capability' => 'activate_plugins'
	),

	array(
		'q' => array(
			__( 'Site title', 'admin-search' ),
			__( 'Tagline', 'admin-search' ),
			__( 'WordPress address (URL)', 'admin-search' ),
			__( 'Site address (URL)', 'admin-search' ),
			__( 'Administration email address', 'admin-search' ),
			__( 'Change email address', 'admin-search' ),
			__( 'Membership', 'admin-search' ),
			__( 'New user default role', 'admin-search' ),
			__( 'Site language', 'admin-search' ),
			__( 'Timezone', 'admin-search' ),
			__( 'Date format', 'admin-search' ),
			__( 'Time format', 'admin-search' ),
			__( 'Week starts on', 'admin-search' )
		),
		'url' => admin_url( 'options-general.php' ),
		'title' => __( 'General settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Writing settings', 'admin-search' ),
			__( 'Writing options', 'admin-search' ),
			__( 'Default post category', 'admin-search' ),
			__( 'Default post format', 'admin-search' ),
			__( 'Post email address', 'admin-search' ),
			__( 'Mail server', 'admin-search' ),
			__( 'Login name', 'admin-search' ),
			__( 'Password', 'admin-search' ),
			__( 'Default mail category', 'admin-search' ),
			__( 'Update services', 'admin-search' )
		),
		'url' => admin_url( 'options-writing.php' ),
		'title' => __( 'Writing settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Your homepage displays', 'admin-search' ),
			__( 'Blog pages show at most', 'admin-search' ),
			__( 'Syndication feeds show the most recent', 'admin-search' ),
			__( 'For each post in a feed, include', 'admin-search' ),
			__( 'Search engine visibility', 'admin-search' )
		),
		'url' => admin_url( 'options-reading.php' ),
		'title' => __( 'Reading settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Default post settings', 'admin-search' ), 
			__( 'Other comment settings', 'admin-search' ),
			__( 'Email me whenever', 'admin-search' ),
			__( 'Before a comment appears', 'admin-search' ),
			__( 'Comment moderation', 'admin-search' ),
			__( 'Comment blacklist', 'admin-search' ),
			__( 'Avatars', 'admin-search' ),
			__( 'Avatar display', 'admin-search' ),
			__( 'Maximum rating', 'admin-search' ),
			__( 'Default avatar', 'admin-search' ),
			'Gravatar'
		),
		'url' => admin_url( 'options-discussion.php' ),
		'title' => __( 'Discussion settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Image sizes', 'admin-search' ),
			__( 'Thumbnail sizes', 'admin-search' ),
			__( 'Medium size', 'admin-search' ),
			__( 'Large size', 'admin-search' ),
			__( 'Uploading files', 'admin-search' )
		),
		'url' => admin_url( 'options-media.php' ),
		'title' => __( 'Media settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Category base', 'admin-search' ),
			__( 'Tag base', 'admin-search' )
		),
		'url' => admin_url( 'options-permalink.php' ),
		'title' => __( 'Permalink settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Privacy policy page', 'admin-search' ),
			__( 'Change your privacy policy page', 'admin-search' )
		),
		'url' => admin_url( 'options-privacy.php' ),
		'title' => __( 'Privacy settings', 'admin-search' ),
		'status' => NULL,
		'capability' => 'manage_options'
	),

	array(
		'q' => array(
			__( 'Add file', 'admin-search' ),
			__( 'Add image', 'admin-search' ),
			__( 'Upload image', 'admin-search' ),
			__( 'Upload file', 'admin-search' )
		),
		'url' => admin_url( 'media-new.php' ),
		'title' => __( 'Upload file', 'admin-search' ),
		'status' => NULL,
		'capability' => 'upload_files'
	),

	array(
		'q' => array(
			__( 'Add user', 'admin-search' )
		),
		'url' => admin_url( 'user-new.php' ),
		'title' => __( 'Add new user', 'admin-search' ),
		'status' => NULL,
		'capability' => 'create_users'
	),

	array(
		'q' => array(
			__( 'Edit profile', 'admin-search' ),
			__( 'Manage profile', 'admin-search' ),
			__( 'Update profile', 'admin-search' ),
			__( 'Change email address', 'admin-search' ),
			__( 'Change password', 'admin-search' ),
			__( 'Update email address', 'admin-search' ),
			__( 'Update password', 'admin-search' )
		),
		'url' => admin_url( 'profile.php' ),
		'title' => __( 'Edit profile', 'admin-search' ),
		'status' => NULL,
		'capability' => 'read'
	),

	array(
		'q' => array(
			__( 'what version of WordPress am I running?', 'admin-search' ),
			__( 'what version of WordPress is on this site?', 'admin-search' )
		),

		/* translators: %s: the version of WordPress currently installed */
		'title' => sprintf( __( 'The installed version of WordPress is %s', 'admin-search' ), $wp_version ),
		'status' => NULL,
		'capability' => 'update_core'
	),

);
