<?php
/**
 * Settings Indexer
 *
 * Indexes every page under the WordPress Settings menu and every field on
 * those pages. Discovery runs in four layers, merged and deduplicated:
 *
 *   1. Menu        `$submenu['options-general.php']` enumerates every page
 *                  under Settings, core and plugin alike.
 *   2. Settings API `get_registered_settings()` plus `$wp_settings_sections`
 *                  and `$wp_settings_fields` yield every field registered
 *                  through `register_setting()` / `add_settings_field()`.
 *   3. Core map    Core's options-*.php screens print their own markup rather
 *                  than registering fields, so their controls come from a map
 *                  mirroring core's `$allowed_options`.
 *   4. Crawl       Plugin pages that print raw HTML are rendered into an
 *                  output buffer and their form controls parsed out. This is
 *                  the backstop for everything the first three layers miss.
 *
 * Layers 1, 2 and 4 need the admin bootstrap, so the index is rebuilt on
 * `admin_init` at the lowest priority — after every plugin has registered its
 * menus and settings — and cached in a transient that REST and WP-CLI read.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes WordPress admin settings.
 *
 * @since 1.0.0
 */
class Settings_Indexer extends Indexer implements Spotlight_Provider {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'settings';

	/**
	 * Transient key used to cache the settings index.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const INDEX_TRANSIENT_KEY = 'wp_search_settings_index';

	/**
	 * Default index time-to-live in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_TTL = 86400;

	/**
	 * Build-time ceiling on indexed records.
	 *
	 * Applied while building, never while querying: a query-time cap would
	 * make records past the limit permanently unreachable no matter what the
	 * user types. The per-facet response cap lives in Spotlight::FACET_CAP and
	 * is applied *after* matching.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_INDEX_RECORDS = 1500;

	/**
	 * Maximum number of controls harvested from a single crawled page.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_CRAWLED_FIELDS_PER_PAGE = 200;

	/**
	 * Maximum number of secondary tabs followed on one Settings page.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_TABS_PER_PAGE = 10;

	/**
	 * Maximum snippet length in bytes.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_SNIPPET_LENGTH = 1200;

	/**
	 * Guards against a crawled page callback re-entering the crawler.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static bool $crawling = false;

	/**
	 * Core Settings screens: slug => [title, settings-API group].
	 *
	 * Used as the fallback page list when the admin menu globals are not
	 * populated (REST, WP-CLI), and to map a core slug onto the option group
	 * that `register_setting()` files its fields under.
	 *
	 * @since 1.0.0
	 * @var array<string, array{title: string, group: string}>
	 */
	private array $core_pages = array(
		'options-general.php'    => array(
			'title' => 'General',
			'group' => 'general',
		),
		'options-writing.php'    => array(
			'title' => 'Writing',
			'group' => 'writing',
		),
		'options-reading.php'    => array(
			'title' => 'Reading',
			'group' => 'reading',
		),
		'options-discussion.php' => array(
			'title' => 'Discussion',
			'group' => 'discussion',
		),
		'options-media.php'      => array(
			'title' => 'Media',
			'group' => 'media',
		),
		'options-permalink.php'  => array(
			'title' => 'Permalinks',
			'group' => 'permalink',
		),
		'options-privacy.php'    => array(
			'title' => 'Privacy',
			'group' => 'privacy',
		),
	);

	/**
	 * Every control on core's Settings screens.
	 *
	 * Mirrors `$allowed_options` in wp-admin/options.php plus the controls core
	 * saves outside that list (permalinks, the editor toggle). Core prints this
	 * markup directly instead of registering fields, so it cannot be discovered
	 * from the Settings API and has to be enumerated here.
	 *
	 * @since 1.0.0
	 * @var array<string, array<string, array{label: string, description: string, type: string, class: string, alias?: string}>>
	 */
	private array $core_fields = array(
		'options-general.php'    => array(
			'blogname'           => array(
				'label'       => 'Site Title',
				'description' => 'The name of your site, shown in the header and browser tab.',
				'type'        => 'text',
				'class'       => 'regular-text',
			),
			'blogdescription'    => array(
				'label'       => 'Tagline',
				'description' => 'In a few words, explain what this site is about.',
				'type'        => 'text',
				'class'       => 'regular-text',
			),
			'site_icon'          => array(
				'label'       => 'Site Icon',
				'description' => 'The Site Icon is what you see in browser tabs, bookmark bars, and within the WordPress mobile apps. Favicon.',
				'type'        => 'text',
				'class'       => '',
			),
			'siteurl'            => array(
				'label'       => 'WordPress Address (URL)',
				'description' => 'The address where your WordPress core files live.',
				'type'        => 'url',
				'class'       => 'regular-text code',
			),
			'home'               => array(
				'label'       => 'Site Address (URL)',
				'description' => 'The address visitors type to reach your site.',
				'type'        => 'url',
				'class'       => 'regular-text code',
			),
			'admin_email'        => array(
				'label'       => 'Administration Email Address',
				'description' => 'This address is used for admin purposes. Changing it sends a confirmation email to the new address.',
				'type'        => 'email',
				'class'       => 'regular-text ltr',
				'alias'       => 'new_admin_email',
			),
			'users_can_register' => array(
				'label'       => 'Membership: Anyone can register',
				'description' => 'Allow visitors to create their own accounts.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'default_role'       => array(
				'label'       => 'New User Default Role',
				'description' => 'The role assigned to users who register on this site.',
				'type'        => 'select',
				'class'       => '',
			),
			'WPLANG'             => array(
				'label'       => 'Site Language',
				'description' => 'The language this site is displayed in.',
				'type'        => 'select',
				'class'       => '',
			),
			'timezone_string'    => array(
				'label'       => 'Timezone',
				'description' => 'Choose either a city in the same timezone as you or a UTC time offset. Also stored as gmt_offset.',
				'type'        => 'select',
				'class'       => '',
				'alias'       => 'gmt_offset',
			),
			'date_format'        => array(
				'label'       => 'Date Format',
				'description' => 'How dates are displayed across the site.',
				'type'        => 'text',
				'class'       => 'small-text',
			),
			'time_format'        => array(
				'label'       => 'Time Format',
				'description' => 'How times are displayed across the site.',
				'type'        => 'text',
				'class'       => 'small-text',
			),
			'start_of_week'      => array(
				'label'       => 'Week Starts On',
				'description' => 'The first day of the week for calendars.',
				'type'        => 'select',
				'class'       => '',
			),
		),
		'options-writing.php'    => array(
			'default_category'       => array(
				'label'       => 'Default Post Category',
				'description' => 'Category assigned to posts that specify none.',
				'type'        => 'select',
				'class'       => '',
			),
			'default_post_format'    => array(
				'label'       => 'Default Post Format',
				'description' => 'Post format assigned to new posts.',
				'type'        => 'select',
				'class'       => '',
			),
			'default_link_category'  => array(
				'label'       => 'Default Link Category',
				'description' => 'Category assigned to new links.',
				'type'        => 'select',
				'class'       => '',
			),
			'default_editor'         => array(
				'label'       => 'Default Editor for All Users',
				'description' => 'Choose the block editor or the classic editor.',
				'type'        => 'radio',
				'class'       => '',
			),
			'use_smilies'            => array(
				'label'       => 'Formatting: Convert emoticons to graphics',
				'description' => 'Convert emoticons like :-) and :-P to graphics on display.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'use_balanceTags'        => array(
				'label'       => 'Correct invalidly nested XHTML automatically',
				'description' => 'WordPress should correct invalidly nested XHTML automatically.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'ping_sites'             => array(
				'label'       => 'Update Services',
				'description' => 'Sites WordPress notifies when you publish a new post.',
				'type'        => 'textarea',
				'class'       => 'large-text code',
			),
			'mailserver_url'         => array(
				'label'       => 'Post via email: Mail Server',
				'description' => 'The POP3 server WordPress checks for posts sent by email.',
				'type'        => 'text',
				'class'       => 'regular-text code',
			),
			'mailserver_port'        => array(
				'label'       => 'Post via email: Port',
				'description' => 'Port used to reach the mail server.',
				'type'        => 'text',
				'class'       => 'small-text',
			),
			'mailserver_login'       => array(
				'label'       => 'Post via email: Login Name',
				'description' => 'Mailbox account WordPress reads posts from.',
				'type'        => 'text',
				'class'       => 'regular-text ltr',
			),
			'mailserver_pass'        => array(
				'label'       => 'Post via email: Password',
				'description' => 'Password for the post-by-email mailbox.',
				'type'        => 'password',
				'class'       => 'regular-text ltr',
			),
			'default_email_category' => array(
				'label'       => 'Post via email: Default Mail Category',
				'description' => 'Category assigned to posts published by email.',
				'type'        => 'select',
				'class'       => '',
			),
		),
		'options-reading.php'    => array(
			'show_on_front'   => array(
				'label'       => 'Your homepage displays',
				'description' => 'Show your latest posts or a static page.',
				'type'        => 'radio',
				'class'       => '',
			),
			'page_on_front'   => array(
				'label'       => 'Homepage',
				'description' => 'The static page used as the homepage.',
				'type'        => 'select',
				'class'       => '',
			),
			'page_for_posts'  => array(
				'label'       => 'Posts page',
				'description' => 'The static page that lists your blog posts.',
				'type'        => 'select',
				'class'       => '',
			),
			'posts_per_page'  => array(
				'label'       => 'Blog pages show at most',
				'description' => 'posts',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'posts_per_rss'   => array(
				'label'       => 'Syndication feeds show the most recent',
				'description' => 'items',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'rss_use_excerpt' => array(
				'label'       => 'For each post in a feed, include',
				'description' => 'Full text or excerpt.',
				'type'        => 'radio',
				'class'       => '',
			),
			'blog_public'     => array(
				'label'       => 'Search engine visibility',
				'description' => 'Discourage search engines from indexing this site.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'blog_charset'    => array(
				'label'       => 'Encoding for pages and feeds',
				'description' => 'Character encoding used for pages and feeds.',
				'type'        => 'text',
				'class'       => 'small-text',
			),
		),
		'options-discussion.php' => array(
			'default_pingback_flag'        => array(
				'label'       => 'Attempt to notify any blogs linked to from the post',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'default_ping_status'          => array(
				'label'       => 'Allow link notifications from other blogs (pingbacks and trackbacks) on new posts',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'default_comment_status'       => array(
				'label'       => 'Allow people to submit comments on new posts',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'require_name_email'           => array(
				'label'       => 'Comment author must fill out name and email',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'comment_registration'         => array(
				'label'       => 'Users must be registered and logged in to comment',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'close_comments_for_old_posts' => array(
				'label'       => 'Automatically close comments on old posts',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'close_comments_days_old'      => array(
				'label'       => 'Close comments on posts older than',
				'description' => 'days',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'show_comments_cookies_opt_in' => array(
				'label'       => 'Show comments cookies opt-in checkbox',
				'description' => 'Lets comment author cookies be set.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'thread_comments'              => array(
				'label'       => 'Enable threaded (nested) comments',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'thread_comments_depth'        => array(
				'label'       => 'Threaded comments levels deep',
				'description' => 'levels deep',
				'type'        => 'select',
				'class'       => '',
			),
			'page_comments'                => array(
				'label'       => 'Break comments into pages',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'comments_per_page'            => array(
				'label'       => 'Top level comments per page',
				'description' => 'comments per page',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'default_comments_page'        => array(
				'label'       => 'Page displayed by default',
				'description' => 'Last page or first page of comments.',
				'type'        => 'select',
				'class'       => '',
			),
			'comment_order'                => array(
				'label'       => 'Comments should be displayed with the older or newer comments at the top',
				'description' => '',
				'type'        => 'select',
				'class'       => '',
			),
			'comments_notify'              => array(
				'label'       => 'Email me whenever anyone posts a comment',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'moderation_notify'            => array(
				'label'       => 'Email me whenever a comment is held for moderation',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'comment_moderation'           => array(
				'label'       => 'Comment must be manually approved before it appears',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'comment_previously_approved'  => array(
				'label'       => 'Comment author must have a previously approved comment',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'comment_max_links'            => array(
				'label'       => 'Hold a comment in the queue if it contains links',
				'description' => 'or more links',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'moderation_keys'              => array(
				'label'       => 'Disallowed Comment Keys: hold for moderation',
				'description' => 'Words, names, URLs, emails or IPs that send a comment to the moderation queue.',
				'type'        => 'textarea',
				'class'       => 'large-text code',
			),
			'disallowed_keys'              => array(
				'label'       => 'Disallowed Comment Keys',
				'description' => 'Words, names, URLs, emails or IPs that put a comment straight in the trash.',
				'type'        => 'textarea',
				'class'       => 'large-text code',
			),
			'show_avatars'                 => array(
				'label'       => 'Avatar Display: Show avatars',
				'description' => 'Show avatars alongside comments.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'avatar_rating'                => array(
				'label'       => 'Maximum Rating',
				'description' => 'Highest avatar rating shown: G, PG, R or X.',
				'type'        => 'radio',
				'class'       => '',
			),
			'avatar_default'               => array(
				'label'       => 'Default Avatar',
				'description' => 'Avatar shown for users without a custom one: Mystery Person, Gravatar Logo, Identicon, Wavatar, MonsterID, Retro.',
				'type'        => 'radio',
				'class'       => '',
			),
		),
		'options-media.php'      => array(
			'thumbnail_size_w'             => array(
				'label'       => 'Thumbnail size width',
				'description' => 'pixels',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'thumbnail_size_h'             => array(
				'label'       => 'Thumbnail size height',
				'description' => 'pixels',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'thumbnail_crop'               => array(
				'label'       => 'Crop thumbnail to exact dimensions',
				'description' => 'Normally thumbnails are proportional.',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'medium_size_w'                => array(
				'label'       => 'Medium size max width',
				'description' => 'pixels',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'medium_size_h'                => array(
				'label'       => 'Medium size max height',
				'description' => 'pixels',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'large_size_w'                 => array(
				'label'       => 'Large size max width',
				'description' => 'pixels',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'large_size_h'                 => array(
				'label'       => 'Large size max height',
				'description' => 'pixels',
				'type'        => 'number',
				'class'       => 'small-text',
			),
			'uploads_use_yearmonth_folders' => array(
				'label'       => 'Uploading Files: organize my uploads into month- and year-based folders',
				'description' => '',
				'type'        => 'checkbox',
				'class'       => '',
			),
			'upload_path'                  => array(
				'label'       => 'Store uploads in this folder',
				'description' => 'Default is wp-content/uploads.',
				'type'        => 'text',
				'class'       => 'regular-text code',
			),
			'upload_url_path'              => array(
				'label'       => 'Full URL path to files',
				'description' => 'Configuring this is optional. By default, it should be blank.',
				'type'        => 'text',
				'class'       => 'regular-text code',
			),
			'image_default_size'           => array(
				'label'       => 'Default image size',
				'description' => '',
				'type'        => 'select',
				'class'       => '',
			),
			'image_default_align'          => array(
				'label'       => 'Default image alignment',
				'description' => '',
				'type'        => 'select',
				'class'       => '',
			),
			'image_default_link_type'      => array(
				'label'       => 'Default image link type',
				'description' => '',
				'type'        => 'select',
				'class'       => '',
			),
		),
		'options-permalink.php'  => array(
			'permalink_structure' => array(
				'label'       => 'Custom Structure',
				'description' => 'Enter a custom structure for your permalink URLs. Plain, day and name, month and name, numeric, post name.',
				'type'        => 'text',
				'class'       => 'regular-text code',
			),
			'category_base'       => array(
				'label'       => 'Category base',
				'description' => 'Optional prefix for category archive URLs.',
				'type'        => 'text',
				'class'       => 'regular-text code',
			),
			'tag_base'            => array(
				'label'       => 'Tag base',
				'description' => 'Optional prefix for tag archive URLs.',
				'type'        => 'text',
				'class'       => 'regular-text code',
			),
		),
		'options-privacy.php'    => array(
			'wp_page_for_privacy_policy' => array(
				'label'       => 'Change your Privacy Policy page',
				'description' => 'Select an existing page to serve as your privacy policy.',
				'type'        => 'select',
				'class'       => '',
			),
		),
	);

	/**
	 * Substrings that mark a field as secret-bearing.
	 *
	 * A match suppresses the live value from both the snippet and the search
	 * terms, so indexing a settings page never turns into a credential dump.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	private array $sensitive_markers = array(
		'pass',
		'pwd',
		'secret',
		'token',
		'api_key',
		'apikey',
		'api-key',
		'private_key',
		'privatekey',
		'access_key',
		'auth_key',
		'salt',
		'nonce',
		'license',
		'credential',
	);

	/**
	 * Stored record keys that are searchable by the legacy search() method.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	private array $search_fields = array( 'fieldId', 'fieldLabel', 'sectionTitle', 'pageTitle', 'snippetText' );

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_schedule_reindex' ) );
		// Lowest priority: every plugin has registered its menus (admin_menu,
		// which core fires before admin_init) and its settings by this point.
		add_action( 'admin_init', array( $this, 'maybe_build_index' ), PHP_INT_MAX );

		/*
		 * The index is a snapshot of which pages exist under Settings, so it
		 * has to be dropped whenever that set can have changed. Without this a
		 * newly activated plugin's Settings page stays unfindable until the
		 * TTL lapses.
		 */
		foreach ( array( 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'switch_theme' ) as $event ) {
			add_action( $event, array( $this, 'invalidate' ) );
		}
	}

	/**
	 * Drop the cached index so the next admin request rebuilds it.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function invalidate(): void {
		delete_transient( self::INDEX_TRANSIENT_KEY );
	}

	/**
	 * Schedule a reindex when an admin trigger is received.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_schedule_reindex(): void {
		if ( ! is_admin() ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['wp_search_reindex_nonce'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_search_reindex' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to reindex settings.', 'wp-search' ) );
		}

		$this->reindex();

		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings index rebuilt.', 'wp-search' ) . '</p></div>';
			}
		);
	}

	/**
	 * Build the index automatically if it is missing or was built without the
	 * admin menu available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_build_index(): void {
		if ( ! is_admin() ) {
			return;
		}

		$index = get_transient( self::INDEX_TRANSIENT_KEY );
		if ( ! is_array( $index ) || empty( $index ) || $this->index_is_partial( $index ) ) {
			$this->reindex();
		}
	}

	/**
	 * Detect an index built outside the admin bootstrap.
	 *
	 * A cold REST or WP-CLI request can only reach the core-map layer, so the
	 * result is marked partial and rebuilt on the next admin page load rather
	 * than being cached as though it were complete.
	 *
	 * @since 1.0.0
	 * @param array<mixed> $index Cached index.
	 * @return bool
	 */
	private function index_is_partial( array $index ): bool {
		foreach ( $index as $record ) {
			if ( is_array( $record ) && ! empty( $record['partial'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build and cache the settings index.
	 *
	 * @since 1.0.0
	 * @return int Number of indexed records.
	 */
	public function reindex(): int {
		$index = $this->build_index();

		$index = apply_filters( 'wp_search_settings_index', $index );

		set_transient( self::INDEX_TRANSIENT_KEY, $index, absint( apply_filters( 'wp_search_index_ttl', self::DEFAULT_TTL ) ) );

		return count( $index );
	}

	/**
	 * Retrieve the cached index or rebuild it.
	 *
	 * @since 1.0.0
	 * @return array<mixed> List of indexed settings records.
	 */
	public function get_index(): array {
		$index = get_transient( self::INDEX_TRANSIENT_KEY );

		if ( ! is_array( $index ) || empty( $index ) ) {
			$index = array();
			$this->reindex();
			$index = get_transient( self::INDEX_TRANSIENT_KEY );
		}

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Return the source label for these results.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_source(): string {
		return self::SOURCE;
	}

	/**
	 * Return the cached settings index as spotlight records.
	 *
	 * The whole index is returned: matching and the per-facet cap are applied
	 * downstream by Spotlight, so a field is reachable no matter where it sits
	 * in the index.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$index = $this->get_index();
		if ( ! is_array( $index ) ) {
			return array();
		}

		$records = array();
		$counter = 0;

		foreach ( $index as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			// Honour the capability the page itself was registered with.
			$cap = (string) ( $record['cap'] ?? '' );
			if ( '' !== $cap && 'manage_options' !== $cap && ! current_user_can( $cap ) ) {
				continue;
			}

			$counter++;

			$source      = (string) ( $record['source'] ?? __( 'WordPress Core', 'wp-search' ) );
			$source_kind = (string) ( $record['sourceKind'] ?? 'core' );
			$breadcrumb  = is_array( $record['breadcrumb'] ?? null ) ? $record['breadcrumb'] : array( __( 'Settings', 'wp-search' ), (string) ( $record['pageTitle'] ?? '' ) );
			$language    = (string) ( $record['language'] ?? 'html' );
			$snippet      = (string) ( $record['snippet'] ?? '' );
			$snippet_text = wp_strip_all_tags( $snippet );
			$url          = (string) ( $record['url'] ?? '' );

			$terms = array_filter(
				array_unique(
					array_merge(
						array( $source ),
						$breadcrumb,
						array( $snippet_text ),
						array_filter(
							array(
								(string) ( $record['pageTitle'] ?? '' ),
								(string) ( $record['sectionTitle'] ?? '' ),
								(string) ( $record['fieldId'] ?? '' ),
								(string) ( $record['fieldLabel'] ?? '' ),
								(string) ( $record['alias'] ?? '' ),
								(string) ( $record['description'] ?? '' ),
							)
						)
					)
				)
			);

			$records[] = array(
				'id'      => 's-' . $counter,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => array_values( array_map( 'strval', $terms ) ),
					'weight' => (int) ( $record['weight'] ?? 70 ),
				),
				'display' => array(
					'source'     => $source,
					'sourceKind' => $source_kind,
					'breadcrumb' => $breadcrumb,
					'language'   => $language,
					'snippet'    => $snippet,
					'url'        => $url,
				),
			);
		}

		return $records;
	}

	/**
	 * Search the cached settings index.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		$index   = $this->get_index();
		$results = array();
		$term    = $this->normalize_query( $query );

		foreach ( $index as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			if ( '' === $term ) {
				$results[] = $this->normalize_record( $record );
				continue;
			}

			$haystack = '';
			foreach ( $this->search_fields as $field ) {
				$haystack .= ' ' . (string) ( $record[ $field ] ?? '' );
			}

			if ( false !== strpos( $this->normalize_query( $haystack ), $term ) ) {
				$results[] = $this->normalize_record( $record );
			}
		}

		return $results;
	}

	/**
	 * Run every discovery layer and merge the result into one index.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	private function build_index(): array {
		$pages   = $this->discover_pages();
		$records = array();
		$seen    = array();

		/*
		 * Layers run richest-first and a field is claimed by the first layer
		 * that finds it, keyed on page + field rather than on the layer's own
		 * sort key -- otherwise a field both registered and rendered (layer 2
		 * and layer 4) would be indexed twice and show up as a duplicate hit.
		 */
		$layers = array(
			$this->page_records( $pages ),
			$this->core_field_records( $pages ),
			$this->registered_field_records( $pages ),
			$this->crawled_field_records( $pages ),
		);

		foreach ( $layers as $layer ) {
			foreach ( $layer as $record ) {
				$identity = $record['pageSlug'] . '|' . $record['fieldId'];

				if ( isset( $seen[ $identity ] ) ) {
					// A rendered control is the real markup, so it upgrades the
					// synthesized snippet an earlier layer stored for the same
					// field while that layer keeps its richer metadata.
					$claimed = $seen[ $identity ];
					if ( 'crawled' === $record['sectionId'] && 'crawled' !== $records[ $claimed ]['sectionId'] ) {
						$records[ $claimed ]['snippet']     = $record['snippet'];
						$records[ $claimed ]['snippetText'] = $record['snippetText'];
						$records[ $claimed ]['language']    = $record['language'];
						$records[ $claimed ]['url']         = $record['url'];
					}
					continue;
				}

				$seen[ $identity ]        = $record['key'];
				$records[ $record['key'] ] = $record;
			}
		}

		ksort( $records, SORT_NATURAL );

		$index = array_values( $records );

		if ( count( $index ) > self::MAX_INDEX_RECORDS ) {
			$index = array_slice( $index, 0, self::MAX_INDEX_RECORDS );
		}

		return $index;
	}

	/**
	 * Layer 1 — every page under the Settings menu.
	 *
	 * Falls back to the core screen list when the admin menu globals are not
	 * populated, so a cold REST or WP-CLI build still returns core.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Keyed by page slug.
	 */
	private function discover_pages(): array {
		global $submenu;

		$pages   = array();
		$order   = 0;
		$partial = true;

		foreach ( $this->core_pages as $slug => $core ) {
			$order++;
			$pages[ $slug ] = array(
				'slug'    => $slug,
				'title'   => $core['title'],
				'group'   => $core['group'],
				'kind'    => 'core',
				'cap'     => 'manage_options',
				'order'   => $order,
				'partial' => true,
			);
		}

		if ( ! empty( $submenu['options-general.php'] ) && is_array( $submenu['options-general.php'] ) ) {
			$partial = false;

			foreach ( $submenu['options-general.php'] as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry[2] ) ) {
					continue;
				}

				$slug  = (string) $entry[2];
				$title = wp_strip_all_tags( (string) ( $entry[0] ?? $slug ) );
				$cap   = (string) ( $entry[1] ?? 'manage_options' );

				if ( isset( $pages[ $slug ] ) ) {
					// Core screen: keep the group mapping, take the live title.
					$pages[ $slug ]['title']   = '' !== $title ? $title : $pages[ $slug ]['title'];
					$pages[ $slug ]['cap']     = $cap;
					$pages[ $slug ]['partial'] = false;
					continue;
				}

				$order++;
				$pages[ $slug ] = array(
					'slug'    => $slug,
					'title'   => '' !== $title ? $title : $slug,
					'group'   => $slug,
					'kind'    => 'plugin',
					'cap'     => $cap,
					'order'   => $order,
					'partial' => false,
				);
			}
		}

		if ( ! $partial ) {
			foreach ( $pages as $slug => $page ) {
				$pages[ $slug ]['partial'] = false;
			}
		}

		/**
		 * Filters the Settings pages the indexer walks.
		 *
		 * @since 1.0.0
		 * @param array<string, array<string, mixed>> $pages Keyed by page slug.
		 */
		$pages = apply_filters( 'wp_search_settings_pages', $pages );

		return is_array( $pages ) ? $pages : array();
	}

	/**
	 * One record per Settings page, so the page itself is findable by name.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $pages Discovered pages.
	 * @return array<mixed>
	 */
	private function page_records( array $pages ): array {
		$records = array();

		foreach ( $pages as $slug => $page ) {
			$title   = (string) $page['title'];
			$snippet = '<h1>' . esc_html( $title ) . '</h1>' . "\n" . '<p class="description">' . esc_html( sprintf( 'Settings screen: %s', $title ) ) . '</p>';

			$records[] = $this->make_record(
				array(
					'key'          => sprintf( '%03d-page', (int) $page['order'] ),
					'page'         => $page,
					'sectionId'    => 'page',
					'sectionTitle' => $title,
					'fieldId'      => $slug,
					'fieldLabel'   => $title,
					'description'  => sprintf( 'WordPress Settings page %s', $title ),
					'snippet'      => $snippet,
					'weight'       => 85,
					'breadcrumb'   => array( __( 'Settings', 'wp-search' ), $title ),
				)
			);
		}

		return $records;
	}

	/**
	 * Layer 3 — core's own controls, which are printed rather than registered.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $pages Discovered pages.
	 * @return array<mixed>
	 */
	private function core_field_records( array $pages ): array {
		$records = array();

		foreach ( $this->core_fields as $slug => $fields ) {
			$page = $pages[ $slug ] ?? null;
			if ( null === $page ) {
				continue;
			}

			$index = 0;
			foreach ( $fields as $field_id => $field ) {
				$index++;
				$records[] = $this->make_record(
					array(
						'key'          => sprintf( '%03d-core-%04d', (int) $page['order'], $index ),
						'page'         => $page,
						'sectionId'    => 'default',
						'sectionTitle' => sprintf( '%s Settings', (string) $page['title'] ),
						'fieldId'      => $field_id,
						'fieldLabel'   => (string) $field['label'],
						'description'  => (string) $field['description'],
						'alias'        => (string) ( $field['alias'] ?? '' ),
						'snippet'      => $this->build_field_snippet( $field_id, $field ),
						'weight'       => 80,
						'breadcrumb'   => array( __( 'Settings', 'wp-search' ), (string) $page['title'] ),
					)
				);
			}
		}

		return $records;
	}

	/**
	 * Layer 2 — fields registered through the Settings API.
	 *
	 * Reads `get_registered_settings()` for option metadata and the
	 * `$wp_settings_sections` / `$wp_settings_fields` globals for the sections
	 * and fields plugins attach to a Settings screen.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $pages Discovered pages.
	 * @return array<mixed>
	 */
	private function registered_field_records( array $pages ): array {
		global $wp_settings_sections, $wp_settings_fields;

		$records    = array();
		$registered = function_exists( 'get_registered_settings' ) ? get_registered_settings() : array();
		$registered = is_array( $registered ) ? $registered : array();

		// Group name -> page slug, so a registered setting lands on its screen.
		$group_to_slug = array();
		foreach ( $pages as $slug => $page ) {
			$group_to_slug[ (string) $page['group'] ] = $slug;
		}

		foreach ( $registered as $option_name => $args ) {
			if ( ! is_array( $args ) ) {
				continue;
			}

			$group = (string) ( $args['group'] ?? '' );
			$slug  = $group_to_slug[ $group ] ?? null;
			if ( null === $slug || ! isset( $pages[ $slug ] ) ) {
				continue;
			}

			$page  = $pages[ $slug ];
			$label = (string) ( $args['label'] ?? '' );
			if ( '' === $label ) {
				$label = ucwords( str_replace( array( '_', '-' ), ' ', (string) $option_name ) );
			}

			$field = array(
				'label'       => $label,
				'description' => (string) ( $args['description'] ?? '' ),
				'type'        => $this->input_type_for( (string) ( $args['type'] ?? 'string' ) ),
				'class'       => 'regular-text',
			);

			$records[] = $this->make_record(
				array(
					'key'          => sprintf( '%03d-api-%s', (int) $page['order'], (string) $option_name ),
					'page'         => $page,
					'sectionId'    => 'default',
					'sectionTitle' => sprintf( '%s Settings', (string) $page['title'] ),
					'fieldId'      => (string) $option_name,
					'fieldLabel'   => $label,
					'description'  => (string) ( $args['description'] ?? '' ),
					'snippet'      => $this->build_field_snippet( (string) $option_name, $field ),
					'weight'       => 78,
					'breadcrumb'   => array( __( 'Settings', 'wp-search' ), (string) $page['title'] ),
				)
			);
		}

		if ( ! is_array( $wp_settings_fields ) ) {
			return $records;
		}

		foreach ( $pages as $slug => $page ) {
			// Settings API pages are keyed by the slug passed to
			// add_settings_field(), which is the menu slug for plugin screens
			// and the short group name for core screens.
			foreach ( array_unique( array( $slug, (string) $page['group'] ) ) as $page_key ) {
				if ( ! isset( $wp_settings_fields[ $page_key ] ) || ! is_array( $wp_settings_fields[ $page_key ] ) ) {
					continue;
				}

				foreach ( $wp_settings_fields[ $page_key ] as $section_id => $fields ) {
					if ( ! is_array( $fields ) ) {
						continue;
					}

					$section_title = '';
					if ( is_array( $wp_settings_sections ) && isset( $wp_settings_sections[ $page_key ][ $section_id ]['title'] ) ) {
						$section_title = wp_strip_all_tags( (string) $wp_settings_sections[ $page_key ][ $section_id ]['title'] );
					}
					if ( '' === $section_title ) {
						$section_title = sprintf( '%s Settings', (string) $page['title'] );
					}

					foreach ( $fields as $field_id => $field ) {
						if ( ! is_array( $field ) ) {
							continue;
						}

						$label = wp_strip_all_tags( (string) ( $field['title'] ?? '' ) );
						if ( '' === $label ) {
							$label = ucwords( str_replace( array( '_', '-' ), ' ', (string) $field_id ) );
						}

						$definition = array(
							'label'       => $label,
							'description' => '',
							'type'        => 'text',
							'class'       => 'regular-text',
						);

						$records[] = $this->make_record(
							array(
								'key'          => sprintf( '%03d-sec-%s-%s', (int) $page['order'], (string) $section_id, (string) $field_id ),
								'page'         => $page,
								'sectionId'    => (string) $section_id,
								'sectionTitle' => $section_title,
								'fieldId'      => (string) $field_id,
								'fieldLabel'   => $label,
								'description'  => '',
								'snippet'      => $this->build_field_snippet( (string) $field_id, $definition ),
								'weight'       => 76,
							)
						);
					}
				}
			}
		}

		return $records;
	}

	/**
	 * Layer 4 — render plugin Settings pages and harvest their form controls.
	 *
	 * Core screens are skipped: layer 3 already covers them exactly, and
	 * including wp-admin/options-*.php mid-request is not safe. Plugin screens
	 * are rendered by firing the hook `add_submenu_page()` registered their
	 * callback on, captured in an output buffer.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $pages Discovered pages.
	 * @return array<mixed>
	 */
	private function crawled_field_records( array $pages ): array {
		/**
		 * Filters whether Settings pages are rendered and parsed.
		 *
		 * @since 1.0.0
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'wp_search_settings_crawl', true ) ) {
			return array();
		}

		if ( self::$crawling || ! function_exists( 'do_action' ) || ! function_exists( 'has_action' ) ) {
			return array();
		}

		/*
		 * Never render a settings page while one is being submitted. Plugin
		 * page callbacks routinely process $_POST and redirect, and a crawl
		 * that fired mid-save could re-run a save handler or exit the request.
		 */
		if ( ! empty( $_POST ) || 'GET' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array();
		}

		$records = array();

		foreach ( $pages as $slug => $page ) {
			if ( 'plugin' !== $page['kind'] ) {
				continue;
			}

			$cap = (string) $page['cap'];
			if ( '' !== $cap && function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
				continue;
			}

			$html = $this->render_page( $slug );
			if ( '' === $html ) {
				continue;
			}

			foreach ( $this->parse_controls( $html, $page ) as $record ) {
				$records[] = $record;
			}

			/*
			 * A tabbed page prints only the active tab, so the other tabs'
			 * controls are invisible from a single render. Follow the tab
			 * links it just printed and render each one.
			 */
			foreach ( $this->discover_tabs( $html ) as $tab ) {
				$tab_html = $this->render_page( $slug, $tab );
				if ( '' === $tab_html ) {
					continue;
				}

				foreach ( $this->parse_controls( $tab_html, $page, $tab ) as $record ) {
					$records[] = $record;
				}
			}
		}

		return $records;
	}

	/**
	 * Collect the secondary tabs a rendered Settings page links to.
	 *
	 * Reads the `tab` query arg off the page's own nav-tab links, which is the
	 * convention core's admin CSS establishes and plugins follow.
	 *
	 * @since 1.0.0
	 * @param string $html Rendered markup of the page's default tab.
	 * @return array<string> Distinct tab values, excluding the default render.
	 */
	private function discover_tabs( string $html ): array {
		if ( ! class_exists( '\DOMDocument' ) || false === strpos( $html, 'tab=' ) ) {
			return array();
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array();
		}

		$xpath = new \DOMXPath( $dom );
		$links = $xpath->query( '//a[contains(concat(" ", normalize-space(@class), " "), " nav-tab ")]/@href | //a[contains(@href, "tab=")]/@href' );
		if ( false === $links ) {
			return array();
		}

		$tabs = array();
		foreach ( $links as $link ) {
			if ( count( $tabs ) >= self::MAX_TABS_PER_PAGE ) {
				break;
			}

			$href  = html_entity_decode( (string) $link->nodeValue );
			$query = (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $href, PHP_URL_QUERY ) : parse_url( $href, PHP_URL_QUERY ) );
			if ( '' === $query ) {
				continue;
			}

			$args = array();
			parse_str( $query, $args );

			$tab = isset( $args['tab'] ) ? (string) $args['tab'] : '';
			if ( '' === $tab || isset( $tabs[ $tab ] ) ) {
				continue;
			}

			$tabs[ $tab ] = true;
		}

		return array_keys( $tabs );
	}

	/**
	 * Render one plugin Settings page into a string.
	 *
	 * Any output buffer the callback leaves open is unwound, and any error it
	 * raises is swallowed: a broken third-party page must not break indexing
	 * or leak half-rendered markup into the response.
	 *
	 * @since 1.0.0
	 * @param string $slug Menu slug.
	 * @param string $tab  Optional value for the `tab` query arg.
	 * @return string Rendered markup, or '' when the page could not be rendered.
	 */
	private function render_page( string $slug, string $tab = '' ): string {
		$hookname = function_exists( 'get_plugin_page_hookname' )
			? get_plugin_page_hookname( $slug, 'options-general.php' )
			: 'settings_page_' . preg_replace( '/\.php$/', '', $slug );

		if ( '' === (string) $hookname || ! has_action( $hookname ) ) {
			return '';
		}

		$previous_page = $GLOBALS['plugin_page'] ?? null;
		$depth         = ob_get_level();
		$html          = '';

		/*
		 * Page callbacks routinely gate on $_GET['page'] before printing
		 * anything, so the crawl has to present the request state WordPress
		 * would have when actually serving this screen. Saved and restored
		 * below: the surrounding request keeps its own superglobals.
		 */
		$had_get           = array_key_exists( 'page', $_GET );
		$had_request       = array_key_exists( 'page', $_REQUEST );
		$had_tab           = array_key_exists( 'tab', $_GET );
		$previous_get      = $_GET['page'] ?? null;
		$previous_request  = $_REQUEST['page'] ?? null;
		$previous_tab      = $_GET['tab'] ?? null;

		self::$crawling         = true;
		$GLOBALS['plugin_page'] = $slug;
		$_GET['page']           = $slug;
		$_REQUEST['page']       = $slug;

		if ( '' !== $tab ) {
			$_GET['tab']     = $tab;
			$_REQUEST['tab'] = $tab;
		}

		try {
			ob_start();
			do_action( $hookname );
			$html = (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			error_log( 'wp-search: settings page render failed for ' . $slug . ': ' . $e->getMessage() );
			$html = '';
		} finally {
			// Drop anything the callback opened and did not close.
			while ( ob_get_level() > $depth ) {
				ob_end_clean();
			}

			if ( null === $previous_page ) {
				unset( $GLOBALS['plugin_page'] );
			} else {
				$GLOBALS['plugin_page'] = $previous_page;
			}

			if ( $had_get ) {
				$_GET['page'] = $previous_get;
			} else {
				unset( $_GET['page'] );
			}

			if ( $had_request ) {
				$_REQUEST['page'] = $previous_request;
			} else {
				unset( $_REQUEST['page'] );
			}

			if ( $had_tab ) {
				$_GET['tab'] = $previous_tab;
			} else {
				unset( $_GET['tab'], $_REQUEST['tab'] );
			}

			self::$crawling = false;
		}

		return $html;
	}

	/**
	 * Extract indexable form controls from rendered admin markup.
	 *
	 * @since 1.0.0
	 * @param string                $html Rendered page markup.
	 * @param array<string, mixed>  $page Page descriptor.
	 * @param string                $tab  Tab the markup was rendered under.
	 * @return array<mixed>
	 */
	private function parse_controls( string $html, array $page, string $tab = '' ): array {
		if ( ! class_exists( '\DOMDocument' ) || '' === trim( $html ) ) {
			return array();
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );

		// The fragment is wrapped so a page that prints bare rows still parses.
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?><div id="wp-search-crawl-root">' . $html . '</div>',
			LIBXML_NOWARNING | LIBXML_NOERROR
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array();
		}

		$xpath    = new \DOMXPath( $dom );
		$controls = $xpath->query( '//input | //select | //textarea' );
		if ( false === $controls ) {
			return array();
		}

		$skip_types = array( 'hidden', 'submit', 'button', 'reset', 'image', 'file' );
		$skip_names = array( '_wpnonce', '_wp_http_referer', 'option_page', 'action', 'submit', 'search', 's' );

		$records = array();
		$seen    = array();
		$count   = 0;

		foreach ( $controls as $node ) {
			if ( $count >= self::MAX_CRAWLED_FIELDS_PER_PAGE ) {
				break;
			}

			if ( ! $node instanceof \DOMElement ) {
				continue;
			}

			$type = strtolower( $node->getAttribute( 'type' ) );
			if ( 'input' === strtolower( $node->nodeName ) && in_array( $type, $skip_types, true ) ) {
				continue;
			}

			$name     = $node->getAttribute( 'name' );
			$dom_id   = $node->getAttribute( 'id' );
			$field_id = '' !== $name ? $name : $dom_id;

			if ( '' === $field_id || in_array( $field_id, $skip_names, true ) ) {
				continue;
			}

			// Radio groups and checkbox arrays repeat one name many times.
			if ( isset( $seen[ $field_id ] ) ) {
				continue;
			}
			$seen[ $field_id ] = true;
			$count++;

			$label = $this->label_for_control( $xpath, $node, $dom_id );
			if ( '' === $label ) {
				$label = ucwords( str_replace( array( '_', '-', '[', ']' ), ' ', $field_id ) );
			}

			$section_title = $this->section_for_control( $xpath, $node );
			if ( '' === $section_title ) {
				$section_title = sprintf( '%s Settings', (string) $page['title'] );
			}

			$breadcrumb = array( __( 'Settings', 'wp-search' ), (string) $page['title'] );
			if ( '' !== $tab ) {
				$breadcrumb[] = ucwords( str_replace( array( '_', '-' ), ' ', $tab ) );
			} elseif ( $section_title !== sprintf( '%s Settings', (string) $page['title'] ) && $section_title !== (string) $page['title'] ) {
				$breadcrumb[] = $section_title;
			}

			$records[] = $this->make_record(
				array(
					'key'          => sprintf( '%03d-dom-%s-%04d-%s', (int) $page['order'], $tab, $count, $field_id ),
					'page'         => $page,
					'tab'          => $tab,
					'sectionId'    => 'crawled',
					'sectionTitle' => $section_title,
					'fieldId'      => $field_id,
					'fieldLabel'   => $label,
					'description'  => $this->description_for_control( $xpath, $node ),
					'snippet'      => $this->snippet_from_node( $dom, $node, $label, $field_id ),
					'weight'       => 72,
					'domId'        => $dom_id,
					'breadcrumb'   => $breadcrumb,
				)
			);
		}

		return $records;
	}

	/**
	 * Find the visible label for a rendered control.
	 *
	 * Tries the explicit `for` association, then a wrapping label, then the
	 * row header of a `form-table`, then a fieldset legend, then the
	 * accessible-name attributes.
	 *
	 * @since 1.0.0
	 * @param \DOMXPath  $xpath  Document xpath.
	 * @param \DOMElement $node   Control element.
	 * @param string     $dom_id Control id attribute.
	 * @return string
	 */
	private function label_for_control( \DOMXPath $xpath, \DOMElement $node, string $dom_id ): string {
		if ( '' !== $dom_id ) {
			$labels = $xpath->query( sprintf( '//label[@for=%s]', $this->xpath_literal( $dom_id ) ) );
			if ( $labels instanceof \DOMNodeList && $labels->length > 0 ) {
				$text = $this->node_text( $labels->item( 0 ) );
				if ( '' !== $text ) {
					return $text;
				}
			}
		}

		foreach ( array( 'ancestor::label[1]', 'ancestor::tr[1]/th[1]', 'ancestor::fieldset[1]/legend[1]' ) as $query ) {
			$found = $xpath->query( $query, $node );
			if ( $found instanceof \DOMNodeList && $found->length > 0 ) {
				$text = $this->node_text( $found->item( 0 ) );
				if ( '' !== $text ) {
					return $text;
				}
			}
		}

		foreach ( array( 'aria-label', 'placeholder', 'title' ) as $attribute ) {
			$value = trim( $node->getAttribute( $attribute ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Find the nearest heading above a rendered control.
	 *
	 * @since 1.0.0
	 * @param \DOMXPath   $xpath Document xpath.
	 * @param \DOMElement $node  Control element.
	 * @return string
	 */
	private function section_for_control( \DOMXPath $xpath, \DOMElement $node ): string {
		$headings = $xpath->query(
			'preceding::*[self::h1 or self::h2 or self::h3 or self::h4]'
				. '[not(contains(concat(" ", normalize-space(@class), " "), " nav-tab-wrapper "))]'
				. '[not(.//a[contains(concat(" ", normalize-space(@class), " "), " nav-tab ")])][1]',
			$node
		);
		if ( $headings instanceof \DOMNodeList && $headings->length > 0 ) {
			return $this->node_text( $headings->item( $headings->length - 1 ) );
		}

		return '';
	}

	/**
	 * Pull the description paragraph that follows a rendered control.
	 *
	 * @since 1.0.0
	 * @param \DOMXPath   $xpath Document xpath.
	 * @param \DOMElement $node  Control element.
	 * @return string
	 */
	private function description_for_control( \DOMXPath $xpath, \DOMElement $node ): string {
		$found = $xpath->query( 'following::p[contains(@class, "description")][1]', $node );
		if ( $found instanceof \DOMNodeList && $found->length > 0 ) {
			return $this->node_text( $found->item( 0 ) );
		}

		return '';
	}

	/**
	 * Build the stored snippet for a rendered control.
	 *
	 * @since 1.0.0
	 * @param \DOMDocument $dom      Parsed document.
	 * @param \DOMElement  $node     Control element.
	 * @param string       $label    Resolved label.
	 * @param string       $field_id Field identifier.
	 * @return string
	 */
	private function snippet_from_node( \DOMDocument $dom, \DOMElement $node, string $label, string $field_id ): string {
		$clone = $node->cloneNode( true );

		if ( $clone instanceof \DOMElement && $this->is_sensitive( $field_id ) && $clone->hasAttribute( 'value' ) ) {
			$clone->setAttribute( 'value', '' );
		}

		$markup = (string) $dom->saveHTML( $clone );
		if ( '' === trim( $markup ) ) {
			$markup = '<input name="' . esc_attr( $field_id ) . '" type="text" class="regular-text" />';
		}

		if ( '' !== $label ) {
			$markup = '<label>' . esc_html( $label ) . '</label>' . "\n" . $markup;
		}

		return $this->sanitize_snippet( $markup );
	}

	/**
	 * Read the trimmed text of a node.
	 *
	 * @since 1.0.0
	 * @param \DOMNode|null $node Node to read.
	 * @return string
	 */
	private function node_text( ?\DOMNode $node ): string {
		if ( null === $node ) {
			return '';
		}

		$text = preg_replace( '/\s+/', ' ', (string) $node->textContent );

		return trim( (string) $text );
	}

	/**
	 * Quote a string for safe interpolation into an XPath expression.
	 *
	 * @since 1.0.0
	 * @param string $value Raw value.
	 * @return string
	 */
	private function xpath_literal( string $value ): string {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		return "concat('" . str_replace( "'", "', \"'\", '", $value ) . "')";
	}

	/**
	 * Assemble one index record from a layer's raw parts.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $parts Record parts.
	 * @return array<string, mixed>
	 */
	private function make_record( array $parts ): array {
		$page    = $parts['page'];
		$slug    = (string) $page['slug'];
		$title   = (string) $page['title'];
		$snippet = (string) $parts['snippet'];
		$anchor  = (string) ( $parts['domId'] ?? $parts['fieldId'] ?? '' );

		$breadcrumb = $parts['breadcrumb'] ?? array( __( 'Settings', 'wp-search' ), $title );

		return array(
			'key'          => (string) $parts['key'],
			'source'       => 'core' === $page['kind'] ? __( 'WordPress Core', 'wp-search' ) : $title,
			'sourceKind'   => 'core' === $page['kind'] ? 'core' : 'plugin',
			'pageSlug'     => $slug,
			'pageTitle'    => $title,
			'sectionId'    => (string) $parts['sectionId'],
			'sectionTitle' => (string) $parts['sectionTitle'],
			'fieldId'      => (string) $parts['fieldId'],
			'fieldLabel'   => (string) $parts['fieldLabel'],
			'description'  => (string) ( $parts['description'] ?? '' ),
			'alias'        => (string) ( $parts['alias'] ?? '' ),
			'snippet'      => $snippet,
			'snippetText'  => wp_strip_all_tags( $snippet ),
			'language'     => $this->detect_language( $snippet ),
			'weight'       => (int) $parts['weight'],
			'cap'          => (string) ( $page['cap'] ?? 'manage_options' ),
			'partial'      => (bool) ( $page['partial'] ?? false ),
			'url'          => $this->page_url( $slug, 'page' === $parts['sectionId'] ? '' : $anchor, (string) ( $parts['tab'] ?? '' ) ),
			'breadcrumb'   => is_array( $breadcrumb ) ? array_values( $breadcrumb ) : array(),
		);
	}

	/**
	 * Build the admin URL for a Settings page, optionally anchored at a field.
	 *
	 * @since 1.0.0
	 * @param string $slug     Menu slug.
	 * @param string $fragment Optional anchor.
	 * @param string $tab      Optional tab query arg.
	 * @return string
	 */
	private function page_url( string $slug, string $fragment = '', string $tab = '' ): string {
		$path = false !== strpos( $slug, '.php' ) ? $slug : 'options-general.php?page=' . $slug;

		if ( '' !== $tab ) {
			$path .= ( false === strpos( $path, '?' ) ? '?' : '&' ) . 'tab=' . rawurlencode( $tab );
		}

		$fragment = preg_replace( '/[^A-Za-z0-9_\-]/', '', $fragment );
		if ( is_string( $fragment ) && '' !== $fragment ) {
			$path .= '#' . $fragment;
		}

		return admin_url( $path );
	}

	/**
	 * Map a registered setting's data type onto an HTML input type.
	 *
	 * @since 1.0.0
	 * @param string $type Registered setting type.
	 * @return string
	 */
	private function input_type_for( string $type ): string {
		switch ( $type ) {
			case 'boolean':
				return 'checkbox';
			case 'integer':
			case 'number':
				return 'number';
			default:
				return 'text';
		}
	}

	/**
	 * Decide whether a field carries a secret.
	 *
	 * @since 1.0.0
	 * @param string $field_id Field identifier.
	 * @return bool
	 */
	private function is_sensitive( string $field_id ): bool {
		$lower = $this->normalize_query( $field_id );

		foreach ( $this->sensitive_markers as $marker ) {
			if ( false !== strpos( $lower, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a representative HTML snippet for a settings field.
	 *
	 * Uses the live option value where available so the snippet resembles the
	 * rendered admin control without depending on which admin page is loading.
	 * Secret-bearing fields are rendered empty.
	 *
	 * @since 1.0.0
	 * @param string       $field_id Field identifier.
	 * @param array<mixed> $field    Field definition.
	 * @return string
	 */
	private function build_field_snippet( string $field_id, array $field ): string {
		$label       = $field['label'] ?? '';
		$description = $field['description'] ?? '';
		$type        = $field['type'] ?? 'text';
		$class       = $field['class'] ?? '';

		$value = $this->is_sensitive( $field_id ) ? '' : get_option( $field_id );
		if ( false === $value || null === $value || is_array( $value ) || is_object( $value ) ) {
			$value = '';
		}
		$value = (string) $value;

		$input = '';
		switch ( $type ) {
			case 'checkbox':
				$checked = in_array( strtolower( $value ), array( '1', 'yes', 'true', 'on' ), true ) ? ' checked' : '';
				$input   = '<input name="' . esc_attr( $field_id ) . '" type="checkbox" value="1"' . $checked . ' />';
				break;
			case 'number':
				$input = '<input name="' . esc_attr( $field_id ) . '" type="number" step="1" min="0" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
				break;
			case 'select':
				$input = '<select name="' . esc_attr( $field_id ) . '" class="' . esc_attr( $class ) . '">';
				$input .= '<option value="' . esc_attr( $value ) . '" selected>' . esc_html( $value ) . '</option>';
				$input .= '</select>';
				break;
			case 'radio':
				$input = '<label><input type="radio" name="' . esc_attr( $field_id ) . '" value="' . esc_attr( $value ) . '" checked /> ' . esc_html( $value ) . '</label>';
				break;
			case 'textarea':
				$input = '<textarea name="' . esc_attr( $field_id ) . '" class="' . esc_attr( $class ) . '" rows="3">' . esc_html( $value ) . '</textarea>';
				break;
			case 'password':
				$input = '<input name="' . esc_attr( $field_id ) . '" type="password" value="" class="' . esc_attr( $class ) . '" />';
				break;
			default:
				$input = '<input name="' . esc_attr( $field_id ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
				break;
		}

		$markup = '';
		if ( '' !== $label ) {
			$markup .= '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>' . "\n";
		}
		$markup .= $input;
		if ( '' !== $description ) {
			$markup .= "\n" . '<p class="description">' . esc_html( $description ) . '</p>';
		}

		$markup = $this->sanitize_snippet( $markup );

		// Ensure we always have a non-empty snippet even with no label/value.
		if ( '' === $markup ) {
			$markup = '<input name="' . esc_attr( $field_id ) . '" type="text" class="regular-text" />';
		}

		return $markup;
	}

	/**
	 * Categorize the dominant language of a snippet.
	 *
	 * @since 1.0.0
	 * @param string $snippet Snippet text.
	 * @return string
	 */
	private function detect_language( string $snippet ): string {
		$text = (string) preg_replace( '/<[^>]+>/', '', $snippet );

		if ( false !== strpos( $snippet, '<?php' ) || preg_match( '/\b(add_filter|add_action|function\s+[a-zA-Z0-9_]+)\s*\(/', $text ) ) {
			return 'php';
		}

		if ( preg_match( '/[.#]?[a-zA-Z0-9_-]+\s*\{[^}]*:[^}]*\}/', $snippet ) ) {
			return 'css';
		}

		return 'html';
	}

	/**
	 * Clean a captured snippet while keeping it deterministic.
	 *
	 * @since 1.0.0
	 * @param string $snippet Raw markup.
	 * @return string
	 */
	private function sanitize_snippet( string $snippet ): string {
		$snippet = preg_replace( '/\R+/', "\n", $snippet );
		$snippet = preg_replace( '/[ \t]+/', ' ', $snippet );
		$snippet = trim( $snippet );

		if ( strlen( $snippet ) > self::MAX_SNIPPET_LENGTH ) {
			$snippet = substr( $snippet, 0, self::MAX_SNIPPET_LENGTH ) . '…';
		}

		return $snippet;
	}
}
