<?php
/**
 * Divi Theme Compatibility Handler
 *
 * Ensures ICT Platform works seamlessly with Divi 5 and Divi Builder.
 *
 * @package ICT_Platform
 * @since   2.0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Divi Compatibility Class
 *
 * Handles compatibility with Divi theme and Divi Builder.
 */
class ICT_Divi_Compatibility {

	/**
	 * Initialize Divi compatibility.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Check if Divi is active.
		add_action( 'after_setup_theme', array( __CLASS__, 'check_divi_compatibility' ) );

		// Enqueue compatibility scripts/styles.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_divi_compatibility' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_divi_compatibility' ), 20 );

		// Add Divi Builder support for custom post types.
		add_filter( 'et_builder_post_types', array( __CLASS__, 'add_divi_builder_support' ) );

		// Prevent script conflicts with Divi Visual Builder.
		add_action( 'et_fb_enqueue_assets', array( __CLASS__, 'prevent_visual_builder_conflicts' ) );
	}

	/**
	 * Check if Divi theme is active and compatible.
	 *
	 * @return bool True if Divi is active and compatible.
	 */
	public static function is_divi_active(): bool {
		$theme = wp_get_theme();

		// Check if Divi or Divi child theme is active.
		return ( 'Divi' === $theme->name || 'Divi' === $theme->parent_theme );
	}

	/**
	 * Check Divi compatibility and show admin notice if issues found.
	 *
	 * @return void
	 */
	public static function check_divi_compatibility(): void {
		if ( ! self::is_divi_active() ) {
			return;
		}

		// Get Divi version.
		$theme            = wp_get_theme();
		$divi_version     = ( 'Divi' === $theme->name ) ? $theme->version : $theme->parent()->version;
		$min_divi_version = '5.0.0';

		// Check if Divi version is compatible.
		if ( version_compare( $divi_version, $min_divi_version, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_divi_version_notice' ) );
		}
	}

	/**
	 * Show admin notice for Divi version compatibility.
	 *
	 * @return void
	 */
	public static function show_divi_version_notice(): void {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'ICT Platform Notice:', 'ict-platform' ); ?></strong>
				<?php esc_html_e( 'For best compatibility with ICT Platform, we recommend using Divi 5.0 or higher.', 'ict-platform' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue Divi compatibility scripts and styles for frontend.
	 *
	 * @return void
	 */
	public static function enqueue_divi_compatibility(): void {
		if ( ! self::is_divi_active() ) {
			return;
		}

		// Add inline CSS to prevent conflicts with Divi styles.
		$custom_css = '
			.ict-platform-wrapper {
				/* Ensure ICT Platform components don\'t inherit Divi styles */
				all: initial;
				* {
					all: unset;
				}
			}
		';

		wp_add_inline_style( 'ict-public-style', $custom_css );
	}

	/**
	 * Enqueue Divi compatibility scripts for admin/Visual Builder.
	 *
	 * @return void
	 */
	public static function enqueue_admin_divi_compatibility(): void {
		if ( ! self::is_divi_active() ) {
			return;
		}

		// Prevent React version conflicts with Divi Builder.
		if ( isset( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_deregister_script( 'react' );
			wp_deregister_script( 'react-dom' );
		}
	}

	/**
	 * Add Divi Builder support to custom post types.
	 *
	 * @param array $post_types Existing post types with Divi Builder support.
	 * @return array Modified post types array.
	 */
	public static function add_divi_builder_support( array $post_types ): array {
		// Add ICT Platform custom post types to Divi Builder.
		$ict_post_types = array(
			'ict_project',
			'ict_resource',
			'ict_equipment',
		);

		return array_merge( $post_types, $ict_post_types );
	}

	/**
	 * Prevent script conflicts when Divi Visual Builder is active.
	 *
	 * @return void
	 */
	public static function prevent_visual_builder_conflicts(): void {
		// Dequeue potentially conflicting ICT Platform scripts in Visual Builder.
		wp_dequeue_script( 'ict-admin-bundle' );
		wp_dequeue_script( 'ict-public-bundle' );

		// Add notice for users in Visual Builder.
		add_action(
			'et_fb_framework_loaded',
			function () {
				?>
				<script type="text/javascript">
					console.log('ICT Platform: Some features disabled in Divi Visual Builder for compatibility');
				</script>
				<?php
			}
		);
	}

	/**
	 * Check if we're in Divi Visual Builder mode.
	 *
	 * @return bool True if in Visual Builder mode.
	 */
	public static function is_divi_builder_active(): bool {
		return function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled();
	}

	/**
	 * Get Divi module wrapper classes.
	 *
	 * @return string CSS classes for Divi module wrapper.
	 */
	public static function get_divi_module_classes(): string {
		if ( ! self::is_divi_active() ) {
			return '';
		}

		return 'et_pb_module et_pb_text et_pb_text_align_left';
	}
}

// Initialize Divi compatibility.
ICT_Divi_Compatibility::init();
