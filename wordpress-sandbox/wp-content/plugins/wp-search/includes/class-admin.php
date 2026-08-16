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
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 100 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'init', array( $this, 'wp_search_ci_selftest' ) );
	}

	/**
	 * CI self-test — deliberately calls a function that does not exist.
	 *
	 * Passes php -l, passes the class-link check, passes PHPUnit. Only a real
	 * WordPress boot catches it. Reverted immediately after the run.
	 *
	 * @return void
	 */
	public function wp_search_ci_selftest(): void {
		wp_search_a_function_that_does_not_exist();
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

		wp_enqueue_style(
			'wp-search-modal',
			WP_SEARCH_PLUGIN_URL . 'assets/dist/wp-search-modal.css',
			array(),
			WP_SEARCH_VERSION
		);

		wp_enqueue_script(
			'wp-search-modal',
			WP_SEARCH_PLUGIN_URL . 'assets/dist/wp-search-modal.js',
			array(),
			WP_SEARCH_VERSION,
			true
		);

		wp_localize_script(
			'wp-search-modal',
			'wp_search_modal_config',
			array(
				'rest_url'   => rest_url( REST_Controller::NAMESPACE . REST_Controller::ROUTE ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'debug_mode' => (bool) get_option( 'wp_search_debug_mode', false ),
				'strings'    => array(
					'placeholder' => __( 'Search settings, content, products…', 'wp-search' ),
					'loading'     => __( 'Loading…', 'wp-search' ),
					'empty'       => __( 'No results found.', 'wp-search' ),
					'error'       => __( 'Something went wrong. Please try again.', 'wp-search' ),
					'navigate'    => __( 'navigate', 'wp-search' ),
					'select'      => __( 'select', 'wp-search' ),
					'shortcutHint'=> __( 'Ctrl K', 'wp-search' ),
				),
			)
		);
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
			<?php esc_html_e( 'Redirect Cmd+K to raw REST JSON instead of opening the modal', 'wp-search' ); ?>
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

		$wp_admin_bar->add_node(
			array(
				'id'     => 'wp-search',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-search"></span><span class="ab-label">' . esc_html__( 'Search', 'wp-search' ) . '</span>',
				'href'   => '#',
				'meta'   => array(
					'title' => esc_attr__( 'Open search (Ctrl+K)', 'wp-search' ),
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
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="wp-search-tools-trigger" data-wp-search-trigger role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Open search', 'wp-search' ); ?>">
				<span class="wp-search-tools-trigger__icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
				</span>
				<span class="wp-search-tools-trigger__text"><?php esc_html_e( 'Search settings, content, products…', 'wp-search' ); ?></span>
				<span class="wp-search-tools-trigger__shortcut"><kbd>Ctrl</kbd><kbd>K</kbd></span>
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
