<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Admin
 *
 * Defines the admin-specific functionality.
 */
class ICT_Admin {

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
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_styles( $hook_suffix ) {
		// Only load on ICT Platform pages
		if ( ! $this->is_ict_admin_page( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			ICT_PLATFORM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version,
			'all'
		);

		// React app styles
		wp_enqueue_style(
			$this->plugin_name . '-react',
			ICT_PLATFORM_PLUGIN_URL . 'assets/css/dist/admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		// Only load on ICT Platform pages
		if ( ! $this->is_ict_admin_page( $hook_suffix ) ) {
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

		// Load admin app bundle
		wp_enqueue_script(
			$this->plugin_name . '-admin',
			ICT_PLATFORM_PLUGIN_URL . 'assets/js/dist/admin.bundle.js',
			array( $this->plugin_name . '-vendors' ),
			$this->version,
			true
		);

		// Localize script with data
		wp_localize_script(
			$this->plugin_name . '-admin',
			'ictPlatform',
			array(
				'apiUrl'      => rest_url( 'ict/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => wp_get_current_user()->ID,
				'userCan'     => array(
					'manageProjects'       => current_user_can( 'manage_ict_projects' ),
					'manageTimeEntries'    => current_user_can( 'manage_ict_time_entries' ),
					'approveTime'          => current_user_can( 'approve_ict_time_entries' ),
					'manageInventory'      => current_user_can( 'manage_ict_inventory' ),
					'managePurchaseOrders' => current_user_can( 'manage_ict_purchase_orders' ),
					'viewReports'          => current_user_can( 'view_ict_reports' ),
					'manageSync'           => current_user_can( 'manage_ict_sync' ),
				),
				'settings'    => array(
					'dateFormat'   => get_option( 'ict_date_format', 'Y-m-d' ),
					'timeFormat'   => get_option( 'ict_time_format', 'H:i:s' ),
					'currency'     => get_option( 'ict_currency', 'USD' ),
					'timeRounding' => get_option( 'ict_time_rounding', 15 ),
					'offlineMode'  => get_option( 'ict_enable_offline_mode', true ),
					'gpsTracking'  => get_option( 'ict_enable_gps_tracking', true ),
				),
				'zohoStatus'  => $this->get_zoho_connection_status(),
				'i18n'        => array(
					'projects'     => __( 'Projects', 'ict-platform' ),
					'timeTracking' => __( 'Time Tracking', 'ict-platform' ),
					'inventory'    => __( 'Inventory', 'ict-platform' ),
					'reports'      => __( 'Reports', 'ict-platform' ),
					'settings'     => __( 'Settings', 'ict-platform' ),
					'sync'         => __( 'Sync', 'ict-platform' ),
				),
			)
		);
	}

	/**
	 * Check if current page is an ICT Platform admin page.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix The current admin page.
	 * @return bool
	 */
	private function is_ict_admin_page( $hook_suffix ) {
		$ict_pages = array(
			'toplevel_page_ict-platform',
			'ict-platform_page_ict-projects',
			'ict-platform_page_ict-time-tracking',
			'ict-platform_page_ict-resources',
			'ict-platform_page_ict-inventory',
			'ict-platform_page_ict-reports',
			'ict-platform_page_ict-sync',
			'ict-platform_page_ict-settings',
		);

		return in_array( $hook_suffix, $ict_pages, true ) || strpos( $hook_suffix, 'ict-' ) !== false;
	}

	/**
	 * Get Zoho services connection status.
	 *
	 * @since  1.0.0
	 * @return array Connection status for each service.
	 */
	private function get_zoho_connection_status() {
		$services = array( 'crm', 'fsm', 'books', 'people', 'desk' );
		$status   = array();

		foreach ( $services as $service ) {
			$token              = get_option( "ict_zoho_{$service}_access_token" );
			$status[ $service ] = ! empty( $token );
		}

		return $status;
	}
}
