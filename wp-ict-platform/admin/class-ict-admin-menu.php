<?php
/**
 * Admin menu functionality
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Admin_Menu
 *
 * Handles the admin menu structure.
 */
class ICT_Admin_Menu {

	/**
	 * Add menu pages to WordPress admin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_pages() {
		// Main menu page
		add_menu_page(
			__( 'ICT Platform', 'ict-platform' ),
			__( 'ICT Platform', 'ict-platform' ),
			'manage_ict_platform',
			'ict-platform',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-tools',
			30
		);

		// Dashboard submenu (same as main, but explicit)
		add_submenu_page(
			'ict-platform',
			__( 'Dashboard', 'ict-platform' ),
			__( 'Dashboard', 'ict-platform' ),
			'manage_ict_platform',
			'ict-platform',
			array( $this, 'render_dashboard' )
		);

		// Projects submenu
		add_submenu_page(
			'ict-platform',
			__( 'Projects', 'ict-platform' ),
			__( 'Projects', 'ict-platform' ),
			'manage_ict_projects',
			'ict-projects',
			array( $this, 'render_projects' )
		);

		// Time Tracking submenu
		add_submenu_page(
			'ict-platform',
			__( 'Time Tracking', 'ict-platform' ),
			__( 'Time Tracking', 'ict-platform' ),
			'manage_ict_time_entries',
			'ict-time-tracking',
			array( $this, 'render_time_tracking' )
		);

		// Resources submenu
		add_submenu_page(
			'ict-platform',
			__( 'Resources', 'ict-platform' ),
			__( 'Resources', 'ict-platform' ),
			'manage_ict_projects',
			'ict-resources',
			array( $this, 'render_resources' )
		);

		// Inventory submenu
		add_submenu_page(
			'ict-platform',
			__( 'Inventory', 'ict-platform' ),
			__( 'Inventory', 'ict-platform' ),
			'manage_ict_inventory',
			'ict-inventory',
			array( $this, 'render_inventory' )
		);

		// Reports submenu
		add_submenu_page(
			'ict-platform',
			__( 'Reports', 'ict-platform' ),
			__( 'Reports', 'ict-platform' ),
			'view_ict_reports',
			'ict-reports',
			array( $this, 'render_reports' )
		);

		// Sync submenu
		add_submenu_page(
			'ict-platform',
			__( 'Zoho Sync', 'ict-platform' ),
			__( 'Sync', 'ict-platform' ),
			'manage_ict_sync',
			'ict-sync',
			array( $this, 'render_sync' )
		);

		// Settings submenu
		add_submenu_page(
			'ict-platform',
			__( 'Settings', 'ict-platform' ),
			__( 'Settings', 'ict-platform' ),
			'manage_options',
			'ict-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-dashboard-root"></div>
		</div>
		<?php
	}

	/**
	 * Render projects page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_projects() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-projects-root"></div>
		</div>
		<?php
	}

	/**
	 * Render time tracking page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_time_tracking() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-time-tracking-root"></div>
		</div>
		<?php
	}

	/**
	 * Render resources page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_resources() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-resources-root"></div>
		</div>
		<?php
	}

	/**
	 * Render inventory page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_inventory() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-inventory-root"></div>
		</div>
		<?php
	}

	/**
	 * Render reports page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_reports() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-reports-root"></div>
		</div>
		<?php
	}

	/**
	 * Render sync page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_sync() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-sync-root"></div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="ict-settings-root"></div>
		</div>
		<?php
	}
}
