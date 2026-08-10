<?php
/*
 *	Strip HTML tags and content from menu names
 */
function admin_search_sanitize_menu_name(  $str = ''  ) {

	return trim( wp_strip_all_tags( preg_replace( '/>.*?</s', '><', $str ) ) );

}


/*
 *	Convert hex color values to RGB values
 */
function admin_search_RGB(  $hex  ) {

	if ( $hex[ 0 ] == '#' )
		$hex = substr( $hex, 1 );

	if ( strlen( $hex ) == 3 ) {
		$hex = $hex[ 0 ] . $hex[ 0 ] . $hex[ 1 ] . $hex[ 1 ] . $hex[ 2 ] . $hex[ 2 ];
	}

	return hexdec( $hex[ 0 ] . $hex[ 1 ] ) . ' ' . hexdec( $hex[ 2 ] . $hex[ 3 ] ) . ' ' . hexdec( $hex[ 4 ] . $hex[ 5 ] );

}


/*
 *	Add plugin assets
 */
function admin_search_load_plugin_assets() {

	if ( isset( $_GET[ 'admin_search_preview' ] ) && $_GET[ 'admin_search_preview' ] ) {
		add_filter( 'show_admin_bar', '__return_false' );

		wp_register_script( 'admin-search-preview-script', '', array(), ADMIN_SEARCH_VERSION, true );
		wp_enqueue_script( 'admin-search-preview-script'  );
		wp_add_inline_script( 'admin-search-preview-script', "window.parent.postMessage({display:true},'" . get_site_url() . "');" );
	}

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( ! current_user_can( 'edit_others_pages' ) ) {
		return;
	}

	if ( ! is_admin() && ! admin_search_setting( 'show_when_viewing_site' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}


	wp_enqueue_style( 'admin-search-stylesheet', plugin_dir_url( __FILE__ ) . 'assets/style.css', array(), ADMIN_SEARCH_VERSION );
	wp_enqueue_script( 'jquery-ui-draggable' );
	wp_enqueue_script( 'admin-search-script', plugin_dir_url( __FILE__ ) . 'assets/script.js', array( 'jquery', 'jquery-ui-draggable', 'wp-util', 'underscore' ), ADMIN_SEARCH_VERSION, array(
		'in_footer' => true
	) );


	$formatted_menu = array();

	if ( is_admin() ) {
		global $menu, $submenu;

		if ( current_user_can( 'manage_options' ) ) {
			foreach ( $menu as $menu_item ) {
				if ( ! $menu_item[ 0 ] ) {
					continue;
				}

				$menu_item_name = admin_search_sanitize_menu_name( $menu_item[ 0 ] );
				$menu_item_title = $menu_item_name;
				$menu_item_url = $menu_item[ 2 ];

				if ( isset( $submenu[ $menu_item[ 2 ] ] ) ) {
					foreach ( $submenu[ $menu_item[ 2 ] ] as $submenu_item ) {
						if ( ! $submenu_item[ 0 ] ) {
							continue;
						}

						if ( ! current_user_can( $submenu_item[ 1 ] ) ) {
							continue;
						}

						$submenu_item_name = admin_search_sanitize_menu_name( $submenu_item[ 0 ] );
						$submenu_item_title = $submenu_item_name;
						$submenu_item_url = $submenu_item[ 2 ];

						if ( strpos( $submenu_item_url, '.php' ) === false ) {
							$submenu_item_url = 'admin.php?page=' . $submenu_item_url;
						}

						$formatted_menu[ $submenu_item[ 2 ] ] = array(
							'edit_post_link'	=>	admin_url( $submenu_item_url ),
							'name'				=>	strtolower( $submenu_item_name . ' ' . $menu_item_name ),
							'title'				=>	$submenu_item_title,
							'status'			=>	null
						);

						if ( strpos( $submenu_item_name, $menu_item_name ) === false ) {
							$formatted_menu[ $submenu_item[ 2 ] ][ 'status' ] = $menu_item_name;
						}
					}

					continue;
				}

				if ( ! current_user_can( $menu_item[ 1 ] ) ) {
					continue;
				}

				if ( strpos( $menu_item_url, '.php' ) === false ) {
					$menu_item_url = 'admin.php?page=' . $menu_item_url;
				}

				$formatted_menu[ $menu_item[ 2 ] ] = array(
					'edit_post_link'	=>	admin_url( $menu_item_url ),
					'name'				=>	strtolower( $menu_item_name ),
					'title'				=>	$menu_item_title,
					'status'			=>	null
				);
			}
		}
	}


	$script_data = array(
		'site_url'             => get_site_url(),
		'ajax_url'             => admin_url( 'admin-ajax.php' ),
		'nonce'                => wp_create_nonce( 'admin-search' ),
		'display_on_keypress'  => (bool) admin_search_setting( 'display_on_keypress' ),
		'autosearch'           => (bool) admin_search_setting( 'autosearch' ),
		'include_admin_pages'  => (bool) admin_search_setting( 'include_admin_pages' ),
		'show_suggestions'     => (bool) admin_search_setting( 'show_suggestions' ),
		'strings'              => array(
			'confirm_clear_searches' => __( 'This will clear all search history and suggestions will be reset. Are you sure you want to continue?', 'admin-search' )
		),
		'menu'                 => $formatted_menu,
		'admin_pages_label'    => __( 'Admin Pages', 'admin-search' ),
	);

	if ( admin_search_setting( 'show_suggestions' ) ) {
		global $wpdb;

		$table_name = $wpdb -> prefix . 'admin_search__searches';
		$top_searches = $wpdb -> get_results( $wpdb -> prepare( "SELECT * FROM {$table_name} ORDER BY LENGTH(query) DESC, occurrences DESC, results DESC LIMIT 100" ) );

		$script_data[ 'top_searches' ] = ! empty( $top_searches ) ? array_column( $top_searches, 'query' ) : array();
	}

	$inline_script = 'var admin_search=' . wp_json_encode( $script_data ) . ';';

	wp_add_inline_script( 'admin-search-script', $inline_script );


    global $_wp_admin_css_colors;

	$inline_style = "";

	if ( is_admin() && ! in_array( get_user_option( 'admin_color' ), array( 'light', 'modern' ) ) ) {
		$scheme = $_wp_admin_css_colors[ get_user_option( 'admin_color' ) ];

		$inline_style .= ":root{--as-color-background:" . $scheme -> colors[ 1 ];
		$inline_style .= ";--as-color-background-rgb:" . admin_search_RGB( $scheme -> colors[ 1 ] );
		$inline_style .= ";--as-color-focus:" . $scheme -> colors[ 2 ];
		$inline_style .= ";--as-color-focus-text:" . $scheme -> colors[ 3 ];
		$inline_style .= ";--as-color-primary:" . $scheme -> icon_colors[ 'current' ];
		$inline_style .= ";--as-color-primary-rgb:" . admin_search_RGB( $scheme -> icon_colors[ 'current' ] );
		$inline_style .= "}@media (min-width:783px){:root{--as-color-background:" . $scheme -> colors[ 0 ];
		$inline_style .= ";--as-color-background-rgb:" . admin_search_RGB( $scheme -> colors[ 0 ] );
		$inline_style .= "}}";
	}

	wp_add_inline_style( 'admin-search-stylesheet', $inline_style );

}

add_action( 'admin_enqueue_scripts', 'admin_search_load_plugin_assets' );
add_action( 'wp_enqueue_scripts', 'admin_search_load_plugin_assets' );



/*
 *	Add search button to admin bar
 */
function admin_search_add_wb_item( $admin_bar ) {

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( ! current_user_can( 'edit_others_pages' ) ) {
		return;
	}

	if ( ! is_admin() && ! admin_search_setting( 'show_when_viewing_site' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}

	$admin_bar -> add_menu( array(
		'id'    =>	'admin-search-toggle',
		'title' =>	'<span class="ab-icon"></span><span class="screen-reader-text">' . __( 'Search WordPress', 'admin-search' ) . '</span>',
		'href'  =>	'#',
		'meta'  =>	array(
			'title' =>		__( 'Search', 'admin-search' ),
			'onclick' =>	'admin_search_modal( { toggle : true } ); return false'
		),
	) );

}


add_action( 'admin_bar_menu', 'admin_search_add_wb_item', 100 );



/*
 *	Add search UI template compontents
 */
function admin_search_ui() {

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( ! current_user_can( 'edit_others_pages' ) ) {
		return;
	}

	if ( ! is_admin() && ! admin_search_setting( 'show_when_viewing_site' ) ) {
		return;
	}

	if ( ! is_admin_bar_showing() ) {
		return;
	}


	$loading_indicator = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="24px" height="24px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve"><path d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z" transform="rotate(104.145 25 25)"><animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.6s" repeatCount="indefinite"></animateTransform></path></svg>';
?>
<script id="tmpl-admin-search-modal-template" type="text/html">
<div id="admin-search-modal" class="admin-search-no-results<?php if ( admin_search_setting( 'result_previews' ) ) { ?> admin-search-previews-enabled<?php } ?>">

	<div id="admin-search-modal-background"></div>

	<div id="admin-search-container">

		<div id="admin-search-input">

			<div id="admin-search-input-field-container">

				<div id="admin-search-input-field">

					<div id="admin-search-input-field-placeholder"><?php esc_html_e( 'Search keywords', 'admin-search' ); ?></div>

					<div id="admin-search-input-field-value-container">

						<div id="admin-search-input-field-value" contenteditable="true">{{{data.value}}}</div>

						<div id="admin-search-input-field-autocomplete">

							<div id="admin-search-input-field-autocomplete-suggestion"></div>

							<div id="admin-search-input-field-autocomplete-hint"><?php esc_html_e( 'Tab', 'admin-search' ) ?></div>

						</div>

					</div>

				</div>

				<button type="submit" id="admin-search-input-submit" tabindex="-1">

					<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 24 24" style="enable-background:new 0 0 24 24;" xml:space="preserve">
						<path d="M24,22.6l-5.9-5.9c1.4-1.8,2.3-4,2.3-6.5C20.4,4.6,15.8,0,10.2,0C4.6,0,0,4.6,0,10.2c0,5.6,4.6,10.2,10.2,10.2 c2.5,0,4.7-0.9,6.5-2.3l5.9,5.9L24,22.6z M2,10.2C2,5.7,5.7,2,10.2,2s8.2,3.7,8.2,8.2s-3.7,8.2-8.2,8.2S2,14.7,2,10.2z" />
					</svg>

				</button>

			</div>

			<div id="admin-search-status-indicator">
				<?php echo $loading_indicator ?>
			</div>

			<div id="admin-search-clear-button">
				<button type="button" title="<?php esc_attr_e( 'Clear search', 'admin-search' ); ?>">
					<svg width="24px" height="24px" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
						<polygon points="13.4142136 12 18.7071068 17.2928932 17.2928932 18.7071068 12 13.4142136 6.70710678 18.7071068 5.29289322 17.2928932 10.5857864 12 5.29289322 6.70710678 6.70710678 5.29289322 12 10.5857864 17.2928932 5.29289322 18.7071068 6.70710678"></polygon>
					</svg>
				</button>
			</div>

		</div>

		<div id="admin-search-results-container">

			<div id="admin-search-results"></div>

			<?php if ( admin_search_setting( 'result_previews' ) ) { ?>
			<div id="admin-search-result-preview">
				<div id="admin-search-result-preview-content">

					<div id="admin-search-result-preview-loading-indicator">
						<?php echo $loading_indicator ?>
					</div>

					<iframe scrolling="no"></iframe>

					<a href="#"></a>

					<div id="admin-search-result-preview-close">
						<button type="button" title="<?php echo esc_attr( __( 'Close preview', 'admin-search' ) ); ?>">
							<svg width="24px" height="24px" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
								<polygon points="13.4142136 12 18.7071068 17.2928932 17.2928932 18.7071068 12 13.4142136 6.70710678 18.7071068 5.29289322 17.2928932 10.5857864 12 5.29289322 6.70710678 6.70710678 5.29289322 12 10.5857864 17.2928932 5.29289322 18.7071068 6.70710678"></polygon>
							</svg>
						</button>
					</div>
				</div>
			</div>
			<?php } ?>
		</div>

	</div>

</div>
</script>

<script id="tmpl-admin-search-result-group-template" type="text/html">
<div class="admin-search-result-group admin-search-{{{data.post_type_name}}}-post-type" data-group-name="{{{data.post_type_name}}}" data-group-label="{{{data.post_type_title}}}" data-group-search-url="{{{data.post_type_shortcut}}}">

	<div class="admin-search-result-group-title">

		{{{data.post_type_title}}}

	</div>

	<div class="admin-search-results">

		{{{data.results}}}

	</div>

</div>
</script>

<script id="tmpl-admin-search-result-template" type="text/html">
<div class="admin-search-result<# if ( data.edit_post_link ) { #> admin-search-result-has-link<# } #><# if ( data.preview_link ) { #> admin-search-result-has-preview<# } #>"<# if ( data.preview_link ) { #> data-preview="{{{data.preview_link}}}" data-edit="{{{data.edit_post_link}}}"<# } #>>

	<div class="admin-search-result-container">

		<div class="admin-search-result-title">

			<# if ( data.edit_post_link ) { #>
			<a href="{{{data.edit_post_link}}}"<# if ( data.target == '_blank' ) { #> target="_blank"<# } #> class="admin-search-result-link">
			<# } #>
	
			{{{data.title}}}<# if ( data.status ) { #><span class="admin-search-result-status"><# if ( data.title ) { #> &mdash; <# } #>{{{data.status}}}</span><# } #>

			<# if ( data.edit_post_link ) { #>
			</a>
			<# } #>

		</div>

		<# if ( data.date ) { #>

		<div class="admin-search-result-date">

			{{{data.date}}}

		</div>

		<# } #>

		<# if ( data.preview_link ) { #>
		
		<div class="admin-search-result-preview-toggle">
			<button type="button" title="<?php esc_attr_e( 'Preview', 'admin-search' ) ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
					<path d="m4 13 .67.336.003-.005a2.42 2.42 0 0 1 .094-.17c.071-.122.18-.302.329-.52.298-.435.749-1.017 1.359-1.598C7.673 9.883 9.498 8.75 12 8.75s4.326 1.132 5.545 2.293c.61.581 1.061 1.163 1.36 1.599a8.29 8.29 0 0 1 .422.689l.002.005L20 13l.67-.336v-.003l-.003-.005-.008-.015-.028-.052a9.752 9.752 0 0 0-.489-.794 11.6 11.6 0 0 0-1.562-1.838C17.174 8.617 14.998 7.25 12 7.25S6.827 8.618 5.42 9.957c-.702.669-1.22 1.337-1.563 1.839a9.77 9.77 0 0 0-.516.845l-.008.015-.002.005-.001.002v.001L4 13Zm8 3a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path>
				</svg>
			</button>
		</div>

		<# } #>

	</div>

</div>
</script>

<script id="tmpl-admin-search-results-group-pagination-template" type="text/html">
<div class="admin-search-result-group-pagination">

	<form class="admin-search-result-group-pagination-form" method="get">

		<input type="hidden" name="source" value="{{{data.post_type.name}}}">

		<input type="hidden" name="page" value="{{{data.next_page}}}">

		<button type="submit">

			<span><?php esc_html_e( 'Load more results', 'admin-search' ) ?></span>

			<div class="admin-search-result-group-pagination-status-indicator">
				<?php echo $loading_indicator ?>
			</div>

		</button>

	</form>

</div>
</script>

<script id="tmpl-admin-search-media-result-template" type="text/html">
<div class="admin-search-result">

	<a href="{{{data.edit_post_link}}}">

		<# if ( data.attachment ) { #>

		<div class="admin-search-result-preview">

			{{{data.attachment}}}

		</div>

		<# } else { #>

		<div class="admin-search-result-file">

			<div class="admin-search-result-file-container">

				<img src="{{{data.mime_icon}}}" class="admin-search-result-file-icon">

				<div class="admin-search-result-file-title">{{{data.title}}}</div>

			</div>

		</div>

		<# } #>

	</a>

</div>
</script>
<?php

}

add_action( 'admin_footer', 'admin_search_ui' );
add_action( 'wp_footer', 'admin_search_ui' );
