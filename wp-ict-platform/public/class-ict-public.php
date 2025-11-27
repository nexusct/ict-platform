<?php
/**
 * The public-facing functionality of the plugin
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Public
 *
 * Defines the public-facing functionality.
 */
class ICT_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		// Only load on pages that use ICT Platform shortcodes or templates
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			ICT_PLATFORM_PLUGIN_URL . 'assets/css/public.css',
			array(),
			$this->version,
			'all'
		);

		wp_enqueue_style(
			$this->plugin_name . '-react',
			ICT_PLATFORM_PLUGIN_URL . 'assets/css/dist/public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only load on pages that use ICT Platform shortcodes or templates
		if ( ! $this->should_load_assets() ) {
			return;
		}

		// Load vendors bundle
		wp_enqueue_script(
			$this->plugin_name . '-vendors',
			ICT_PLATFORM_PLUGIN_URL . 'assets/js/dist/vendors.bundle.js',
			array(),
			$this->version,
			true
		);

		// Load public app bundle
		wp_enqueue_script(
			$this->plugin_name . '-public',
			ICT_PLATFORM_PLUGIN_URL . 'assets/js/dist/public.bundle.js',
			array( $this->plugin_name . '-vendors' ),
			$this->version,
			true
		);

		// Localize script
		wp_localize_script(
			$this->plugin_name . '-public',
			'ictPlatformPublic',
			array(
				'apiUrl'      => rest_url( 'ict/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => get_current_user_id(),
				'isLoggedIn'  => is_user_logged_in(),
				'settings'    => array(
					'dateFormat' => get_option( 'ict_date_format', 'Y-m-d' ),
					'timeFormat' => get_option( 'ict_time_format', 'H:i:s' ),
					'currency'   => get_option( 'ict_currency', 'USD' ),
				),
			)
		);
	}

	/**
	 * Add PWA meta tags to wp_head.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_pwa_meta_tags() {
		?>
		<meta name="theme-color" content="#2271b1">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<meta name="apple-mobile-web-app-title" content="ICT Platform">
		<link rel="manifest" href="<?php echo esc_url( ICT_PLATFORM_PLUGIN_URL . 'manifest.json' ); ?>">
		<?php
	}

	/**
	 * Check if assets should be loaded on current page.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	private function should_load_assets() {
		global $post;

		// Divi Visual Builder detection - always load if builder is processing
		if ( $this->is_divi_builder_active() ) {
			// Check if our shortcodes might be present in builder content
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only check
			if ( isset( $_POST['et_fb_processing_shortcodes'] ) ) {
				return true;
			}
		}

		// Check for shortcodes
		if ( $post && has_shortcode( $post->post_content, 'ict_client_portal' ) ) {
			return true;
		}

		if ( $post && has_shortcode( $post->post_content, 'ict_time_clock' ) ) {
			return true;
		}

		// Divi: Check for shortcodes in Divi library items and global modules
		if ( $this->is_divi_builder_active() && $post ) {
			// Check raw post content for shortcodes (Divi may store them differently)
			$raw_content = get_post_field( 'post_content', $post->ID );
			if ( strpos( $raw_content, '[ict_' ) !== false ) {
				return true;
			}
		}

		// Check for custom post types
		if ( is_singular( 'ict_project' ) ) {
			return true;
		}

		// Check for specific page templates
		$template = get_page_template_slug();
		if ( strpos( $template, 'ict-' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Divi theme or builder is active.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_divi_builder_active() {
		// Check for Divi theme
		if ( defined( 'ET_CORE_VERSION' ) ) {
			return true;
		}

		// Check for Divi Builder plugin
		if ( defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
			return true;
		}

		// Check for Extra theme (Elegant Themes)
		if ( defined( 'ET_EXTRA_VERSION' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if we're in the Visual Builder editor.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_divi_visual_builder() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check
		if ( isset( $_GET['et_fb'] ) && '1' === $_GET['et_fb'] ) {
			return true;
		}

		// Check for builder AJAX request
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only check
		if ( isset( $_POST['action'] ) && strpos( $_POST['action'], 'et_fb_' ) === 0 ) {
			return true;
		}

		return false;
	}
}
