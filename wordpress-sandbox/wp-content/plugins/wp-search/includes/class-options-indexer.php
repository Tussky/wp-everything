<?php
/**
 * Options Indexer
 *
 * Reads the wp_options table directly and publishes selected option families
 * as spotlight records. Protected options (API keys, gateway secrets) are
 * surfaced with masked values and are excluded from search terms.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Indexes and searches WordPress options.
 *
 * @since 1.0.0
 */
class Options_Indexer extends Indexer implements Spotlight_Provider {

	/**
	 * Source label for results.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE = 'options';

	/**
	 * Maximum number of option records surfaced in the spotlight response.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const RECORDS_LIMIT = 100;

	/**
	 * Option families surfaced by this indexer, with weight and explainer.
	 *
	 * Weights mirror spotlight-data.json so important options sort first.
	 *
	 * @since 1.0.0
	 * @var array<string, array{weight: int, explainer: string}>
	 */
	private array $known_options = array(
		// Site identity / general.
		'siteurl'                     => array(
			'weight'    => 90,
			'explainer' => 'The WordPress address (site_url) where core files live.',
		),
		'home'                        => array(
			'weight'    => 90,
			'explainer' => 'The public address (URL) visitors see.',
		),
		'blogname'                    => array(
			'weight'    => 90,
			'explainer' => 'The site title shown in the header and browser tab.',
		),
		'blogdescription'             => array(
			'weight'    => 80,
			'explainer' => 'The tagline shown beneath the site title.',
		),
		'admin_email'                 => array(
			'weight'    => 80,
			'explainer' => 'Address that receives administrative and system notifications.',
		),
		'site_icon'                   => array(
			'weight'    => 50,
			'explainer' => 'ID of the image used as the site icon and app favicon.',
		),
		'default_role'                => array(
			'weight'    => 70,
			'explainer' => 'Default role assigned to newly registered users.',
		),
		'date_format'                 => array(
			'weight'    => 70,
			'explainer' => 'Default format used to display dates.',
		),
		'time_format'                 => array(
			'weight'    => 70,
			'explainer' => 'Default format used to display times.',
		),
		'timezone_string'             => array(
			'weight'    => 55,
			'explainer' => 'Local timezone used for scheduling and timestamps.',
		),
		'gmt_offset'                  => array(
			'weight'    => 55,
			'explainer' => 'Hours offset from GMT when no timezone string is set.',
		),
		'start_of_week'               => array(
			'weight'    => 60,
			'explainer' => 'First day of the week shown in the calendar.',
		),
		'blog_charset'                => array(
			'weight'    => 60,
			'explainer' => 'Character encoding used for pages and feeds.',
		),
		'html_type'                   => array(
			'weight'    => 40,
			'explainer' => 'Content-Type header value sent for the front end.',
		),
		'WPLANG'                      => array(
			'weight'    => 45,
			'explainer' => 'Language code used by the site when set.',
		),
		// Reading.
		'posts_per_page'              => array(
			'weight'    => 75,
			'explainer' => 'Maximum number of posts shown per listing page.',
		),
		'posts_per_rss'               => array(
			'weight'    => 65,
			'explainer' => 'Maximum number of items shown in RSS feeds.',
		),
		'rss_use_excerpt'             => array(
			'weight'    => 55,
			'explainer' => 'Whether RSS feeds show full text or excerpts.',
		),
		'show_on_front'               => array(
			'weight'    => 75,
			'explainer' => 'Whether the front page shows posts or a static page.',
		),
		'page_on_front'               => array(
			'weight'    => 75,
			'explainer' => 'ID of the page shown on the front page.',
		),
		'page_for_posts'              => array(
			'weight'    => 70,
			'explainer' => 'ID of the page that lists posts when a static front page is set.',
		),
		'blog_public'                 => array(
			'weight'    => 70,
			'explainer' => 'Whether the site is visible to search engines.',
		),
		// Writing.
		'default_category'            => array(
			'weight'    => 70,
			'explainer' => 'Default category assigned to new posts.',
		),
		'default_post_format'         => array(
			'weight'    => 60,
			'explainer' => 'Default post format applied to new posts.',
		),
		'default_post_edit_rows'      => array(
			'weight'    => 40,
			'explainer' => 'Height of the post editor textarea in rows.',
		),
		'use_smilies'                 => array(
			'weight'    => 55,
			'explainer' => 'Whether text emoticons are converted to graphic icons.',
		),
		'use_balanceTags'             => array(
			'weight'    => 40,
			'explainer' => 'Whether invalid nested HTML is fixed on save.',
		),
		'mailserver_url'              => array(
			'weight'    => 50,
			'explainer' => 'Hostname of the mail server used for post-by-email.',
		),
		'mailserver_login'            => array(
			'weight'    => 50,
			'explainer' => 'Username for the post-by-email mail server.',
		),
		'mailserver_pass'             => array(
			'weight'    => 50,
			'explainer' => 'Password for the post-by-email mail server.',
		),
		'mailserver_port'             => array(
			'weight'    => 50,
			'explainer' => 'Port for the post-by-email mail server.',
		),
		'default_email_category'      => array(
			'weight'    => 50,
			'explainer' => 'Default category for posts submitted by email.',
		),
		'default_link_category'       => array(
			'weight'    => 30,
			'explainer' => 'Default category for the legacy links manager.',
		),
		'ping_sites'                  => array(
			'weight'    => 55,
			'explainer' => 'Services notified when a new post is published.',
		),
		// Discussion.
		'default_comment_status'      => array(
			'weight'    => 65,
			'explainer' => 'Default status given to comments on new posts.',
		),
		'default_ping_status'         => array(
			'weight'    => 60,
			'explainer' => 'Default status for incoming pingbacks and trackbacks.',
		),
		'default_pingback_flag'       => array(
			'weight'    => 50,
			'explainer' => 'Whether to attempt pingbacks for links in new posts.',
		),
		'comments_notify'             => array(
			'weight'    => 60,
			'explainer' => 'Whether the post author is emailed when a comment is added.',
		),
		'moderation_notify'           => array(
			'weight'    => 60,
			'explainer' => 'Whether the admin is emailed when a comment is held for moderation.',
		),
		'comment_moderation'          => array(
			'weight'    => 60,
			'explainer' => 'Whether comments with links must be approved.',
		),
		'comment_whitelist'           => array(
			'weight'    => 55,
			'explainer' => 'Whether previously approved authors skip moderation.',
		),
		'comment_max_links'           => array(
			'weight'    => 55,
			'explainer' => 'Maximum links allowed before a comment is held.',
		),
		'comment_registration'        => array(
			'weight'    => 55,
			'explainer' => 'Whether users must be logged in to comment.',
		),
		'require_name_email'          => array(
			'weight'    => 55,
			'explainer' => 'Whether comment authors must provide a name and email.',
		),
		'comment_order'               => array(
			'weight'    => 50,
			'explainer' => 'Sort order of comments on a post.',
		),
		'page_comments'               => array(
			'weight'    => 50,
			'explainer' => 'Whether comments are split into pages.',
		),
		'comments_per_page'           => array(
			'weight'    => 50,
			'explainer' => 'Comments shown per page when pagination is enabled.',
		),
		'thread_comments'             => array(
			'weight'    => 50,
			'explainer' => 'Whether comments display as a nested thread.',
		),
		'thread_comments_depth'       => array(
			'weight'    => 50,
			'explainer' => 'Maximum nesting depth for threaded comments.',
		),
		'close_comments_for_old_posts' => array(
			'weight'    => 50,
			'explainer' => 'Whether comments close on posts older than a set age.',
		),
		'close_comments_days_old'     => array(
			'weight'    => 50,
			'explainer' => 'Age in days at which comments are closed.',
		),
		'show_avatars'                => array(
			'weight'    => 55,
			'explainer' => 'Whether avatars are shown next to comments.',
		),
		'avatar_default'              => array(
			'weight'    => 50,
			'explainer' => 'Default avatar shown when a user has none.',
		),
		'avatar_rating'               => array(
			'weight'    => 50,
			'explainer' => 'Maximum maturity rating for displayed Gravatars.',
		),
		'moderation_keys'             => array(
			'weight'    => 45,
			'explainer' => 'Words that flag a comment for moderation.',
		),
		'blacklist_keys'              => array(
			'weight'    => 45,
			'explainer' => 'Words that mark a comment as spam.',
		),
		// Media.
		'thumbnail_size_w'             => array(
			'weight'    => 60,
			'explainer' => 'Width of the thumbnail image size in pixels.',
		),
		'thumbnail_size_h'             => array(
			'weight'    => 60,
			'explainer' => 'Height of the thumbnail image size in pixels.',
		),
		'thumbnail_crop'              => array(
			'weight'    => 55,
			'explainer' => 'Whether the thumbnail image size is cropped to fit.',
		),
		'medium_size_w'               => array(
			'weight'    => 60,
			'explainer' => 'Width of the medium image size in pixels.',
		),
		'medium_size_h'               => array(
			'weight'    => 60,
			'explainer' => 'Height of the medium image size in pixels.',
		),
		'large_size_w'                => array(
			'weight'    => 60,
			'explainer' => 'Width of the large image size in pixels.',
		),
		'large_size_h'                => array(
			'weight'    => 60,
			'explainer' => 'Height of the large image size in pixels.',
		),
		'image_default_link_type'     => array(
			'weight'    => 50,
			'explainer' => 'Default link type for newly inserted images.',
		),
		'image_default_size'          => array(
			'weight'    => 50,
			'explainer' => 'Default size for newly inserted images.',
		),
		'image_default_align'         => array(
			'weight'    => 50,
			'explainer' => 'Default alignment for newly inserted images.',
		),
		'uploads_use_yearmon_folders' => array(
			'weight'    => 55,
			'explainer' => 'Whether uploads are sorted into year/month folders.',
		),
		// Permalinks.
		'permalink_structure'         => array(
			'weight'    => 70,
			'explainer' => 'Pattern used to build human-readable post and page URLs.',
		),
		'category_base'               => array(
			'weight'    => 60,
			'explainer' => 'Prefix prepended to category archive URLs.',
		),
		'tag_base'                    => array(
			'weight'    => 60,
			'explainer' => 'Prefix prepended to tag archive URLs.',
		),
		// Theme and plugins.
		'template'                    => array(
			'weight'    => 50,
			'explainer' => 'Slug of the active theme\'s parent template.',
		),
		'stylesheet'                  => array(
			'weight'    => 50,
			'explainer' => 'Slug of the active theme stylesheet.',
		),
		'active_plugins'              => array(
			'weight'    => 70,
			'explainer' => 'Serialized list of plugin files WordPress loads on every request.',
		),
		'users_can_register'          => array(
			'weight'    => 55,
			'explainer' => 'Whether visitors may create their own accounts.',
		),
		// System / internal core options.
		'db_version'                  => array(
			'weight'    => 30,
			'explainer' => 'Database schema version WordPress expects.',
		),
		'fresh_site'                  => array(
			'weight'    => 30,
			'explainer' => 'Whether the site is still in its initial setup period.',
		),
		'rewrite_rules'               => array(
			'weight'    => 40,
			'explainer' => 'Cached rewrite rules used for URL routing.',
		),
		'wp_user_roles'               => array(
			'weight'    => 35,
			'explainer' => 'Serialized map of user roles and capabilities.',
		),
		'cron'                        => array(
			'weight'    => 35,
			'explainer' => 'Timestamps of the next scheduled cron events.',
		),
		'sticky_posts'                => array(
			'weight'    => 45,
			'explainer' => 'List of post IDs marked as sticky.',
		),
		'nav_menu_options'            => array(
			'weight'    => 40,
			'explainer' => 'Serialized configuration for navigation menus.',
		),
		// Plugin-added options kept for continuity with the original map.
		'woocommerce_stripe_settings' => array(
			'weight'    => 75,
			'explainer' => 'Stripe gateway config including the live secret API key.',
		),
		'akismet_api_key'             => array(
			'weight'    => 65,
			'explainer' => 'Authenticates the site against Akismet\'s spam-filtering service.',
		),
		'woocommerce_currency'        => array(
			'weight'    => 60,
			'explainer' => 'Default currency used for store prices and orders.',
		),
	);

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
	 * Search is handled by Spotlight::record_matches() against the full
	 * record set. This indexer only needs get_records().
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @return array<mixed>
	 */
	public function search( string $query ): array {
		return array();
	}

	/**
	 * Build spotlight records for selected options from the real database.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_records(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		global $wpdb;

		$option_names = array_keys( $this->known_options );
		$placeholders = implode( ', ', array_fill( 0, count( $option_names ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- live options, trivial row count.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
				...$option_names
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$by_name = array();
		foreach ( $rows as $row ) {
			$by_name[ $row->option_name ] = $row;
		}

		// Stable ID assignment regardless of row order.
		uksort( $by_name, 'strnatcasecmp' );

		$admin_hrefs = array(
			'siteurl'                     => 'options-general.php',
			'home'                        => 'options-general.php',
			'blogname'                    => 'options-general.php',
			'blogdescription'             => 'options-general.php',
			'admin_email'                 => 'options-general.php',
			'site_icon'                   => 'customize.php?autofocus[section]=title_tagline',
			'default_role'                => 'options-general.php#default_role',
			'date_format'                 => 'options-general.php#date_format_custom',
			'time_format'                 => 'options-general.php#time_format_custom',
			'timezone_string'             => 'options-general.php#timezone_string',
			'gmt_offset'                  => 'options-general.php#timezone_string',
			'start_of_week'               => 'options-general.php#start_of_week',
			'blog_charset'                => 'options-general.php',
			'html_type'                   => 'options-general.php',
			'WPLANG'                      => 'options-general.php#WPLANG',
			'posts_per_page'              => 'options-reading.php#posts_per_page',
			'posts_per_rss'               => 'options-reading.php#posts_per_rss',
			'rss_use_excerpt'             => 'options-reading.php#rss_use_excerpt',
			'show_on_front'               => 'options-reading.php#show_on_front',
			'page_on_front'               => 'options-reading.php#page_on_front',
			'page_for_posts'              => 'options-reading.php#page_for_posts',
			'blog_public'                 => 'options-reading.php#blog_public',
			'default_category'            => 'options-writing.php#default_category',
			'default_post_format'         => 'options-writing.php#default_post_format',
			'default_post_edit_rows'      => 'options-writing.php#default_post_edit_rows',
			'use_smilies'                 => 'options-writing.php#use_smilies',
			'use_balanceTags'             => 'options-writing.php#use_balanceTags',
			'mailserver_url'              => 'options-writing.php#mailserver_url',
			'mailserver_login'            => 'options-writing.php#mailserver_login',
			'mailserver_pass'             => 'options-writing.php#mailserver_pass',
			'mailserver_port'             => 'options-writing.php#mailserver_port',
			'default_email_category'      => 'options-writing.php#default_email_category',
			'default_link_category'       => 'options-writing.php#default_link_category',
			'ping_sites'                  => 'options-writing.php#ping_sites',
			'default_comment_status'      => 'options-discussion.php#default_comment_status',
			'default_ping_status'         => 'options-discussion.php#default_ping_status',
			'default_pingback_flag'       => 'options-discussion.php#default_pingback_flag',
			'comments_notify'             => 'options-discussion.php#comments_notify',
			'moderation_notify'           => 'options-discussion.php#moderation_notify',
			'comment_moderation'          => 'options-discussion.php#comment_moderation',
			'comment_whitelist'           => 'options-discussion.php#comment_whitelist',
			'comment_max_links'           => 'options-discussion.php#comment_max_links',
			'comment_registration'        => 'options-discussion.php#comment_registration',
			'require_name_email'          => 'options-discussion.php#require_name_email',
			'comment_order'               => 'options-discussion.php#comment_order',
			'page_comments'               => 'options-discussion.php#page_comments',
			'comments_per_page'           => 'options-discussion.php#comments_per_page',
			'thread_comments'             => 'options-discussion.php#thread_comments',
			'thread_comments_depth'       => 'options-discussion.php#thread_comments_depth',
			'close_comments_for_old_posts' => 'options-discussion.php#close_comments_for_old_posts',
			'close_comments_days_old'     => 'options-discussion.php#close_comments_days_old',
			'show_avatars'                => 'options-discussion.php#show_avatars',
			'avatar_default'              => 'options-discussion.php#avatar_default',
			'avatar_rating'               => 'options-discussion.php#avatar_rating',
			'moderation_keys'             => 'options-discussion.php#moderation_keys',
			'blacklist_keys'              => 'options-discussion.php#blacklist_keys',
			'thumbnail_size_w'             => 'options-media.php#thumbnail_size_w',
			'thumbnail_size_h'             => 'options-media.php#thumbnail_size_h',
			'thumbnail_crop'              => 'options-media.php#thumbnail_crop',
			'medium_size_w'               => 'options-media.php#medium_size_w',
			'medium_size_h'               => 'options-media.php#medium_size_h',
			'large_size_w'                => 'options-media.php#large_size_w',
			'large_size_h'                => 'options-media.php#large_size_h',
			'image_default_link_type'     => 'options-media.php#image_default_link_type',
			'image_default_size'          => 'options-media.php#image_default_size',
			'image_default_align'         => 'options-media.php#image_default_align',
			'uploads_use_yearmon_folders' => 'options-media.php#uploads_use_yearmon_folders',
			'active_plugins'              => 'plugins.php',
			'woocommerce_stripe_settings' => 'admin.php?page=wc-settings&tab=checkout&section=stripe',
			'akismet_api_key'             => 'admin.php?page=akismet-key-config',
			'permalink_structure'         => 'options-permalink.php#permalink_structure',
			'category_base'               => 'options-permalink.php#category_base',
			'tag_base'                    => 'options-permalink.php#tag_base',
			'woocommerce_currency'        => 'admin.php?page=wc-settings&tab=general',
			'users_can_register'          => 'options-general.php#users_can_register',
			'template'                    => 'themes.php',
			'stylesheet'                  => 'themes.php',
			'db_version'                  => 'options-general.php',
			'fresh_site'                  => 'options-general.php',
			'rewrite_rules'               => 'options-permalink.php',
			'wp_user_roles'               => 'options-general.php',
			'cron'                        => 'options-general.php',
			'sticky_posts'                => 'edit.php',
			'nav_menu_options'            => 'nav-menus.php',
		);

		$records = array();
		$index   = 0;

		foreach ( $by_name as $name => $row ) {
			$index++;
			if ( $index > self::RECORDS_LIMIT ) {
				break;
			}
			$known     = $this->known_options[ $name ] ?? array(
				'weight'    => 40,
				'explainer' => 'WordPress option.',
			);
			$protected = $this->is_protected_option( $name );
			$value     = $protected ? null : (string) $row->option_value;

			$terms = array(
				$name,
			);

			$explainer_terms = array_filter( explode( ' ', $this->sanitize_term_text( $known['explainer'] ) ) );
			foreach ( $explainer_terms as $explainer_term ) {
				$terms[] = $explainer_term;
			}

			if ( ! $protected && null !== $value && ! $this->is_machine_readable( $value ) ) {
				$terms[] = $value;
			}

			$records[] = array(
				'id'      => 'o-' . $index,
				'facet'   => self::SOURCE,
				'search'  => array(
					'terms'  => array_values( array_filter( array_unique( $terms ) ) ),
					'weight' => (int) $known['weight'],
				),
				'display' => array(
					'name'      => $name,
					'value'     => $value,
					'autoload'  => $this->normalize_autoload( $row->autoload ?? 'yes' ),
					'protected' => $protected,
					'explainer' => $known['explainer'],
					'url'       => admin_url( $admin_hrefs[ $name ] ?? 'options-general.php' ),
				),
			);
		}

		return $records;
	}

	/**
	 * Options are queried live; no persistent cache is maintained.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function reindex(): int {
		return 0;
	}

	/**
	 * Decide whether an option value must be masked from search terms.
	 *
	 * @since 1.0.0
	 * @param string $name Option name.
	 * @return bool
	 */
	private function is_protected_option( string $name ): bool {
		$explicit = array(
			'akismet_api_key',
			'woocommerce_stripe_settings',
		);

		if ( in_array( $name, $explicit, true ) ) {
			return true;
		}

		$sensitive = array(
			'api_key',
			'_api_key',
			'secret',
			'token',
			'password',
			'private_key',
			'stripe_settings',
		);

		$lower = $this->normalize_query( $name );
		foreach ( $sensitive as $needle ) {
			if ( false !== strpos( $lower, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make a block of text safe to use as searchable tokens.
	 *
	 * @since 1.0.0
	 * @param string $text Raw text.
	 * @return string
	 */
	private function sanitize_term_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[^a-zA-Z0-9\\-\\/_\\s]/', '', $text );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * Convert MariaDB's ON/OFF autoload values back to WordPress yes/no convention.
	 *
	 * @since 1.0.0
	 * @param string $autoload Raw autoload value.
	 * @return string
	 */
	private function normalize_autoload( string $autoload ): string {
		$autoload = $this->normalize_query( $autoload );
		if ( 'on' === $autoload ) {
			return 'yes';
		}
		if ( 'off' === $autoload ) {
			return 'no';
		}
		return $autoload;
	}

	/**
	 * Decide whether a value is machine-read (serialized, JSON, or too long)
	 * and should be omitted from search terms.
	 *
	 * @since 1.0.0
	 * @param string $value Option value.
	 * @return bool
	 */
	private function is_machine_readable( string $value ): bool {
		if ( strlen( $value ) > 500 ) {
			return true;
		}
		if ( is_serialized_string( $value ) ) {
			return true;
		}
		if ( '{' === substr( ltrim( $value ), 0, 1 ) && '}' === substr( rtrim( $value ), -1 ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return true;
			}
		}
		return false;
	}
}
