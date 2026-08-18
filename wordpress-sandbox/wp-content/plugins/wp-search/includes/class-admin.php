<?php
/**
 * Admin UI
 *
 * Registers the Tools > wp->search page, admin bar node, search modal assets.
 *
 * @package WP_Search
 */

namespace WP_Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page controller.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Slug for the admin page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'wp-search';

	/**
	 * Capability required to view the page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Default keyboard shortcut key.
	 *
	 * Moved from 'j' to 't' (IA-193) to avoid colliding with the WordPress Core
	 * command palette on Cmd/Ctrl+K. Users can still override to any a–z key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_SHORTCUT_KEY = 't';

	/**
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'print_spotlight_bootstrap' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 100 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the Tools submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_tools_page(): void {
		add_management_page(
			__( 'wp->search', 'wp-search' ),
			__( 'wp->search', 'wp-search' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue modal assets on all admin pages.
	 *
	 * @since 1.0.0
	 * @param string $_hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $_hook_suffix ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! is_admin() ) {
			return;
		}

		$shortcut_key = get_option( 'wp_search_shortcut_key', self::DEFAULT_SHORTCUT_KEY );

		$dist_dir = WP_SEARCH_PLUGIN_DIR . 'assets/dist/';
		$css_file = $dist_dir . 'wp-search-modal.css';
		$js_file  = $dist_dir . 'wp-search-modal.js';
		$css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : WP_SEARCH_VERSION;
		$js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : WP_SEARCH_VERSION;

		wp_enqueue_style(
			'wp-search-modal',
			WP_SEARCH_PLUGIN_URL . 'assets/dist/wp-search-modal.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'wp-search-modal',
			WP_SEARCH_PLUGIN_URL . 'assets/dist/wp-search-modal.js',
			array(),
			$js_ver,
			true
		);

		wp_localize_script(
			'wp-search-modal',
			'wp_search_modal_config',
			array(
				'rest_url'     => rest_url( REST_Controller::NAMESPACE . REST_Controller::ROUTE ),
				'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
				'debug_mode'   => (bool) get_option( 'wp_search_debug_mode', false ),
				'shortcutKey'  => $shortcut_key,
				'strings'      => array(
					'placeholder'  => __( 'Search settings, content, products…', 'wp-search' ),
					'loading'      => __( 'Loading…', 'wp-search' ),
					'empty'        => __( 'No results found.', 'wp-search' ),
					'error'        => __( 'Something went wrong. Please try again.', 'wp-search' ),
					'navigate'     => __( 'navigate', 'wp-search' ),
					'select'       => __( 'select', 'wp-search' ),
					'shortcutHint' => sprintf( __( 'Ctrl+%s', 'wp-search' ), strtoupper( $shortcut_key ) ),
				),
			)
		);
	}

	/**
	 * Inline the flat Spotlight payload as window.WPSS_DATA on every admin screen.
	 *
	 * Printed as a raw <script> (not wp_localize_script, which stringifies
	 * scalars — see the IA-162 bug) for users with manage_options, so the Cmd+K
	 * Spotlight modal (IA-190) shows real data on every admin screen — not only
	 * on Tools > wp->search. This widens the IA-184 Tools-only scoping: the
	 * payload is identical to what the /spotlight REST route returns, and it is
	 * gated to manage_options, who can already query it via REST/CLI. The
	 * frontend reads window.WPSS_DATA before it runs and falls back to built-in
	 * sample data when it is absent.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function print_spotlight_bootstrap(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		try {
			$rest     = new REST_Controller();
			$payload  = $rest->build_spotlight_payload();
			$encoded  = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} catch ( \Throwable $e ) {
			error_log( 'wp-search bootstrap: failed to build spotlight payload: ' . $e->getMessage() );
			return;
		}

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return;
		}

		echo '<script>window.WPSS_DATA = ' . $encoded . ';</script>' . "\n";
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wp_search_settings',
			'wp_search_debug_mode',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'wp_search_settings',
			'wp_search_shortcut_key',
			array(
				'type'              => 'string',
				'default'           => self::DEFAULT_SHORTCUT_KEY,
				'sanitize_callback' => array( $this, 'sanitize_shortcut_key' ),
			)
		);

		add_settings_section(
			'wp_search_debug_section',
			__( 'Debug', 'wp-search' ),
			null,
			self::PAGE_SLUG
		);

		add_settings_field(
			'wp_search_debug_mode',
			__( 'Debug mode', 'wp-search' ),
			array( $this, 'render_debug_field' ),
			self::PAGE_SLUG,
			'wp_search_debug_section'
		);

		add_settings_section(
			'wp_search_shortcut_section',
			__( 'Keyboard Shortcut', 'wp-search' ),
			null,
			self::PAGE_SLUG
		);

		add_settings_field(
			'wp_search_shortcut_key',
			__( 'Shortcut key', 'wp-search' ),
			array( $this, 'render_shortcut_key_field' ),
			self::PAGE_SLUG,
			'wp_search_shortcut_section'
		);
	}

	/**
	 * Render the debug mode checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_debug_field(): void {
		$value = (bool) get_option( 'wp_search_debug_mode', false );
		?>
		<label>
			<input type="checkbox" name="wp_search_debug_mode" value="1" <?php checked( $value ); ?> />
			<?php esc_html_e( 'Output raw REST JSON instead of opening the modal', 'wp-search' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize the shortcut key.
	 *
	 * Rejects empty strings and non-a-z characters, falling back to default 't'.
	 *
	 * @since 1.0.0
	 * @param string $value The shortcut key value.
	 * @return string
	 */
	public function sanitize_shortcut_key( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( 1 !== preg_match( '/^[a-z]$/', $value ) ) {
			return self::DEFAULT_SHORTCUT_KEY;
		}
		return $value;
	}

	/**
	 * Render the shortcut key field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_shortcut_key_field(): void {
		$value = get_option( 'wp_search_shortcut_key', self::DEFAULT_SHORTCUT_KEY );
		?>
		<label>
			<input type="text" name="wp_search_shortcut_key" value="<?php echo esc_attr( $value ); ?>" maxlength="1" size="1" class="small-text" />
			<span class="description">
				<?php
				esc_html_e( 'Keyboard shortcut key (a–z). Default: t. Example: press Ctrl+', 'wp-search' );
				echo ' <kbd>' . esc_html( strtoupper( $value ) ) . '</kbd>';
				?>
			</span>
		</label>
		<?php
	}

	/**
	 * Add a search icon to the WordPress admin bar.
	 *
	 * @since 1.0.0
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_node( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$shortcut_key = get_option( 'wp_search_shortcut_key', self::DEFAULT_SHORTCUT_KEY );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'wp-search',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-search"></span><span class="ab-label">' . esc_html__( 'Search', 'wp-search' ) . '</span>',
				'href'   => '#',
				'meta'   => array(
					'title' => esc_attr( sprintf( __( 'Open search (Ctrl/Cmd+%s)', 'wp-search' ), strtoupper( $shortcut_key ) ) ),
					'class' => 'wp-search-admin-bar-node',
				),
			)
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$shortcut_key = get_option( 'wp_search_shortcut_key', self::DEFAULT_SHORTCUT_KEY );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="wp-search-tools-trigger" data-wp-search-trigger role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Open search', 'wp-search' ); ?>">
				<span class="wp-search-tools-trigger__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
				</span>
				<span class="wp-search-tools-trigger__text"><?php esc_html_e( 'Search settings, content, products…', 'wp-search' ); ?></span>
				<span class="wp-search-tools-trigger__shortcut"><kbd>Ctrl/Cmd</kbd><kbd><?php echo esc_html( strtoupper( $shortcut_key ) ); ?></kbd></span>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wp_search_settings' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( null, 'primary', 'submit', false );
				?>
			</form>

			<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php wp_nonce_field( 'wp_search_reindex', 'wp_search_reindex_nonce' ); ?>
				<p>
					<?php submit_button( __( 'Reindex Settings', 'wp-search' ), 'secondary', 'wp_search_reindex', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}
}
