<?php
/*
 *	Add field to user profile page
 */
function admin_search_user_option(  $user  ) {

	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

?>
<tr>
	<th><label for="hide_in_admin_search"><?php esc_html_e( 'Admin Search', 'admin-search' ); ?></label></th>
	<td>
		<label for="hide_in_admin_search">
			<input name="hide_in_admin_search" type="checkbox" id="hide_in_admin_search" value="1"<?php echo get_the_author_meta( 'hide_in_admin_search', $user -> ID ) ? ' checked' : ''; ?>>
			<?php esc_html_e( 'Do not include profile in Admin Search results', 'admin-search' ); ?>
		</label>
	</td>
</tr>
<?php

}

add_action( 'personal_options', 'admin_search_user_option' );


function admin_search_save_user_option(  $user_id  ) {

	if ( ! current_user_can( 'edit_user', $user_id ) ) { 
		return false; 
	}

	if ( empty( $_POST[ '_wpnonce' ] ) || ! wp_verify_nonce( $_POST[ '_wpnonce' ], 'update-user_' . $user_id ) ) {
		return;
	}

	if ( isset( $_POST[ 'hide_in_admin_search' ] ) ) {
		update_user_meta( $user_id, 'hide_in_admin_search', $_POST[ 'hide_in_admin_search' ] );
	}

}

add_action( 'personal_options_update', 'admin_search_save_user_option' );
add_action( 'edit_user_profile_update', 'admin_search_save_user_option' );



/*
 *	Add plugin settings page
 */
function admin_search_add_settings_page() {

	add_options_page(
		__( 'Admin Search Settings', 'admin-search' ),
		__( 'Admin Search', 'admin-search' ),
		'manage_options',
		'admin-search',
		'admin_search_render_plugin_settings_page'
	);

	add_filter( 'admin_footer_text', function( $text ) {
		if ( get_current_screen() -> id === 'settings_page_admin-search' ) {
			return '<a target="_blank" href="https://wordpress.org/support/plugin/admin-search/#new-post">' . esc_attr__( 'Get support', 'admin-search' ) . '</a> | ' . str_replace(
				array(
					'[a rate]',
					'[a wp]',
					'[a donate]',
					'[/a]'
				),
				array(
					'<a target="_blank" href="https://wordpress.org/support/plugin/admin-search/reviews/#new-post">',
					'<a target="_blank" href="https://wordpress.org/plugin/admin-search/">',
					'<a target="_blank" href="https://www.buymeacoffee.com/andrewstichbury">',
					'</a>'
				),
				__( '[a rate]Leave a review[/a] on [a wp]WordPress.org[/a] or [a donate]donate[/a] to support the plugin.', 'admin-search' )
			);
		}

		return $text;
	} );

	add_filter( 'update_footer', function( $text ) {
		if ( get_current_screen() -> id === 'settings_page_admin-search' ) {

			/* translators: %s: the version of Admin Search currently installed */
			return '<a target="_blank" href="https://translate.wordpress.org/projects/wp-plugins/admin-search/"><span class="dashicons dashicons-translation"></span></a> ' . sprintf( __( 'Plugin version %s', 'admin-search' ), ADMIN_SEARCH_VERSION );
		}

		return $text;
	}, 15 );

	// Ensure the plugin stylesheet (which contains the post-type toggle CSS) is
	// enqueued on the settings page regardless of whether the admin bar is visible.
	add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
		if ( $hook_suffix === 'settings_page_admin-search' ) {
			wp_enqueue_style(
				'admin-search-stylesheet',
				plugin_dir_url( __FILE__ ) . 'assets/style.css',
				array(),
				ADMIN_SEARCH_VERSION
			);
		}
	} );

}

add_action( 'admin_menu', 'admin_search_add_settings_page' );


/*
 *	Set default plugin settings and get user settings
 */
function admin_search_setting( $name, $fallback = NULL ) {

	$default_settings = array(
		'display_on_keypress'		=>	true,
		'autosearch'				=>	true,
		'highlight_query'			=>	false,
		'show_when_viewing_site'	=>	false,
		'result_previews'			=>	false,
		'show_suggestions'			=>	false,
		'hide_internal_post_types'	=>	true,
		'post_types'				=>	array(
			'post'       => array( 'body' ),
			'page'       => array( 'body' ),
			'attachment' => array( 'body', '_wp_attachment_image_alt' ),
		),
		'taxonomies'				=>	array( ),
		'include_admin_pages'		=>	true,
		'include_comments'			=>	false,
		'include_users'				=>	true,
		'external_websites'			=>	'',
		'result_count'				=>	5
	);

	$settings = get_option( 'admin_search_settings' );

	if ( $settings === false ) {
		$settings = array();
	}

	foreach ( $default_settings as $default_setting_name => $default_setting_value ) {
		if ( ! isset( $settings[ $default_setting_name ] ) ) {
			$settings[ $default_setting_name ] = $default_setting_value;
		}
	}

	// Migrate old flat-array post_types format (list of slug strings) to the new
	// associative format (slug => [enabled field names]).  This runs every call so
	// that sites upgrading from v1.4.x automatically get the new structure without
	// needing an explicit database migration.
	if ( isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ) {
		$first = reset( $settings['post_types'] );
		if ( ! is_array( $first ) ) {
			$migrated = array();
			foreach ( $settings['post_types'] as $slug ) {
				if ( is_string( $slug ) ) {
					$migrated[ $slug ] = ( $slug === 'attachment' )
						? array( 'body', '_wp_attachment_image_alt' )
						: array( 'body' );
				}
			}
			$settings['post_types'] = $migrated;
		}
	}

	if ( isset( $settings[ $name ] ) ) {
		return $settings[ $name ];
	}

	return $fallback;

}



/*
 *	Add plugin settings fields
 */
function admin_search_settings_init() {

	register_setting(
		'admin_search',
		'admin_search_settings',
		array(
			'sanitize_callback' => 'admin_search_sanitize_setting_values'
		)
	);


	// General settings
	add_settings_section(
		'admin_search_section_general',
		__( 'General', 'admin-search' ),
		'',
		'admin-search'
	);

	add_settings_field(
		'display_on_keypress',
		__( 'Display on Keypress', 'admin-search' ),
		'admin_search_setting_display_on_keypress',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'autosearch',
		__( 'Search While Typing', 'admin-search' ),
		'admin_search_setting_autosearch',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'highlight_query',
		__( 'Query Highlighting', 'admin-search' ),
		'admin_search_setting_highlight_query',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'show_when_viewing_site',
		__( 'Show on Site', 'admin-search' ),
		'admin_search_setting_show_when_viewing_site',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'result_previews',
		__( 'Result Previews', 'admin-search' ),
		'admin_search_setting_result_previews',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'show_suggestions',
		__( 'Search Suggestions', 'admin-search' ),
		'admin_search_setting_show_suggestions',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'hide_internal_post_types',
		__( 'Hide internal content types', 'admin-search' ),
		'admin_search_setting_hide_internal_post_types',
		'admin-search',
		'admin_search_section_general'
	);

	add_settings_field(
		'result_count',
		__( 'Result Limit', 'admin-search' ),
		'admin_search_setting_result_count',
		'admin-search',
		'admin_search_section_general'
	);


	// Source settings
	add_settings_section(
		'admin_search_section_sources',
		__( 'Sources', 'admin-search' ),
		'admin_search_section_sources_text',
		'admin-search'
	);

	add_settings_field(
		'post_types',
		__( 'Post Types', 'admin-search' ),
		'admin_search_setting_post_types',
		'admin-search',
		'admin_search_section_sources'
	);

	add_settings_field(
		'taxonomies',
		__( 'Taxonomies', 'admin-search' ),
		'admin_search_setting_taxonomies',
		'admin-search',
		'admin_search_section_sources'
	);

	add_settings_field(
		'include_admin_pages',
		__( 'Admin Pages', 'admin-search' ),
		'admin_search_setting_include_admin_pages',
		'admin-search',
		'admin_search_section_sources'
	);

	add_settings_field(
		'include_comments',
		__( 'Comments', 'admin-search' ),
		'admin_search_setting_include_comments',
		'admin-search',
		'admin_search_section_sources'
	);

	add_settings_field(
		'include_users',
		__( 'Users', 'admin-search' ),
		'admin_search_setting_include_users',
		'admin-search',
		'admin_search_section_sources'
	);

	add_settings_field(
		'external_websites',
		__( 'External Websites', 'admin-search' ),
		'admin_search_setting_external_websites',
		'admin-search',
		'admin_search_section_sources'
	);

}

add_action( 'admin_init', 'admin_search_settings_init' );



/*
 *	Add plugin settings link to plugin item on Plugins page
 */
function admin_search_settings_page_link( $links ) {

	$links[] = '<a href="' . admin_url( 'options-general.php?page=admin-search' ) . '">' . __( 'Settings', 'admin-search' ) . '</a>';

	return $links;

}

add_filter( 'plugin_action_links_' . plugin_basename( plugin_dir_path( __FILE__ ) . 'admin-search.php' ), 'admin_search_settings_page_link' );




/*
 *	Render plugin settings page
 */
function admin_search_render_plugin_settings_page() {
	
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Admin Search Settings', 'admin-search' ); ?></h1>

	<form action="options.php" method="post">
	<?php
		do_settings_sections( 'admin-search' );

		settings_fields( 'admin_search' );

		submit_button();
	?>
	</form>
</div>
<?php

}



/*
 *	Sanitize and validate plugin settings
 */
function admin_search_sanitize_setting_values( $input ) {

	// Validate 'display_on_keypress' setting
	if ( isset( $input[ 'display_on_keypress' ] ) ) {
		$input[ 'display_on_keypress' ] = 1;
	} else {
		$input[ 'display_on_keypress' ] = 0;
	}

	// Validate 'autosearch' setting
	if ( isset( $input[ 'autosearch' ] ) ) {
		$input[ 'autosearch' ] = 1;
	} else {
		$input[ 'autosearch' ] = 0;
	}

	// Validate 'highlight_query' setting
	if ( isset( $input[ 'highlight_query' ] ) ) {
		$input[ 'highlight_query' ] = 1;
	} else {
		$input[ 'highlight_query' ] = 0;
	}

	// Validate 'show_when_viewing_site' setting
	if ( isset( $input[ 'show_when_viewing_site' ] ) ) {
		$input[ 'show_when_viewing_site' ] = 1;
	} else {
		$input[ 'show_when_viewing_site' ] = 0;
	}

	/*
	// Validate 'show_when_viewing_site' setting
	if ( isset( $input[ 'show_when_viewing_site' ] ) ) {
		$user_roles = new WP_Roles();
		$user_roles_names = $user_roles -> get_names();

		unset( $user_roles_names[ 'subscriber' ] );

		if ( ! in_array( $input[ 'show_when_viewing_site' ], $user_roles_names ) ) {
			$input[ 'show_when_viewing_site' ] = 'editor';
		}
	}
	*/

	// Validate 'result_previews' setting
	if ( isset( $input[ 'result_previews' ] ) ) {
		$input[ 'result_previews' ] = 1;
	} else {
		$input[ 'result_previews' ] = 0;
	}

	// Validate 'show_suggestions' setting
	if ( isset( $input[ 'show_suggestions' ] ) ) {
		$input[ 'show_suggestions' ] = 1;
	} else {
		$input[ 'show_suggestions' ] = 0;
	}

	// Validate 'hide_internal_post_types' setting
	if ( isset( $input[ 'hide_internal_post_types' ] ) ) {
		$input[ 'hide_internal_post_types' ] = 1;
	} else {
		$input[ 'hide_internal_post_types' ] = 0;
	}

	// Validate 'post_types' setting — new format: slug => [enabled field names]
	// A post type is only included when its toggle is checked (the 'enabled' sub-key
	// is present in the submitted data).  Unchecked toggles produce no sub-key at all.
	if ( isset( $input[ 'post_types' ] ) && is_array( $input[ 'post_types' ] ) ) {
		$post_types = array();

		foreach ( $input[ 'post_types' ] as $slug => $pt_data ) {
			$slug = sanitize_key( $slug );

			if ( ! post_type_exists( $slug ) ) {
				continue;
			}

			// Toggle must be on
			if ( ! isset( $pt_data[ 'enabled' ] ) ) {
				continue;
			}

			$fields = array();

			if ( isset( $pt_data[ 'fields' ] ) && is_array( $pt_data[ 'fields' ] ) ) {
				foreach ( $pt_data[ 'fields' ] as $field ) {
					if ( $field === 'body' ) {
						$fields[] = 'body';
					} elseif ( preg_match( '/^[a-zA-Z0-9_\-]+$/', $field ) ) {
						$fields[] = sanitize_text_field( $field );
					}
				}
			}

			$post_types[ $slug ] = array_unique( $fields );
		}

		$input[ 'post_types' ] = $post_types;
	} else {
		$input[ 'post_types' ] = array();
	}

	// Validate 'taxonomies' setting
	if ( isset( $input[ 'taxonomies' ] ) ) {
		$taxonomies = array();

		foreach ( $input[ 'taxonomies' ] as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$taxonomies[] = $taxonomy;
			}
		}

		$input[ 'taxonomies' ] = $taxonomies;
	}

	// Validate 'include_admin_pages' setting
	if ( isset( $input[ 'include_admin_pages' ] ) ) {
		$input[ 'include_admin_pages' ] = 1;
	} else {
		$input[ 'include_admin_pages' ] = 0;
	}

	// Validate 'include_comments' setting
	if ( isset( $input[ 'include_comments' ] ) ) {
		$input[ 'include_comments' ] = 1;
	} else {
		$input[ 'include_comments' ] = 0;
	}

	// Validate 'include_users' setting
	if ( isset( $input[ 'include_users' ] ) ) {
		$input[ 'include_users' ] = 1;
	} else {
		$input[ 'include_users' ] = 0;
	}

	// Validate 'external_websites' setting
	if ( isset( $input[ 'external_websites' ] ) ) {
		$external_websites = array();

		foreach ( explode( "\r\n", $input[ 'external_websites' ] ) as $external_website ) {
			if ( filter_var( $external_website, FILTER_VALIDATE_URL ) ) {
				$external_websites[] = trim( $external_website );
			}
		}

		$input[ 'external_websites' ] = implode( "\n", $external_websites );
	} else {
		$input[ 'external_websites' ] = '';
	}

	return $input;

}



/*
 *	Set up 'display_on_keypress' setting
 */
function admin_search_setting_display_on_keypress() {

	echo "<div id='admin_search_setting_display_on_keypress'><label><input type='checkbox' name='admin_search_settings[display_on_keypress]' value='1'";

	if ( admin_search_setting( 'display_on_keypress' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Display the Admin Search box on keypress', 'admin-search' ) . "</label></p><p class='description'>" . str_replace( array(
		'[code]',
		'[/code]'
	), array(
		'<code style="white-space: nowrap">',
		'</code>'
	), esc_html__( 'When enabled, the Admin Search box will display instantly on keypress except when a text field is in focus. The Admin Search box can be opened anytime by clicking the search icon in the admin bar.', 'admin-search' ) ) . "</p></div>";

}



/*
 *	Set up 'autosearch' setting
 */
function admin_search_setting_autosearch() {

	echo "<div id='admin_search_setting_autosearch'><label><input type='checkbox' name='admin_search_settings[autosearch]' value='1'";

	if ( admin_search_setting( 'autosearch' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Begin searching as you type', 'admin-search' ) . "</label></p><p class='description'>" . str_replace( array(
		'[code]',
		'[/code]'
	), array(
		'<code style="white-space: nowrap">',
		'</code>'
	), esc_html__( 'When enabled, search results will be displayed as you type. When disabled, press [code]Enter[/code] to search.', 'admin-search' ) ) . "</p></div>";

}



/*
 *	Set up 'result_count' setting
 */
function admin_search_setting_result_count() {

	$field = "<select name='admin_search_settings[result_count]'>";
	$options = array(
		5 => __( '5 (Recommended)', 'admin-search' ),
		10 => '10',
		20 => '20',
		50 => '50',
		100 => '100'
	);

	// Older versions of the plugin had different values, this will preserve legacy values until users change it
	if ( ! isset( $options[ admin_search_setting( 'result_count' ) ] ) ) {
		$options[ admin_search_setting( 'result_count' ) ] = admin_search_setting( 'result_count' );
	}

	foreach ( $options as $count => $label ) {
		$field .= "<option value='" . esc_attr( $count ) . "'";

		if ( $count == admin_search_setting( 'result_count' ) ) {
			$field .= " selected";
		}

		$field .= ">" . esc_html( $label ) . "</option>";
	}

	$field .= "</select>";

	/* translators: %s: the settings field, rendered as a dropdown inline with this label */
	echo "<div id='admin_search_setting_result_count'>" . sprintf( esc_html__( '%s results per source', 'admin-search' ), $field ) . "<p class='description'>" . esc_html__( 'If a search returns more results than the limit, an option to load more will be displayed in the search box. A high result limit may cause longer load times when searching.', 'admin-search' ) . "</p></div>";

}



/*
 *	Set up 'highlight_query' setting
 */
function admin_search_setting_highlight_query() {

	echo "<div id='admin_search_setting_highlight_query'><label><input type='checkbox' name='admin_search_settings[highlight_query]' value='1'";

	if ( admin_search_setting( 'highlight_query' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Highlight matching keywords in the search results', 'admin-search' ) . "</label></p></div>";

}



/*
 *	Set up 'show_when_viewing_site' setting
 */
function admin_search_setting_show_when_viewing_site() {

	echo "<div id='admin_search_setting_show_when_viewing_site'><label><input type='checkbox' name='admin_search_settings[show_when_viewing_site]' value='1'";

	if ( admin_search_setting( 'show_when_viewing_site' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Allow access to the Admin Search box on the website', 'admin-search' ) . "</label></p><p class='description'>" . esc_html__( 'When enabled, the Admin Search box will be accessible to admins when viewing the website.', 'admin-search' ) . "</p></div>";

}



/*
 *	Set up 'result_previews' setting
 */
function admin_search_setting_result_previews() {

	echo "<div id='admin_search_setting_result_previews'><label><input type='checkbox' name='admin_search_settings[result_previews]' value='1'";

	if ( admin_search_setting( 'result_previews' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Display previews in search results', 'admin-search' ) . "<span style='margin-left:4px;padding:4px 6px;background:rgb(34 112 177 / 10%);color:rgb(34 112 177);font-weight:500;border-radius:2px'>" . esc_html__( 'Beta', 'admin-search' ) . "</span></label></p><p class='description'>" . esc_html__( 'When enabled, permalinks of supported search results can be previewed directly in the Admin Search box.', 'admin-search' ) . "</p></div>";

}



/*
 *	Set up 'show_suggestions' setting
 */
function admin_search_setting_show_suggestions() {

	echo "<div id='admin_search_setting_show_suggestions'><label><input type='checkbox' name='admin_search_settings[show_suggestions]' value='1'";

	if ( admin_search_setting( 'show_suggestions' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Show suggestions as you type', 'admin-search' ) . "<span style='margin-left:4px;padding:4px 6px;background:rgb(34 112 177 / 10%);color:rgb(34 112 177);font-weight:500;border-radius:2px'>" . esc_html__( 'Beta', 'admin-search' ) . "</span></label></p><p class='description'>";

	global $wpdb;

	$table_name = $wpdb -> prefix . 'admin_search__searches';
	$top_searches = $wpdb -> get_results( $wpdb -> prepare( "SELECT * FROM {$table_name} ORDER BY LENGTH(query) DESC, occurrences DESC, results DESC LIMIT 100" ) );

	if ( empty( $top_searches ) ) {
		esc_html_e( 'When enabled, search suggestions will be displayed as you type. These suggestions are based on past searches – the more you search, the more accurate the suggestions become.', 'admin-search' );
	} else {
		echo str_replace(
			array(
				'[span clearSuggestionContainer]',
				'[a clearSuggestions]',
				'[/a]',
				'[/span]'
			),
			array(
				'<span id="admin-search-clear-searches-button">',
				'<a href="#" onclick="AS_clear_searches(\'' . wp_create_nonce( 'clear-searches' ) . '\')" style="color: #d63638">',
				'</a>',
				'</span>'
			),
			esc_html__( 'When enabled, search suggestions will be displayed as you type. These suggestions are based on past searches – the more you search, the more accurate the suggestions become.[span clearSuggestionContainer] [a clearSuggestions]Clear search suggestions[/a].[/span]', 'admin-search' )
		);
	}
	echo "</p></div>";

}



/*
 *	Set up 'hide_internal_post_types' setting
 */
function admin_search_setting_hide_internal_post_types() {

	$denylist = admin_search_internal_post_types();

	echo "<div id='admin_search_setting_hide_internal_post_types'><label><input type='checkbox' name='admin_search_settings[hide_internal_post_types]' value='1'";

	if ( admin_search_setting( 'hide_internal_post_types' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Hide page builder and internal content types from search results', 'admin-search' ) . "</label><p class='description'>" . esc_html__( 'When enabled, known page builder templates (Bricks, Elementor, FSE templates, ACF, etc.) are excluded from search results. Turn this off to search those types. The exact list is filterable via the admin_search_internal_post_types hook.', 'admin-search' ) . "</p><p class='description'><em>" . esc_html__( 'Filtered types:', 'admin-search' ) . "</em> <code>" . esc_html( implode( ', ', $denylist ) ) . "</code></p></div>";

}



/*
 *	Return the list of internal / page-builder post type slugs that are excluded
 *	from search results when the 'hide_internal_post_types' setting is on.
 *
 *	Exposed via the `admin_search_internal_post_types` filter so themes and other
 *	plugins can extend or shrink the list without editing plugin source.
 */
function admin_search_internal_post_types() {

	return apply_filters( 'admin_search_internal_post_types', array(
		// Bricks Builder
		'bricks_template',
		'bricks_font',
		'bricks_global_element',
		// Elementor
		'elementor_library',
		'elementor_font',
		'e-landing-page',
		'e-floating-buttons',
		// Core Full Site Editing
		'wp_block',
		'wp_navigation',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		// Advanced Custom Fields
		'acf-post-type',
		'acf-taxonomy',
		'acf-field-group',
		// Cornerstone / Pro
		'cs_template',
		'cs_global_block',
		// Misc internal
		'oembed_cache',
	) );

}



/*
 *	Return label overrides for internal / page-builder post types so that when
 *	they DO appear in search (admin opted out of hiding) the result label is
 *	friendlier than the raw slug.
 *
 *	Exposed via the `admin_search_post_type_label_overrides` filter.
 */
function admin_search_post_type_label_overrides() {

	return apply_filters( 'admin_search_post_type_label_overrides', array(
		'bricks_template'        => __( 'Bricks Template', 'admin-search' ),
		'bricks_font'            => __( 'Bricks Font', 'admin-search' ),
		'bricks_global_element'  => __( 'Bricks Global Element', 'admin-search' ),
		'elementor_library'      => __( 'Elementor Template', 'admin-search' ),
		'elementor_font'         => __( 'Elementor Font', 'admin-search' ),
		'e-landing-page'         => __( 'Elementor Landing Page', 'admin-search' ),
		'e-floating-buttons'     => __( 'Elementor Floating Buttons', 'admin-search' ),
		'wp_block'               => __( 'Reusable Block', 'admin-search' ),
		'wp_navigation'          => __( 'Navigation Block', 'admin-search' ),
		'wp_template'            => __( 'FSE Template', 'admin-search' ),
		'wp_template_part'       => __( 'FSE Template Part', 'admin-search' ),
		'wp_global_styles'       => __( 'FSE Global Styles', 'admin-search' ),
		'acf-post-type'          => __( 'ACF Post Type', 'admin-search' ),
		'acf-taxonomy'           => __( 'ACF Taxonomy', 'admin-search' ),
		'acf-field-group'        => __( 'ACF Field Group', 'admin-search' ),
		'cs_template'            => __( 'Cornerstone Template', 'admin-search' ),
		'cs_global_block'        => __( 'Cornerstone Global Block', 'admin-search' ),
		'oembed_cache'           => __( 'oEmbed Cache', 'admin-search' ),
	) );

}



/*
 *	Add 'sources' settings section summary copy
 */
function admin_search_section_sources_text() {

	echo '<p>' . esc_html__( 'Choose what appears in search results.', 'admin-search' ) . '</p>';

}



/*
 *	Return all distinct meta keys that exist in the database for a given post type,
 *	excluding WordPress internal housekeeping keys that are never useful to search.
 */
function admin_search_get_post_type_meta_keys( $post_type ) {

	global $wpdb;

	$excluded = array(
		'_edit_lock', '_edit_last',
		'_wp_trash_meta_status', '_wp_trash_meta_time',
		'_wp_desired_post_slug', '_wp_old_date', '_wp_old_slug',
	);

	$keys = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT pm.meta_key
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type = %s
		   AND pm.meta_key NOT LIKE '\_%%'
		 ORDER BY pm.meta_key",
		$post_type
	) );

	return array_values( array_diff( $keys ?: array(), $excluded ) );

}


/*
 *	Set up 'post_types' setting
 */
function admin_search_setting_post_types() {

	$current = admin_search_setting( 'post_types', array() );

	echo '<div id="admin_search_plugin_setting_post_types">';

	// WordPress internal / system post types that should never appear in the
	// settings list.  Exposed via a filter so plugin/theme authors can adjust.
	//
	// We intentionally exclude FSE types (wp_template, wp_template_part, …) even
	// though they have show_ui = true, because they are low-level site-building
	// primitives rather than content a site admin would want to search for.
	$_excluded_pt = apply_filters( 'admin_search_excluded_post_types', array(
		'revision',            // post revisions — never directly editable
		'nav_menu_item',       // navigation menu entries
		'custom_css',          // Customizer custom CSS
		'customize_changeset', // Customizer draft changesets
		'oembed_cache',        // oEmbed response cache
		'user_request',        // GDPR data erasure requests
		'wp_template',         // FSE site templates
		'wp_template_part',    // FSE template parts
		'wp_global_styles',    // FSE global styles
		'wp_navigation',       // block-based nav menus
		'wp_font_family',      // Font Library families
		'wp_font_face',        // Font Library faces
	) );

	// Use show_ui rather than public: this is an admin tool, so the right criterion
	// is whether a post type has an edit screen — not whether it's front-end queryable.
	// This naturally includes non-public admin-only CPTs (e.g. internal CRM types)
	// and wp_block (Patterns) without needing special-cases.
	foreach ( get_post_types( array( 'show_ui' => true ), 'object' ) as $post_type ) {

		if ( in_array( $post_type->name, $_excluded_pt, true ) ) {
			continue;
		}

		$slug           = $post_type->name;
		$label          = $post_type->label;
		$is_enabled     = array_key_exists( $slug, $current );
		$enabled_fields = $is_enabled ? (array) $current[ $slug ] : array();

		// Merge meta keys from DB with any saved keys that may no longer be in the DB
		// (e.g. all posts with that meta deleted) so saved values are still shown.
		$db_meta_keys    = admin_search_get_post_type_meta_keys( $slug );
		$saved_meta_keys = array_values( array_filter( $enabled_fields, function ( $f ) {
			return $f !== 'body';
		} ) );
		$all_meta_keys = array_unique( array_merge( $db_meta_keys, $saved_meta_keys ) );
		sort( $all_meta_keys );

		$toggle_id = 'as-pt-toggle-' . esc_attr( $slug );

		// Build the post type icon HTML.
		// menu_icon can be a dashicons class, a data URI, a full URL, or empty.
		$raw_icon = ! empty( $post_type->menu_icon ) ? $post_type->menu_icon : '';
		if ( empty( $raw_icon ) || $raw_icon === 'none' ) {
			$icon_defaults = array(
				'post'       => 'dashicons-admin-post',
				'page'       => 'dashicons-admin-page',
				'attachment' => 'dashicons-admin-media',
				'wp_block'   => 'dashicons-block-default',
			);
			$raw_icon = isset( $icon_defaults[ $slug ] ) ? $icon_defaults[ $slug ] : 'dashicons-admin-post';
		}
		if ( strpos( $raw_icon, 'dashicons-' ) === 0 ) {
			$icon_html = '<span class="dashicons ' . esc_attr( $raw_icon ) . ' as-pt-icon" aria-hidden="true"></span>';
		} elseif ( strpos( $raw_icon, 'data:' ) === 0 ) {
			$icon_html = '<span class="as-pt-icon as-pt-icon-img" aria-hidden="true" style="background-image:url(\'' . esc_attr( $raw_icon ) . '\')"></span>';
		} else {
			$icon_html = '<span class="as-pt-icon as-pt-icon-img" aria-hidden="true" style="background-image:url(\'' . esc_url( $raw_icon ) . '\')"></span>';
		}

		echo '<div class="as-pt-item">';

		// Hidden checkbox drives both the visual toggle and the CSS expand/collapse
		echo '<input type="checkbox"'
			. ' id="' . $toggle_id . '"'
			. ' class="as-pt-toggle"'
			. ' name="admin_search_settings[post_types][' . esc_attr( $slug ) . '][enabled]"'
			. ' value="1"'
			. ( $is_enabled ? ' checked' : '' )
			. '>';

		// Row label: icon + post type name on the left, visual toggle track on the right
		echo '<label for="' . $toggle_id . '" class="as-pt-label">'
			. $icon_html
			. '<span class="as-pt-name">' . esc_html( $label ) . '</span>'
			. '<span class="as-pt-track" aria-hidden="true"></span>'
			. '</label>';

		// Fields panel — CSS shows/hides this via :checked ~ .as-pt-fields
		echo '<div class="as-pt-fields">';
		echo '<div class="as-field-list">';

		// Body (post_content + post_excerpt)
		$body_id  = 'as-field-' . esc_attr( $slug ) . '-body';
		echo '<label class="as-field-item" for="' . $body_id . '">'
			. '<input type="checkbox"'
			. ' id="' . $body_id . '"'
			. ' class="as-field-toggle"'
			. ' name="admin_search_settings[post_types][' . esc_attr( $slug ) . '][fields][]"'
			. ' value="body"'
			. ( in_array( 'body', $enabled_fields ) ? ' checked' : '' )
			. '>'
			. '<span class="as-field-name">' . esc_html__( 'Body', 'admin-search' ) . '</span>'
			. '<span class="as-field-hint">' . esc_html__( 'Content and excerpt', 'admin-search' ) . '</span>'
			. '<span class="as-field-track" aria-hidden="true"></span>'
			. '</label>';

		// Custom / meta fields
		$meta_labels = apply_filters( 'admin_search_meta_labels', array() );
		if ( ! is_array( $meta_labels ) ) {
			$meta_labels = array();
		}

		foreach ( $all_meta_keys as $meta_key ) {
			// Sanitise the meta key to a safe string for use in an HTML id attribute
			$safe_suffix = preg_replace( '/[^a-z0-9_-]/', '-', strtolower( $meta_key ) );
			$field_id    = 'as-field-' . esc_attr( $slug ) . '-' . $safe_suffix;

			// Resolve the display label: prefer the human-readable label from the
			// `admin_search_meta_labels` filter (e.g. "Regular price" for `_regular_price`),
			// fall back to the meta key with its leading underscore stripped.
			if ( isset( $meta_labels[ $meta_key ] ) && is_string( $meta_labels[ $meta_key ] ) && $meta_labels[ $meta_key ] !== '' ) {
				$display = $meta_labels[ $meta_key ];
			} else {
				$display = ltrim( $meta_key, '_' ); // strip leading _ for the readable label
			}

			echo '<label class="as-field-item" for="' . $field_id . '">'
				. '<input type="checkbox"'
				. ' id="' . $field_id . '"'
				. ' class="as-field-toggle"'
				. ' name="admin_search_settings[post_types][' . esc_attr( $slug ) . '][fields][]"'
				. ' value="' . esc_attr( $meta_key ) . '"'
				. ( in_array( $meta_key, $enabled_fields ) ? ' checked' : '' )
				. '>'
				. '<span class="as-field-name">' . esc_html( $display ) . '</span>'
				. '<span class="as-field-hint as-field-hint-meta">' . esc_html( $meta_key ) . '</span>'
				. '<span class="as-field-track" aria-hidden="true"></span>'
				. '</label>';
		}

		// Taxonomy toggles — allows filtering posts by category, tag, or any custom taxonomy
		$pt_taxonomies    = get_object_taxonomies( $slug, 'objects' );
		$public_taxonomies = array_filter( $pt_taxonomies, function ( $tax ) {
			return $tax->public || ( isset( $tax->show_ui ) && $tax->show_ui );
		} );

		if ( ! empty( $public_taxonomies ) ) {
			echo '<div class="as-field-section-label">' . esc_html__( 'Taxonomies', 'admin-search' ) . '</div>';

			foreach ( $public_taxonomies as $tax_slug => $tax_obj ) {
				$safe_suffix = preg_replace( '/[^a-z0-9_-]/', '-', strtolower( $tax_slug ) );
				$field_id    = 'as-field-' . esc_attr( $slug ) . '-tax-' . $safe_suffix;

				echo '<label class="as-field-item" for="' . $field_id . '">'
					. '<input type="checkbox"'
					. ' id="' . $field_id . '"'
					. ' class="as-field-toggle"'
					. ' name="admin_search_settings[post_types][' . esc_attr( $slug ) . '][fields][]"'
					. ' value="' . esc_attr( $tax_slug ) . '"'
					. ( in_array( $tax_slug, $enabled_fields ) ? ' checked' : '' )
					. '>'
					. '<span class="as-field-name">' . esc_html( $tax_obj->label ) . '</span>'
					. '<span class="as-field-hint as-field-hint-taxonomy">' . esc_html( $tax_slug ) . '</span>'
					. '<span class="as-field-track" aria-hidden="true"></span>'
					. '</label>';
			}
		}

		echo '</div>'; // .as-field-list

		echo '<p class="description as-field-note">'
			. esc_html__( 'Title, slug, and ID are always searchable. With no fields enabled, posts are only findable by title, slug, ID, author, or date.', 'admin-search' )
			. '</p>';

		echo '</div>'; // .as-pt-fields
		echo '</div>'; // .as-pt-item
	}

	echo '</div>'; // #admin_search_plugin_setting_post_types

}



/*
 *	Set up 'taxonomies' setting
 */
function admin_search_setting_taxonomies() {

	echo "<div id='admin_search_plugin_setting_taxonomies'>";

	foreach ( get_taxonomies( array(), 'object' ) as $taxonomy ) {
		if ( $taxonomy -> {'public'} ) {
			echo "<p><label><input type='checkbox' name='admin_search_settings[taxonomies][]' value='" . esc_attr( $taxonomy -> name ) . "'";

			if ( in_array( $taxonomy -> name, admin_search_setting( 'taxonomies', array() ) ) ) {
				echo " checked";
			}

			echo "> " . esc_html( $taxonomy -> label ) . "</label></p>";
		}
	}

	echo "</div>";

}



/*
 *	Set up 'include_admin_pages' setting
 */
function admin_search_setting_include_admin_pages() {

	echo "<div id='admin_search_setting_include_admin_pages'><label><input type='checkbox' name='admin_search_settings[include_admin_pages]' value='1'";

	if ( admin_search_setting( 'include_admin_pages' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Include admin pages in the search results', 'admin-search' ) . "</label><p class='description'>" . esc_html__( 'Users will only see admin pages they have access to.', 'admin-search' ) . "</p></div>";

}



/*
 *	Set up 'include_comments' setting
 */
function admin_search_setting_include_comments() {

	echo "<div id='admin_search_setting_include_comments'><label><input type='checkbox' name='admin_search_settings[include_comments]' value='1'";

	if ( admin_search_setting( 'include_comments' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Include comments in the search results', 'admin-search' ) . "</label></div>";

}



/*
 *	Set up 'include_users' setting
 */
function admin_search_setting_include_users() {

	echo "<div id='admin_search_setting_include_users'><label><input type='checkbox' name='admin_search_settings[include_users]' value='1'";

	if ( admin_search_setting( 'include_users' ) ) {
		echo " checked";
	}

	echo "> " . esc_html__( 'Include user profiles in the search results', 'admin-search' ) . "</label></div>";

}



/*
 *	Set up 'external_websites' setting
 */
function admin_search_setting_external_websites() {

	echo "<div id='admin_search_setting_external_websites'><textarea name='admin_search_settings[external_websites]' class='large-text code' rows='3' spellcheck='false'>";

	if ( admin_search_setting( 'external_websites' ) ) {
		echo esc_html( admin_search_setting( 'external_websites' ) );
	}

	echo "</textarea><p class='description'>" . str_replace( array(
		'[code]',
		'[/code]'
	), array(
		'<code style="white-space: nowrap">',
		'</code>'
	), esc_html__( 'Display links to external websites in search results. Use [code]%q%[/code] in place of the query value in the URL. Separate multiple website URLs with line breaks.', 'admin-search' ) ) . "</p><p class='description'>" . str_replace( array(
		'[code]',
		'[/code]'
	), array(
		'<code style="white-space: nowrap">',
		'</code>'
	), esc_html__( 'Example: Add [code]https://wordpress.org/search/%q%[/code] to display a link to the WordPress.org search pre-populated with your search query.', 'admin-search' ) ) . "</p></div>";

}



/*
 *	Apply per-post-type field settings to search queries.
 *
 *	These filters run at priority 100 so that they override any other plugin that
 *	has added fields at the default priority (10).  When a post type has been
 *	explicitly configured in Admin Search settings the stored configuration is
 *	authoritative; when it hasn't been configured the filters are a no-op.
 */
add_filter( 'admin_search_fields', function( $fields, $post_type ) {

	$pt_settings = admin_search_setting( 'post_types', array() );

	if ( ! array_key_exists( $post_type, $pt_settings ) ) {
		return $fields;
	}

	$enabled = (array) $pt_settings[ $post_type ];

	// Title and slug are always included regardless of user configuration
	$result = array( 'post_title', 'post_name' );

	// Body (content + excerpt) is opt-in
	if ( in_array( 'body', $enabled ) ) {
		$result[] = 'post_content';
		$result[] = 'post_excerpt';
	}

	return $result;

}, 100, 2 );

add_filter( 'admin_search_meta_queries', function( $meta_fields, $post_type ) {

	$pt_settings = admin_search_setting( 'post_types', array() );

	if ( ! array_key_exists( $post_type, $pt_settings ) ) {
		return $meta_fields;
	}

	$enabled = (array) $pt_settings[ $post_type ];

	// Return only the explicitly enabled meta keys (everything that isn't 'body' or a taxonomy slug)
	return array_values( array_filter( $enabled, function( $f ) {
		return $f !== 'body' && ! taxonomy_exists( $f );
	} ) );

}, 100, 2 );

/*
 *	Feed the per-post-type enabled taxonomy list into the field-level taxonomy search.
 *	Runs at priority 100 to override any lower-priority defaults.
 */
add_filter( 'admin_search_taxonomies', function( $taxonomies, $post_type ) {

	$pt_settings = admin_search_setting( 'post_types', array() );

	if ( ! array_key_exists( $post_type, $pt_settings ) ) {
		return $taxonomies;
	}

	$enabled = (array) $pt_settings[ $post_type ];

	// Return only the taxonomy slugs stored in the enabled fields list
	return array_values( array_filter( $enabled, function( $f ) {
		return $f !== 'body' && taxonomy_exists( $f );
	} ) );

}, 100, 2 );
