<?php
/**
 * REST API functionality
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_API
 *
 * Registers REST API endpoints for the plugin.
 */
class ICT_API {

	/**
	 * API namespace.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	private $namespace = 'ict/v1';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		// Initialize controllers
		$this->initialize_controllers();

		// Register basic routes (legacy - to be migrated to controllers)
		$this->register_project_routes();
		$this->register_time_entry_routes();
		$this->register_inventory_routes();
		$this->register_purchase_order_routes();
		$this->register_resource_routes();
		$this->register_sync_routes();
		$this->register_report_routes();
	}

	/**
	 * Initialize REST controllers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function initialize_controllers() {
		// Authentication controller
		$auth_controller = new ICT_REST_Auth_Controller();
		$auth_controller->register_routes();

		// Location controller
		$location_controller = new ICT_REST_Location_Controller();
		$location_controller->register_routes();

		// Expenses controller
		$expenses_controller = new ICT_REST_Expenses_Controller();
		$expenses_controller->register_routes();

		// Schedule controller
		$schedule_controller = new ICT_REST_Schedule_Controller();
		$schedule_controller->register_routes();

		// Files & Tasks controller
		$files_tasks_controller = new ICT_REST_Files_Tasks_Controller();
		$files_tasks_controller->register_routes();

		// Projects controller
		if ( class_exists( 'ICT_REST_Projects_Controller' ) ) {
			$projects_controller = new ICT_REST_Projects_Controller();
			$projects_controller->register_routes();
		}

		// Time entries controller
		if ( class_exists( 'ICT_REST_Time_Entries_Controller' ) ) {
			$time_controller = new ICT_REST_Time_Entries_Controller();
			$time_controller->register_routes();
		}

		// Inventory controller
		if ( class_exists( 'ICT_REST_Inventory_Controller' ) ) {
			$inventory_controller = new ICT_REST_Inventory_Controller();
			$inventory_controller->register_routes();
		}

		// Purchase orders controller
		if ( class_exists( 'ICT_REST_Purchase_Orders_Controller' ) ) {
			$po_controller = new ICT_REST_Purchase_Orders_Controller();
			$po_controller->register_routes();
		}

		// Resources controller
		if ( class_exists( 'ICT_REST_Resources_Controller' ) ) {
			$resources_controller = new ICT_REST_Resources_Controller();
			$resources_controller->register_routes();
		}

		// Reports controller
		if ( class_exists( 'ICT_REST_Reports_Controller' ) ) {
			$reports_controller = new ICT_REST_Reports_Controller();
			$reports_controller->register_routes();
		}

		// Health controller
		if ( class_exists( 'ICT_REST_Health_Controller' ) ) {
			$health_controller = new ICT_REST_Health_Controller();
			$health_controller->register_routes();
		}
	}

	/**
	 * Register project routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_project_routes() {
		// GET /projects
		register_rest_route(
			$this->namespace,
			'/projects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_projects' ),
				'permission_callback' => array( $this, 'check_projects_permission' ),
			)
		);

		// GET /projects/{id}
		register_rest_route(
			$this->namespace,
			'/projects/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project' ),
				'permission_callback' => array( $this, 'check_projects_permission' ),
			)
		);

		// POST /projects
		register_rest_route(
			$this->namespace,
			'/projects',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_project' ),
				'permission_callback' => array( $this, 'check_manage_projects_permission' ),
			)
		);

		// PUT /projects/{id}
		register_rest_route(
			$this->namespace,
			'/projects/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_project' ),
				'permission_callback' => array( $this, 'check_manage_projects_permission' ),
			)
		);

		// DELETE /projects/{id}
		register_rest_route(
			$this->namespace,
			'/projects/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_project' ),
				'permission_callback' => array( $this, 'check_manage_projects_permission' ),
			)
		);
	}

	/**
	 * Register time entry routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_time_entry_routes() {
		// GET /time-entries
		register_rest_route(
			$this->namespace,
			'/time-entries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_time_entries' ),
				'permission_callback' => array( $this, 'check_time_permission' ),
			)
		);

		// POST /time/clock-in
		register_rest_route(
			$this->namespace,
			'/time/clock-in',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clock_in' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// POST /time/clock-out
		register_rest_route(
			$this->namespace,
			'/time/clock-out',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clock_out' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// POST /time-entries/{id}/approve
		register_rest_route(
			$this->namespace,
			'/time-entries/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_time_entry' ),
				'permission_callback' => array( $this, 'check_approve_time_permission' ),
			)
		);
	}

	/**
	 * Register inventory routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_inventory_routes() {
		register_rest_route(
			$this->namespace,
			'/inventory',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_inventory_items' ),
				'permission_callback' => array( $this, 'check_inventory_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/inventory/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_inventory_item' ),
				'permission_callback' => array( $this, 'check_manage_inventory_permission' ),
			)
		);
	}

	/**
	 * Register purchase order routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_purchase_order_routes() {
		register_rest_route(
			$this->namespace,
			'/purchase-orders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_purchase_orders' ),
				'permission_callback' => array( $this, 'check_po_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/purchase-orders',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_purchase_order' ),
				'permission_callback' => array( $this, 'check_manage_po_permission' ),
			)
		);
	}

	/**
	 * Register resource routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_resource_routes() {
		register_rest_route(
			$this->namespace,
			'/resources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_resources' ),
				'permission_callback' => array( $this, 'check_projects_permission' ),
			)
		);
	}

	/**
	 * Register sync routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_sync_routes() {
		register_rest_route(
			$this->namespace,
			'/sync/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sync_status' ),
				'permission_callback' => array( $this, 'check_sync_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sync/trigger',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'trigger_sync' ),
				'permission_callback' => array( $this, 'check_sync_permission' ),
			)
		);
	}

	/**
	 * Register report routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_report_routes() {
		register_rest_route(
			$this->namespace,
			'/reports/(?P<type>[\w-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'check_reports_permission' ),
			)
		);
	}

	/**
	 * Permission callbacks.
	 */

	public function check_projects_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'view_ict_projects' );
	}

	public function check_manage_projects_permission() {
		return current_user_can( 'manage_ict_projects' );
	}

	public function check_time_permission() {
		return is_user_logged_in();
	}

	public function check_approve_time_permission() {
		return current_user_can( 'approve_ict_time_entries' );
	}

	public function check_inventory_permission() {
		return current_user_can( 'manage_ict_inventory' );
	}

	public function check_manage_inventory_permission() {
		return current_user_can( 'manage_ict_inventory' );
	}

	public function check_po_permission() {
		return current_user_can( 'manage_ict_purchase_orders' );
	}

	public function check_manage_po_permission() {
		return current_user_can( 'manage_ict_purchase_orders' );
	}

	public function check_sync_permission() {
		return current_user_can( 'manage_ict_sync' );
	}

	public function check_reports_permission() {
		return current_user_can( 'view_ict_reports' );
	}

	/**
	 * Placeholder endpoint handlers (to be implemented in dedicated controller classes).
	 */

	public function get_projects( $request ) {
		return new WP_REST_Response( array( 'message' => 'Projects endpoint' ), 200 );
	}

	public function get_project( $request ) {
		return new WP_REST_Response( array( 'message' => 'Get project endpoint' ), 200 );
	}

	public function create_project( $request ) {
		return new WP_REST_Response( array( 'message' => 'Create project endpoint' ), 201 );
	}

	public function update_project( $request ) {
		return new WP_REST_Response( array( 'message' => 'Update project endpoint' ), 200 );
	}

	public function delete_project( $request ) {
		return new WP_REST_Response( array( 'message' => 'Delete project endpoint' ), 200 );
	}

	public function get_time_entries( $request ) {
		return new WP_REST_Response( array( 'message' => 'Time entries endpoint' ), 200 );
	}

	public function clock_in( $request ) {
		return new WP_REST_Response( array( 'message' => 'Clock in endpoint' ), 201 );
	}

	public function clock_out( $request ) {
		return new WP_REST_Response( array( 'message' => 'Clock out endpoint' ), 200 );
	}

	public function approve_time_entry( $request ) {
		return new WP_REST_Response( array( 'message' => 'Approve time entry endpoint' ), 200 );
	}

	public function get_inventory_items( $request ) {
		return new WP_REST_Response( array( 'message' => 'Inventory items endpoint' ), 200 );
	}

	public function update_inventory_item( $request ) {
		return new WP_REST_Response( array( 'message' => 'Update inventory item endpoint' ), 200 );
	}

	public function get_purchase_orders( $request ) {
		return new WP_REST_Response( array( 'message' => 'Purchase orders endpoint' ), 200 );
	}

	public function create_purchase_order( $request ) {
		return new WP_REST_Response( array( 'message' => 'Create purchase order endpoint' ), 201 );
	}

	public function get_resources( $request ) {
		return new WP_REST_Response( array( 'message' => 'Resources endpoint' ), 200 );
	}

	public function get_sync_status( $request ) {
		return new WP_REST_Response( array( 'message' => 'Sync status endpoint' ), 200 );
	}

	public function trigger_sync( $request ) {
		return new WP_REST_Response( array( 'message' => 'Trigger sync endpoint' ), 200 );
	}

	public function get_report( $request ) {
		return new WP_REST_Response( array( 'message' => 'Report endpoint' ), 200 );
	}
}
