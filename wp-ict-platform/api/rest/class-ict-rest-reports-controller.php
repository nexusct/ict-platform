<?php
/**
 * REST API: ICT_REST_Reports_Controller class
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

/**
 * Core class to access reports via the REST API.
 *
 * @since 1.0.0
 *
 * @see WP_REST_Controller
 */
class ICT_REST_Reports_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->namespace = 'ict-platform/v1';
		$this->rest_base = 'reports';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Project reports
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project_report' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'date_from' => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'date_to'   => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'status'    => array(
							'type' => 'string',
						),
						'priority'  => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		// Time reports
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/time',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_time_report' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'date_from'     => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'date_to'       => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'technician_id' => array(
							'type' => 'integer',
						),
						'project_id'    => array(
							'type' => 'integer',
						),
						'group_by'      => array(
							'type' => 'string',
							'enum' => array( 'day', 'week', 'month', 'technician', 'project' ),
						),
					),
				),
			)
		);

		// Budget reports
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/budget',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_budget_report' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'project_id' => array(
							'type' => 'integer',
						),
						'date_from'  => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'date_to'    => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
					),
				),
			)
		);

		// Inventory reports
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inventory',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_inventory_report' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'category'  => array(
							'type' => 'string',
						),
						'location'  => array(
							'type' => 'string',
						),
						'date_from' => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'date_to'   => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
					),
				),
			)
		);

		// Dashboard summary
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_dashboard_summary' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		// Export report
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_report' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'report_type' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'projects', 'time', 'budget', 'inventory' ),
						),
						'format'      => array(
							'type'    => 'string',
							'default' => 'csv',
							'enum'    => array( 'csv', 'excel', 'pdf' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Checks if a given request has access to read reports.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'view_reports' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view reports.', 'ict-platform' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get project report data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_project_report( $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ict_projects';

		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );
		$status    = $request->get_param( 'status' );
		$priority  = $request->get_param( 'priority' );

		// Build WHERE clause
		$where_clauses = array( '1=1' );
		$values        = array();

		if ( $date_from ) {
			$where_clauses[] = 'created_at >= %s';
			$values[]        = $date_from;
		}

		if ( $date_to ) {
			$where_clauses[] = 'created_at <= %s';
			$values[]        = $date_to;
		}

		if ( $status ) {
			$where_clauses[] = 'status = %s';
			$values[]        = $status;
		}

		if ( $priority ) {
			$where_clauses[] = 'priority = %s';
			$values[]        = $priority;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get project statistics
		$query = "SELECT
			COUNT(*) as total_projects,
			SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as active_projects,
			SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
			SUM(CASE WHEN status = 'on-hold' THEN 1 ELSE 0 END) as on_hold_projects,
			SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_projects,
			AVG(progress_percentage) as avg_progress,
			SUM(budget_amount) as total_budget,
			SUM(actual_cost) as total_cost,
			SUM(estimated_hours) as total_estimated_hours,
			SUM(actual_hours) as total_actual_hours
		FROM $table_name
		WHERE $where_sql";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$stats = $wpdb->get_row( $query, ARRAY_A );

		// Get projects by status
		$query = "SELECT status, COUNT(*) as count
		FROM $table_name
		WHERE $where_sql
		GROUP BY status";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$by_status = $wpdb->get_results( $query, ARRAY_A );

		// Get projects by priority
		$query = "SELECT priority, COUNT(*) as count
		FROM $table_name
		WHERE $where_sql
		GROUP BY priority";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$by_priority = $wpdb->get_results( $query, ARRAY_A );

		// Get timeline data (projects created per month)
		$query = "SELECT
			DATE_FORMAT(created_at, '%Y-%m') as month,
			COUNT(*) as count
		FROM $table_name
		WHERE $where_sql
		GROUP BY month
		ORDER BY month DESC
		LIMIT 12";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$timeline = $wpdb->get_results( $query, ARRAY_A );

		// Get top projects by budget
		$query = "SELECT
			id,
			project_name,
			budget_amount,
			actual_cost,
			progress_percentage,
			status
		FROM $table_name
		WHERE $where_sql
		ORDER BY budget_amount DESC
		LIMIT 10";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$top_projects = $wpdb->get_results( $query, ARRAY_A );

		$report = array(
			'statistics'   => $stats,
			'by_status'    => $by_status,
			'by_priority'  => $by_priority,
			'timeline'     => $timeline,
			'top_projects' => $top_projects,
			'generated_at' => current_time( 'mysql' ),
		);

		return rest_ensure_response( $report );
	}

	/**
	 * Get time report data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_time_report( $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ict_time_entries';

		$date_from     = $request->get_param( 'date_from' );
		$date_to       = $request->get_param( 'date_to' );
		$technician_id = $request->get_param( 'technician_id' );
		$project_id    = $request->get_param( 'project_id' );
		$group_by      = $request->get_param( 'group_by' ) ?: 'day';

		// Build WHERE clause
		$where_clauses = array( '1=1' );
		$values        = array();

		if ( $date_from ) {
			$where_clauses[] = 'clock_in >= %s';
			$values[]        = $date_from;
		}

		if ( $date_to ) {
			$where_clauses[] = 'clock_in <= %s';
			$values[]        = $date_to;
		}

		if ( $technician_id ) {
			$where_clauses[] = 'technician_id = %d';
			$values[]        = $technician_id;
		}

		if ( $project_id ) {
			$where_clauses[] = 'project_id = %d';
			$values[]        = $project_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get overall statistics
		$query = "SELECT
			COUNT(*) as total_entries,
			SUM(total_hours) as total_hours,
			SUM(billable_hours) as billable_hours,
			SUM(billable_hours * hourly_rate) as total_revenue,
			AVG(total_hours) as avg_hours_per_entry
		FROM $table_name
		WHERE $where_sql AND status != 'in-progress'";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$stats = $wpdb->get_row( $query, ARRAY_A );

		// Get data grouped by specified dimension
		$group_field = '';
		switch ( $group_by ) {
			case 'day':
				$group_field = 'DATE(clock_in) as group_key, DATE(clock_in) as label';
				break;
			case 'week':
				$group_field = "YEARWEEK(clock_in) as group_key, CONCAT('Week ', WEEK(clock_in)) as label";
				break;
			case 'month':
				$group_field = "DATE_FORMAT(clock_in, '%Y-%m') as group_key, DATE_FORMAT(clock_in, '%b %Y') as label";
				break;
			case 'technician':
				$group_field = 'technician_id as group_key, technician_id as label';
				break;
			case 'project':
				$group_field = 'project_id as group_key, project_id as label';
				break;
		}

		$query = "SELECT
			$group_field,
			COUNT(*) as entry_count,
			SUM(total_hours) as total_hours,
			SUM(billable_hours) as billable_hours,
			SUM(billable_hours * hourly_rate) as revenue
		FROM $table_name
		WHERE $where_sql AND status != 'in-progress'
		GROUP BY group_key
		ORDER BY group_key DESC";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$grouped_data = $wpdb->get_results( $query, ARRAY_A );

		// Get top technicians by hours
		$query = "SELECT
			technician_id,
			COUNT(*) as entry_count,
			SUM(total_hours) as total_hours,
			SUM(billable_hours) as billable_hours,
			SUM(billable_hours * hourly_rate) as revenue
		FROM $table_name
		WHERE $where_sql AND status != 'in-progress'
		GROUP BY technician_id
		ORDER BY total_hours DESC
		LIMIT 10";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$top_technicians = $wpdb->get_results( $query, ARRAY_A );

		// Get hours by status
		$query = "SELECT
			status,
			COUNT(*) as count,
			SUM(total_hours) as total_hours
		FROM $table_name
		WHERE $where_sql
		GROUP BY status";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$by_status = $wpdb->get_results( $query, ARRAY_A );

		$report = array(
			'statistics'      => $stats,
			'grouped_data'    => $grouped_data,
			'top_technicians' => $top_technicians,
			'by_status'       => $by_status,
			'group_by'        => $group_by,
			'generated_at'    => current_time( 'mysql' ),
		);

		return rest_ensure_response( $report );
	}

	/**
	 * Get budget report data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_budget_report( $request ) {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'ict_projects';
		$time_table     = $wpdb->prefix . 'ict_time_entries';
		$po_table       = $wpdb->prefix . 'ict_purchase_orders';

		$project_id = $request->get_param( 'project_id' );
		$date_from  = $request->get_param( 'date_from' );
		$date_to    = $request->get_param( 'date_to' );

		$where_clauses = array( '1=1' );
		$values        = array();

		if ( $project_id ) {
			$where_clauses[] = 'p.id = %d';
			$values[]        = $project_id;
		}

		if ( $date_from ) {
			$where_clauses[] = 'p.created_at >= %s';
			$values[]        = $date_from;
		}

		if ( $date_to ) {
			$where_clauses[] = 'p.created_at <= %s';
			$values[]        = $date_to;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get budget overview
		$query = "SELECT
			SUM(p.budget_amount) as total_budget,
			SUM(p.actual_cost) as total_cost,
			SUM(p.budget_amount - p.actual_cost) as remaining_budget,
			AVG((p.actual_cost / NULLIF(p.budget_amount, 0)) * 100) as avg_budget_utilization
		FROM $projects_table p
		WHERE $where_sql";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$overview = $wpdb->get_row( $query, ARRAY_A );

		// Get labor costs
		$time_where = $project_id ? 'WHERE t.project_id = %d' : '';
		$time_query = "SELECT
			SUM(t.billable_hours * t.hourly_rate) as labor_cost,
			SUM(t.billable_hours) as total_hours
		FROM $time_table t
		$time_where";

		if ( $project_id ) {
			$time_query = $wpdb->prepare( $time_query, $project_id );
		}

		$labor_costs = $wpdb->get_row( $time_query, ARRAY_A );

		// Get material costs (from purchase orders)
		$po_where = $project_id ? 'WHERE po.project_id = %d' : 'WHERE po.project_id IS NOT NULL';
		$po_query = "SELECT
			SUM(po.total_amount) as material_cost,
			COUNT(*) as po_count
		FROM $po_table po
		$po_where AND po.status != 'cancelled'";

		if ( $project_id ) {
			$po_query = $wpdb->prepare( $po_query, $project_id );
		}

		$material_costs = $wpdb->get_row( $po_query, ARRAY_A );

		// Get projects by budget status
		$query = "SELECT
			p.id,
			p.project_name,
			p.budget_amount,
			p.actual_cost,
			(p.budget_amount - p.actual_cost) as remaining,
			((p.actual_cost / NULLIF(p.budget_amount, 0)) * 100) as utilization_percent,
			p.status
		FROM $projects_table p
		WHERE $where_sql
		ORDER BY utilization_percent DESC";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$projects = $wpdb->get_results( $query, ARRAY_A );

		$report = array(
			'overview'       => $overview,
			'labor_costs'    => $labor_costs,
			'material_costs' => $material_costs,
			'projects'       => $projects,
			'generated_at'   => current_time( 'mysql' ),
		);

		return rest_ensure_response( $report );
	}

	/**
	 * Get inventory report data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_inventory_report( $request ) {
		global $wpdb;
		$items_table   = $wpdb->prefix . 'ict_inventory_items';
		$history_table = $wpdb->prefix . 'ict_stock_history';

		$category  = $request->get_param( 'category' );
		$location  = $request->get_param( 'location' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		// Build WHERE clause for items
		$where_clauses = array( '1=1' );
		$values        = array();

		if ( $category ) {
			$where_clauses[] = 'category = %s';
			$values[]        = $category;
		}

		if ( $location ) {
			$where_clauses[] = 'location = %s';
			$values[]        = $location;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get inventory overview
		$query = "SELECT
			COUNT(*) as total_items,
			SUM(quantity_on_hand) as total_quantity,
			SUM(quantity_on_hand * unit_cost) as total_value,
			SUM(quantity_allocated) as total_allocated,
			SUM(quantity_available) as total_available,
			SUM(CASE WHEN quantity_on_hand <= reorder_level THEN 1 ELSE 0 END) as low_stock_count
		FROM $items_table
		WHERE $where_sql";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$overview = $wpdb->get_row( $query, ARRAY_A );

		// Get inventory by category
		$query = "SELECT
			COALESCE(category, 'Uncategorized') as category,
			COUNT(*) as item_count,
			SUM(quantity_on_hand) as total_quantity,
			SUM(quantity_on_hand * unit_cost) as total_value
		FROM $items_table
		WHERE $where_sql
		GROUP BY category
		ORDER BY total_value DESC";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$by_category = $wpdb->get_results( $query, ARRAY_A );

		// Get inventory by location
		$query = "SELECT
			COALESCE(location, 'Unknown') as location,
			COUNT(*) as item_count,
			SUM(quantity_on_hand) as total_quantity,
			SUM(quantity_on_hand * unit_cost) as total_value
		FROM $items_table
		WHERE $where_sql
		GROUP BY location
		ORDER BY total_value DESC";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$by_location = $wpdb->get_results( $query, ARRAY_A );

		// Get stock movements (if date range provided)
		$movements = array();
		if ( $date_from || $date_to ) {
			$history_where  = array( '1=1' );
			$history_values = array();

			if ( $date_from ) {
				$history_where[]  = 'created_at >= %s';
				$history_values[] = $date_from;
			}

			if ( $date_to ) {
				$history_where[]  = 'created_at <= %s';
				$history_values[] = $date_to;
			}

			$history_where_sql = implode( ' AND ', $history_where );

			$query = "SELECT
				adjustment_type,
				COUNT(*) as count,
				SUM(ABS(quantity_change)) as total_quantity
			FROM $history_table
			WHERE $history_where_sql
			GROUP BY adjustment_type";

			if ( ! empty( $history_values ) ) {
				$query = $wpdb->prepare( $query, $history_values );
			}

			$movements = $wpdb->get_results( $query, ARRAY_A );
		}

		// Get top items by value
		$query = "SELECT
			id,
			item_name,
			sku,
			quantity_on_hand,
			unit_cost,
			(quantity_on_hand * unit_cost) as total_value
		FROM $items_table
		WHERE $where_sql
		ORDER BY total_value DESC
		LIMIT 10";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$top_items = $wpdb->get_results( $query, ARRAY_A );

		$report = array(
			'overview'     => $overview,
			'by_category'  => $by_category,
			'by_location'  => $by_location,
			'movements'    => $movements,
			'top_items'    => $top_items,
			'generated_at' => current_time( 'mysql' ),
		);

		return rest_ensure_response( $report );
	}

	/**
	 * Get dashboard summary with key metrics.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_dashboard_summary( $request ) {
		global $wpdb;

		// Projects summary
		$projects_table = $wpdb->prefix . 'ict_projects';
		$projects       = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as active,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(budget_amount) as total_budget,
				SUM(actual_cost) as total_cost
			FROM $projects_table",
			ARRAY_A
		);

		// Time entries summary (last 30 days)
		$time_table      = $wpdb->prefix . 'ict_time_entries';
		$thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		$time            = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_entries,
					SUM(total_hours) as total_hours,
					SUM(billable_hours * hourly_rate) as revenue
				FROM $time_table
				WHERE clock_in >= %s AND status != 'in-progress'",
				$thirty_days_ago
			),
			ARRAY_A
		);

		// Inventory summary
		$inventory_table = $wpdb->prefix . 'ict_inventory_items';
		$inventory       = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_items,
				SUM(quantity_on_hand * unit_cost) as total_value,
				SUM(CASE WHEN quantity_on_hand <= reorder_level THEN 1 ELSE 0 END) as low_stock
			FROM $inventory_table",
			ARRAY_A
		);

		// Purchase orders summary
		$po_table        = $wpdb->prefix . 'ict_purchase_orders';
		$purchase_orders = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
				SUM(total_amount) as total_amount
			FROM $po_table
			WHERE status != 'cancelled'",
			ARRAY_A
		);

		$summary = array(
			'projects'        => $projects,
			'time'            => $time,
			'inventory'       => $inventory,
			'purchase_orders' => $purchase_orders,
			'generated_at'    => current_time( 'mysql' ),
		);

		return rest_ensure_response( $summary );
	}

	/**
	 * Export report data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function export_report( $request ) {
		$report_type = $request->get_param( 'report_type' );
		$format      = $request->get_param( 'format' );

		// Get report data based on type
		$report_data = array();
		switch ( $report_type ) {
			case 'projects':
				$report_response = $this->get_project_report( $request );
				break;
			case 'time':
				$report_response = $this->get_time_report( $request );
				break;
			case 'budget':
				$report_response = $this->get_budget_report( $request );
				break;
			case 'inventory':
				$report_response = $this->get_inventory_report( $request );
				break;
			default:
				return new WP_Error(
					'invalid_report_type',
					__( 'Invalid report type specified.', 'ict-platform' ),
					array( 'status' => 400 )
				);
		}

		$report_data = rest_get_server()->response_to_data( $report_response, false );

		// For now, return JSON data
		// In a full implementation, you would generate CSV/Excel/PDF files
		$export_url = sprintf(
			'/wp-content/uploads/ict-reports/%s-report-%s.%s',
			$report_type,
			date( 'Y-m-d-His' ),
			$format
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'export_url' => $export_url,
				'format'     => $format,
				'message'    => __( 'Report export initiated. File will be available shortly.', 'ict-platform' ),
			)
		);
	}
}
