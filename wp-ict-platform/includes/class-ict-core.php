<?php
/**
 * The core plugin class
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Core
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class ICT_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since  1.0.0
	 * @var    ICT_Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'ict-platform';
		$this->version     = ICT_PLATFORM_VERSION;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_api_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function load_dependencies() {
		// Create loader instance
		$this->loader = new ICT_Loader();

		// Load utility classes
		require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-i18n.php';
		require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/class-ict-helper.php';

		// Load admin classes
		require_once ICT_PLATFORM_PLUGIN_DIR . 'admin/class-ict-admin.php';
		require_once ICT_PLATFORM_PLUGIN_DIR . 'admin/class-ict-admin-settings.php';
		require_once ICT_PLATFORM_PLUGIN_DIR . 'admin/class-ict-admin-menu.php';

		// Load public classes
		require_once ICT_PLATFORM_PLUGIN_DIR . 'public/class-ict-public.php';

		// Load API classes
		require_once ICT_PLATFORM_PLUGIN_DIR . 'api/class-ict-api.php';

		// Load post type and taxonomy classes
		require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/post-types/class-ict-posttype-project.php';
		require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/taxonomies/class-ict-taxonomy-project-status.php';

		// Load integration classes
		require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/integrations/class-ict-integration-manager.php';

		// Load sync engine
		require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/sync/class-ict-sync-engine.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function set_locale() {
		$plugin_i18n = new ICT_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = new ICT_Admin( $this->get_plugin_name(), $this->get_version() );

		// Enqueue admin assets
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Admin menu
		$admin_menu = new ICT_Admin_Menu();
		$this->loader->add_action( 'admin_menu', $admin_menu, 'add_menu_pages' );

		// Settings
		$admin_settings = new ICT_Admin_Settings();
		$this->loader->add_action( 'admin_init', $admin_settings, 'register_settings' );

		// Register post types
		$project_post_type = new ICT_PostType_Project();
		$this->loader->add_action( 'init', $project_post_type, 'register' );

		// Register taxonomies
		$project_status_taxonomy = new ICT_Taxonomy_Project_Status();
		$this->loader->add_action( 'init', $project_status_taxonomy, 'register' );

		// Custom cron schedules
		$this->loader->add_filter( 'cron_schedules', $this, 'add_custom_cron_schedules' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = new ICT_Public( $this->get_plugin_name(), $this->get_version() );

		// Enqueue public assets
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// PWA manifest and service worker
		$this->loader->add_action( 'wp_head', $plugin_public, 'add_pwa_meta_tags' );
	}

	/**
	 * Register all REST API endpoints.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function define_api_hooks() {
		$api = new ICT_API();
		$this->loader->add_action( 'rest_api_init', $api, 'register_routes' );
	}

	/**
	 * Register all cron job hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function define_cron_hooks() {
		$sync_engine = new ICT_Sync_Engine();

		// Sync job
		$this->loader->add_action( 'ict_platform_sync_job', $sync_engine, 'process_sync_queue' );

		// Cleanup job
		$this->loader->add_action( 'ict_platform_cleanup_job', $this, 'cleanup_old_logs' );

		// Stock check job
		$this->loader->add_action( 'ict_platform_stock_check', $this, 'check_low_stock' );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @since  1.0.0
	 * @param  array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['ict_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'ict-platform' ),
		);

		$schedules['ict_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes', 'ict-platform' ),
		);

		return $schedules;
	}

	/**
	 * Cleanup old sync logs (older than 30 days).
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$days_to_keep = apply_filters( 'ict_sync_log_retention_days', 30 );
		$date_cutoff  = date( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . ICT_SYNC_LOG_TABLE . " WHERE synced_at < %s",
				$date_cutoff
			)
		);
	}

	/**
	 * Check for low stock items and send notifications.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function check_low_stock() {
		global $wpdb;

		$threshold = get_option( 'ict_low_stock_threshold', 10 );

		$low_stock_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . "
				WHERE quantity_available <= %d AND is_active = 1",
				$threshold
			)
		);

		if ( ! empty( $low_stock_items ) ) {
			do_action( 'ict_low_stock_detected', $low_stock_items );
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it.
	 *
	 * @since  1.0.0
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since  1.0.0
	 * @return ICT_Loader Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since  1.0.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
