<?php
/**
 * Advanced Reporting System
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Advanced_Reporting
 *
 * Handles advanced reports with multiple export formats.
 */
class ICT_Advanced_Reporting {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Advanced_Reporting
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Advanced_Reporting
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Supported export formats.
	 *
	 * @var array
	 */
	private $export_formats = array( 'csv', 'xlsx', 'pdf', 'json' );

	/**
	 * Report types.
	 *
	 * @var array
	 */
	private $report_types = array(
		'project_summary',
		'time_entries',
		'resource_utilization',
		'inventory_status',
		'purchase_orders',
		'financial_summary',
		'productivity',
		'technician_performance',
		'project_profitability',
		'overtime_analysis',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Register REST API routes (alias for register_endpoints).
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes() {
		$this->register_endpoints();
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_endpoints() {
		register_rest_route( 'ict/v1', '/reports/(?P<type>[a-z_]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_report' ),
			'permission_callback' => array( $this, 'check_report_permission' ),
			'args'                => array(
				'type'       => array(
					'required'          => true,
					'validate_callback' => array( $this, 'validate_report_type' ),
				),
				'start_date' => array( 'type' => 'string' ),
				'end_date'   => array( 'type' => 'string' ),
				'format'     => array( 'type' => 'string', 'default' => 'json' ),
				'filters'    => array( 'type' => 'object' ),
			),
		) );

		register_rest_route( 'ict/v1', '/reports/export/(?P<type>[a-z_]+)', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'export_report' ),
			'permission_callback' => array( $this, 'check_report_permission' ),
		) );

		register_rest_route( 'ict/v1', '/reports/schedule', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'schedule_report' ),
			'permission_callback' => array( $this, 'check_report_permission' ),
		) );

		register_rest_route( 'ict/v1', '/reports/templates', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_report_templates' ),
			'permission_callback' => array( $this, 'check_report_permission' ),
		) );
	}

	/**
	 * Check report permission.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function check_report_permission( $request ) {
		return current_user_can( 'view_ict_reports' );
	}

	/**
	 * Validate report type.
	 *
	 * @since  1.1.0
	 * @param  string $type Report type.
	 * @return bool True if valid.
	 */
	public function validate_report_type( $type ) {
		return in_array( $type, $this->report_types, true );
	}

	/**
	 * Get report data.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_report( $request ) {
		$type       = $request->get_param( 'type' );
		$start_date = $request->get_param( 'start_date' ) ?? date( 'Y-m-01' );
		$end_date   = $request->get_param( 'end_date' ) ?? date( 'Y-m-t' );
		$filters    = $request->get_param( 'filters' ) ?? array();
		$format     = $request->get_param( 'format' );

		$data = $this->generate_report_data( $type, $start_date, $end_date, $filters );

		if ( $format !== 'json' && in_array( $format, $this->export_formats, true ) ) {
			return $this->export_report_data( $data, $type, $format );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'report'  => array(
				'type'       => $type,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'generated'  => current_time( 'mysql' ),
				'data'       => $data,
			),
		), 200 );
	}

	/**
	 * Generate report data.
	 *
	 * @since  1.1.0
	 * @param  string $type       Report type.
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Additional filters.
	 * @return array Report data.
	 */
	private function generate_report_data( $type, $start_date, $end_date, $filters ) {
		switch ( $type ) {
			case 'project_summary':
				return $this->get_project_summary_report( $start_date, $end_date, $filters );

			case 'time_entries':
				return $this->get_time_entries_report( $start_date, $end_date, $filters );

			case 'resource_utilization':
				return $this->get_resource_utilization_report( $start_date, $end_date, $filters );

			case 'inventory_status':
				return $this->get_inventory_status_report( $filters );

			case 'purchase_orders':
				return $this->get_purchase_orders_report( $start_date, $end_date, $filters );

			case 'financial_summary':
				return $this->get_financial_summary_report( $start_date, $end_date, $filters );

			case 'productivity':
				return $this->get_productivity_report( $start_date, $end_date, $filters );

			case 'technician_performance':
				return $this->get_technician_performance_report( $start_date, $end_date, $filters );

			case 'project_profitability':
				return $this->get_project_profitability_report( $start_date, $end_date, $filters );

			case 'overtime_analysis':
				return $this->get_overtime_analysis_report( $start_date, $end_date, $filters );

			default:
				return array();
		}
	}

	/**
	 * Get project summary report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_project_summary_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		$where_clauses = array( "1=1" );
		$where_values  = array();

		if ( $start_date ) {
			$where_clauses[] = "created_at >= %s";
			$where_values[]  = $start_date;
		}

		if ( $end_date ) {
			$where_clauses[] = "created_at <= %s";
			$where_values[]  = $end_date . ' 23:59:59';
		}

		if ( ! empty( $filters['status'] ) ) {
			$where_clauses[] = "status = %s";
			$where_values[]  = $filters['status'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Summary statistics
		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_projects,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
					SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
					AVG(progress_percentage) as avg_progress,
					SUM(budget_amount) as total_budget,
					SUM(actual_cost) as total_cost,
					SUM(estimated_hours) as total_estimated_hours,
					SUM(actual_hours) as total_actual_hours
				FROM " . ICT_PROJECTS_TABLE . "
				WHERE {$where_sql}",
				...$where_values
			),
			ARRAY_A
		);

		// Projects list
		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id, project_name, project_number, status, priority,
					start_date, end_date, progress_percentage,
					budget_amount, actual_cost, estimated_hours, actual_hours,
					created_at
				FROM " . ICT_PROJECTS_TABLE . "
				WHERE {$where_sql}
				ORDER BY created_at DESC",
				...$where_values
			),
			ARRAY_A
		);

		// Status breakdown for chart
		$status_breakdown = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM " . ICT_PROJECTS_TABLE . "
				WHERE {$where_sql}
				GROUP BY status",
				...$where_values
			),
			ARRAY_A
		);

		return array(
			'summary'          => $summary,
			'projects'         => $projects,
			'status_breakdown' => $status_breakdown,
			'budget_variance'  => $summary['total_budget'] - $summary['total_cost'],
			'hours_variance'   => $summary['total_estimated_hours'] - $summary['total_actual_hours'],
		);
	}

	/**
	 * Get time entries report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_time_entries_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		$where_clauses = array( "t.clock_in >= %s", "t.clock_in <= %s" );
		$where_values  = array( $start_date, $end_date . ' 23:59:59' );

		if ( ! empty( $filters['technician_id'] ) ) {
			$where_clauses[] = "t.technician_id = %d";
			$where_values[]  = $filters['technician_id'];
		}

		if ( ! empty( $filters['project_id'] ) ) {
			$where_clauses[] = "t.project_id = %d";
			$where_values[]  = $filters['project_id'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Summary
		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_entries,
					SUM(total_hours) as total_hours,
					SUM(total_cost) as total_cost,
					SUM(CASE WHEN is_overtime = 1 THEN total_hours ELSE 0 END) as overtime_hours,
					AVG(total_hours) as avg_hours_per_entry,
					COUNT(DISTINCT technician_id) as unique_technicians,
					COUNT(DISTINCT project_id) as unique_projects
				FROM " . ICT_TIME_ENTRIES_TABLE . " t
				WHERE {$where_sql}",
				...$where_values
			),
			ARRAY_A
		);

		// Daily breakdown
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(clock_in) as date,
					COUNT(*) as entries,
					SUM(total_hours) as hours,
					SUM(total_cost) as cost
				FROM " . ICT_TIME_ENTRIES_TABLE . " t
				WHERE {$where_sql}
				GROUP BY DATE(clock_in)
				ORDER BY date",
				...$where_values
			),
			ARRAY_A
		);

		// By technician
		$by_technician = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					t.technician_id,
					u.display_name as technician_name,
					COUNT(*) as entries,
					SUM(t.total_hours) as hours,
					SUM(t.total_cost) as cost,
					SUM(CASE WHEN t.is_overtime = 1 THEN t.total_hours ELSE 0 END) as overtime_hours
				FROM " . ICT_TIME_ENTRIES_TABLE . " t
				LEFT JOIN {$wpdb->users} u ON t.technician_id = u.ID
				WHERE {$where_sql}
				GROUP BY t.technician_id
				ORDER BY hours DESC",
				...$where_values
			),
			ARRAY_A
		);

		// By project
		$by_project = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					t.project_id,
					p.project_name,
					p.project_number,
					COUNT(*) as entries,
					SUM(t.total_hours) as hours,
					SUM(t.total_cost) as cost
				FROM " . ICT_TIME_ENTRIES_TABLE . " t
				LEFT JOIN " . ICT_PROJECTS_TABLE . " p ON t.project_id = p.id
				WHERE {$where_sql}
				GROUP BY t.project_id
				ORDER BY hours DESC",
				...$where_values
			),
			ARRAY_A
		);

		return array(
			'summary'       => $summary,
			'daily'         => $daily,
			'by_technician' => $by_technician,
			'by_project'    => $by_project,
		);
	}

	/**
	 * Get resource utilization report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_resource_utilization_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		// Calculate working days in period
		$start = new DateTime( $start_date );
		$end   = new DateTime( $end_date );
		$diff  = $start->diff( $end );
		$working_days = $diff->days * 5 / 7; // Approximate

		$hours_per_day   = get_option( 'ict_working_hours_per_day', 8 );
		$available_hours = $working_days * $hours_per_day;

		// Get technicians with their hours
		$utilization = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					u.ID as user_id,
					u.display_name,
					COALESCE(SUM(t.total_hours), 0) as actual_hours,
					COALESCE(SUM(r.estimated_hours), 0) as allocated_hours
				FROM {$wpdb->users} u
				LEFT JOIN " . ICT_TIME_ENTRIES_TABLE . " t ON u.ID = t.technician_id
					AND t.clock_in BETWEEN %s AND %s
				LEFT JOIN " . ICT_PROJECT_RESOURCES_TABLE . " r ON u.ID = r.resource_id
					AND r.resource_type = 'user'
					AND r.allocation_start <= %s AND r.allocation_end >= %s
				WHERE u.ID IN (
					SELECT user_id FROM {$wpdb->usermeta}
					WHERE meta_key = '{$wpdb->prefix}capabilities'
					AND meta_value LIKE '%ict_technician%'
				)
				GROUP BY u.ID
				ORDER BY actual_hours DESC",
				$start_date,
				$end_date . ' 23:59:59',
				$end_date,
				$start_date
			),
			ARRAY_A
		);

		foreach ( $utilization as &$row ) {
			$row['utilization_rate']  = $available_hours > 0 ? round( ( $row['actual_hours'] / $available_hours ) * 100, 1 ) : 0;
			$row['available_hours']   = $available_hours;
			$row['allocation_rate']   = $available_hours > 0 ? round( ( $row['allocated_hours'] / $available_hours ) * 100, 1 ) : 0;
		}

		// Overall summary
		$total_actual    = array_sum( array_column( $utilization, 'actual_hours' ) );
		$total_available = count( $utilization ) * $available_hours;

		return array(
			'period' => array(
				'start_date'       => $start_date,
				'end_date'         => $end_date,
				'working_days'     => round( $working_days ),
				'hours_per_day'    => $hours_per_day,
				'available_hours'  => $available_hours,
			),
			'summary' => array(
				'total_technicians'    => count( $utilization ),
				'total_hours_worked'   => $total_actual,
				'total_available'      => $total_available,
				'overall_utilization'  => $total_available > 0 ? round( ( $total_actual / $total_available ) * 100, 1 ) : 0,
			),
			'by_technician' => $utilization,
		);
	}

	/**
	 * Get inventory status report.
	 *
	 * @since  1.1.0
	 * @param  array $filters Filters.
	 * @return array Report data.
	 */
	private function get_inventory_status_report( $filters ) {
		global $wpdb;

		$where_clauses = array( "is_active = 1" );
		$where_values  = array();

		if ( ! empty( $filters['category'] ) ) {
			$where_clauses[] = "category = %s";
			$where_values[]  = $filters['category'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Summary
		$summary = $wpdb->get_row(
			count( $where_values ) > 0
				? $wpdb->prepare(
					"SELECT
						COUNT(*) as total_items,
						SUM(quantity_on_hand) as total_quantity,
						SUM(quantity_on_hand * unit_cost) as total_value,
						SUM(CASE WHEN quantity_available <= reorder_level THEN 1 ELSE 0 END) as low_stock_count,
						SUM(CASE WHEN quantity_on_hand = 0 THEN 1 ELSE 0 END) as out_of_stock_count
					FROM " . ICT_INVENTORY_ITEMS_TABLE . "
					WHERE {$where_sql}",
					...$where_values
				)
				: "SELECT
					COUNT(*) as total_items,
					SUM(quantity_on_hand) as total_quantity,
					SUM(quantity_on_hand * unit_cost) as total_value,
					SUM(CASE WHEN quantity_available <= reorder_level THEN 1 ELSE 0 END) as low_stock_count,
					SUM(CASE WHEN quantity_on_hand = 0 THEN 1 ELSE 0 END) as out_of_stock_count
				FROM " . ICT_INVENTORY_ITEMS_TABLE . "
				WHERE {$where_sql}",
			ARRAY_A
		);

		// By category
		$by_category = $wpdb->get_results(
			count( $where_values ) > 0
				? $wpdb->prepare(
					"SELECT
						category,
						COUNT(*) as item_count,
						SUM(quantity_on_hand) as total_quantity,
						SUM(quantity_on_hand * unit_cost) as total_value
					FROM " . ICT_INVENTORY_ITEMS_TABLE . "
					WHERE {$where_sql}
					GROUP BY category
					ORDER BY total_value DESC",
					...$where_values
				)
				: "SELECT
					category,
					COUNT(*) as item_count,
					SUM(quantity_on_hand) as total_quantity,
					SUM(quantity_on_hand * unit_cost) as total_value
				FROM " . ICT_INVENTORY_ITEMS_TABLE . "
				WHERE {$where_sql}
				GROUP BY category
				ORDER BY total_value DESC",
			ARRAY_A
		);

		// Low stock items
		$low_stock = $wpdb->get_results(
			"SELECT
				id, sku, item_name, category,
				quantity_on_hand, quantity_available, reorder_level, reorder_quantity,
				unit_cost, supplier_id
			FROM " . ICT_INVENTORY_ITEMS_TABLE . "
			WHERE is_active = 1 AND quantity_available <= reorder_level
			ORDER BY (reorder_level - quantity_available) DESC
			LIMIT 50",
			ARRAY_A
		);

		// Top value items
		$top_value = $wpdb->get_results(
			"SELECT
				id, sku, item_name, category,
				quantity_on_hand, unit_cost,
				(quantity_on_hand * unit_cost) as total_value
			FROM " . ICT_INVENTORY_ITEMS_TABLE . "
			WHERE is_active = 1
			ORDER BY total_value DESC
			LIMIT 20",
			ARRAY_A
		);

		return array(
			'summary'     => $summary,
			'by_category' => $by_category,
			'low_stock'   => $low_stock,
			'top_value'   => $top_value,
		);
	}

	/**
	 * Get purchase orders report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_purchase_orders_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		// Summary
		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					SUM(total_amount) as total_value,
					SUM(CASE WHEN status = 'approved' THEN total_amount ELSE 0 END) as approved_value,
					SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_value,
					SUM(CASE WHEN status = 'received' THEN total_amount ELSE 0 END) as received_value,
					AVG(total_amount) as avg_order_value
				FROM " . ICT_PURCHASE_ORDERS_TABLE . "
				WHERE po_date BETWEEN %s AND %s",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// By status
		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					status,
					COUNT(*) as count,
					SUM(total_amount) as total_value
				FROM " . ICT_PURCHASE_ORDERS_TABLE . "
				WHERE po_date BETWEEN %s AND %s
				GROUP BY status",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Monthly trend
		$monthly = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(po_date, '%%Y-%%m') as month,
					COUNT(*) as orders,
					SUM(total_amount) as total_value
				FROM " . ICT_PURCHASE_ORDERS_TABLE . "
				WHERE po_date BETWEEN %s AND %s
				GROUP BY DATE_FORMAT(po_date, '%%Y-%%m')
				ORDER BY month",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Orders list
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					po_number, po_date, status, total_amount,
					delivery_date, received_date
				FROM " . ICT_PURCHASE_ORDERS_TABLE . "
				WHERE po_date BETWEEN %s AND %s
				ORDER BY po_date DESC
				LIMIT 100",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		return array(
			'summary'   => $summary,
			'by_status' => $by_status,
			'monthly'   => $monthly,
			'orders'    => $orders,
		);
	}

	/**
	 * Get financial summary report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_financial_summary_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		// Project financials
		$project_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(budget_amount) as total_budget,
					SUM(actual_cost) as total_actual_cost
				FROM " . ICT_PROJECTS_TABLE . "
				WHERE created_at BETWEEN %s AND %s",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		// Labor costs
		$labor_costs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total_cost)
				FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE clock_in BETWEEN %s AND %s",
				$start_date,
				$end_date . ' 23:59:59'
			)
		);

		// PO costs
		$po_costs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total_amount)
				FROM " . ICT_PURCHASE_ORDERS_TABLE . "
				WHERE po_date BETWEEN %s AND %s AND status != 'cancelled'",
				$start_date,
				$end_date
			)
		);

		// Monthly breakdown
		$monthly = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(created_at, '%%Y-%%m') as month,
					SUM(budget_amount) as budget,
					SUM(actual_cost) as actual
				FROM " . ICT_PROJECTS_TABLE . "
				WHERE created_at BETWEEN %s AND %s
				GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
				ORDER BY month",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		return array(
			'summary' => array(
				'total_budget'    => $project_totals['total_budget'] ?? 0,
				'total_costs'     => $project_totals['total_actual_cost'] ?? 0,
				'labor_costs'     => $labor_costs ?? 0,
				'material_costs'  => $po_costs ?? 0,
				'budget_variance' => ( $project_totals['total_budget'] ?? 0 ) - ( $project_totals['total_actual_cost'] ?? 0 ),
			),
			'monthly' => $monthly,
		);
	}

	/**
	 * Get technician performance report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_technician_performance_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		$technicians = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					t.technician_id,
					u.display_name as name,
					COUNT(DISTINCT t.id) as total_entries,
					COUNT(DISTINCT t.project_id) as projects_worked,
					SUM(t.total_hours) as total_hours,
					SUM(CASE WHEN t.is_overtime = 1 THEN t.total_hours ELSE 0 END) as overtime_hours,
					AVG(t.total_hours) as avg_hours_per_entry,
					SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) as approved_entries,
					SUM(CASE WHEN t.status = 'rejected' THEN 1 ELSE 0 END) as rejected_entries
				FROM " . ICT_TIME_ENTRIES_TABLE . " t
				LEFT JOIN {$wpdb->users} u ON t.technician_id = u.ID
				WHERE t.clock_in BETWEEN %s AND %s
				GROUP BY t.technician_id
				ORDER BY total_hours DESC",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		foreach ( $technicians as &$tech ) {
			$tech['approval_rate'] = $tech['total_entries'] > 0
				? round( ( $tech['approved_entries'] / $tech['total_entries'] ) * 100, 1 )
				: 0;
			$tech['overtime_rate'] = $tech['total_hours'] > 0
				? round( ( $tech['overtime_hours'] / $tech['total_hours'] ) * 100, 1 )
				: 0;
		}

		return array(
			'technicians' => $technicians,
		);
	}

	/**
	 * Get productivity report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_productivity_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		// Hours by day of week
		$by_day = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(clock_in) as day_num,
					DAYNAME(clock_in) as day_name,
					SUM(total_hours) as hours,
					COUNT(*) as entries
				FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE clock_in BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(clock_in), DAYNAME(clock_in)
				ORDER BY day_num",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		// Hours by hour of day
		$by_hour = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					HOUR(clock_in) as hour,
					COUNT(*) as clock_ins
				FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE clock_in BETWEEN %s AND %s
				GROUP BY HOUR(clock_in)
				ORDER BY hour",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		return array(
			'by_day_of_week' => $by_day,
			'by_hour'        => $by_hour,
		);
	}

	/**
	 * Get project profitability report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_project_profitability_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.id, p.project_name, p.project_number, p.status,
					p.budget_amount, p.actual_cost,
					(p.budget_amount - p.actual_cost) as profit,
					CASE
						WHEN p.budget_amount > 0 THEN ((p.budget_amount - p.actual_cost) / p.budget_amount * 100)
						ELSE 0
					END as margin_percent,
					p.estimated_hours, p.actual_hours,
					COALESCE(labor.labor_cost, 0) as labor_cost
				FROM " . ICT_PROJECTS_TABLE . " p
				LEFT JOIN (
					SELECT project_id, SUM(total_cost) as labor_cost
					FROM " . ICT_TIME_ENTRIES_TABLE . "
					GROUP BY project_id
				) labor ON p.id = labor.project_id
				WHERE p.created_at BETWEEN %s AND %s
				ORDER BY profit DESC",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		// Summary
		$total_budget = array_sum( array_column( $projects, 'budget_amount' ) );
		$total_actual = array_sum( array_column( $projects, 'actual_cost' ) );
		$total_profit = array_sum( array_column( $projects, 'profit' ) );

		return array(
			'summary' => array(
				'total_projects' => count( $projects ),
				'total_budget'   => $total_budget,
				'total_cost'     => $total_actual,
				'total_profit'   => $total_profit,
				'avg_margin'     => $total_budget > 0 ? round( ( $total_profit / $total_budget ) * 100, 1 ) : 0,
			),
			'projects' => $projects,
		);
	}

	/**
	 * Get overtime analysis report.
	 *
	 * @since  1.1.0
	 * @param  string $start_date Start date.
	 * @param  string $end_date   End date.
	 * @param  array  $filters    Filters.
	 * @return array Report data.
	 */
	private function get_overtime_analysis_report( $start_date, $end_date, $filters ) {
		global $wpdb;

		// Summary
		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(total_hours) as total_hours,
					SUM(CASE WHEN is_overtime = 1 THEN total_hours ELSE 0 END) as overtime_hours,
					SUM(total_cost) as total_cost,
					SUM(CASE WHEN is_overtime = 1 THEN total_cost ELSE 0 END) as overtime_cost
				FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE clock_in BETWEEN %s AND %s",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		// By technician
		$by_technician = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					t.technician_id,
					u.display_name as name,
					SUM(t.total_hours) as total_hours,
					SUM(CASE WHEN t.is_overtime = 1 THEN t.total_hours ELSE 0 END) as overtime_hours,
					SUM(CASE WHEN t.is_overtime = 1 THEN t.total_cost ELSE 0 END) as overtime_cost
				FROM " . ICT_TIME_ENTRIES_TABLE . " t
				LEFT JOIN {$wpdb->users} u ON t.technician_id = u.ID
				WHERE t.clock_in BETWEEN %s AND %s
				GROUP BY t.technician_id
				HAVING overtime_hours > 0
				ORDER BY overtime_hours DESC",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		// Weekly trend
		$weekly = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					YEARWEEK(clock_in) as week,
					MIN(DATE(clock_in)) as week_start,
					SUM(total_hours) as total_hours,
					SUM(CASE WHEN is_overtime = 1 THEN total_hours ELSE 0 END) as overtime_hours
				FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE clock_in BETWEEN %s AND %s
				GROUP BY YEARWEEK(clock_in)
				ORDER BY week",
				$start_date,
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$summary['overtime_percentage'] = $summary['total_hours'] > 0
			? round( ( $summary['overtime_hours'] / $summary['total_hours'] ) * 100, 1 )
			: 0;

		return array(
			'summary'       => $summary,
			'by_technician' => $by_technician,
			'weekly_trend'  => $weekly,
		);
	}

	/**
	 * Export report.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function export_report( $request ) {
		$type       = $request->get_param( 'type' );
		$format     = $request->get_param( 'format' ) ?? 'csv';
		$start_date = $request->get_param( 'start_date' ) ?? date( 'Y-m-01' );
		$end_date   = $request->get_param( 'end_date' ) ?? date( 'Y-m-t' );
		$filters    = $request->get_param( 'filters' ) ?? array();

		if ( ! in_array( $format, $this->export_formats, true ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => 'Invalid export format',
			), 400 );
		}

		$data = $this->generate_report_data( $type, $start_date, $end_date, $filters );

		return $this->export_report_data( $data, $type, $format );
	}

	/**
	 * Export report data to file.
	 *
	 * @since  1.1.0
	 * @param  array  $data   Report data.
	 * @param  string $type   Report type.
	 * @param  string $format Export format.
	 * @return WP_REST_Response Response with download URL.
	 */
	private function export_report_data( $data, $type, $format ) {
		$filename = "ict-report-{$type}-" . date( 'Y-m-d-His' );
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/ict-platform/exports';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		switch ( $format ) {
			case 'csv':
				$file_path = $this->export_to_csv( $data, $export_dir, $filename );
				break;

			case 'xlsx':
				$file_path = $this->export_to_xlsx( $data, $export_dir, $filename );
				break;

			case 'pdf':
				$file_path = $this->export_to_pdf( $data, $export_dir, $filename, $type );
				break;

			case 'json':
				$file_path = $export_dir . "/{$filename}.json";
				file_put_contents( $file_path, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
				break;

			default:
				return new WP_REST_Response( array(
					'success' => false,
					'error'   => 'Unsupported format',
				), 400 );
		}

		$file_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );

		return new WP_REST_Response( array(
			'success'      => true,
			'download_url' => $file_url,
			'filename'     => basename( $file_path ),
			'expires'      => time() + 3600, // 1 hour
		), 200 );
	}

	/**
	 * Export to CSV.
	 *
	 * @since  1.1.0
	 * @param  array  $data      Report data.
	 * @param  string $dir       Export directory.
	 * @param  string $filename  Filename without extension.
	 * @return string File path.
	 */
	private function export_to_csv( $data, $dir, $filename ) {
		$file_path = "{$dir}/{$filename}.csv";
		$fp = fopen( $file_path, 'w' );

		// Flatten and write data
		foreach ( $data as $section => $rows ) {
			if ( is_array( $rows ) && ! empty( $rows ) ) {
				if ( isset( $rows[0] ) && is_array( $rows[0] ) ) {
					// Write section header
					fputcsv( $fp, array( strtoupper( $section ) ) );
					fputcsv( $fp, array_keys( $rows[0] ) );

					foreach ( $rows as $row ) {
						fputcsv( $fp, array_values( $row ) );
					}

					fputcsv( $fp, array() ); // Empty line between sections
				} elseif ( ! is_array( $rows ) || ! isset( $rows[0] ) ) {
					// Single row data (summary)
					fputcsv( $fp, array( strtoupper( $section ) ) );
					foreach ( $rows as $key => $value ) {
						fputcsv( $fp, array( $key, $value ) );
					}
					fputcsv( $fp, array() );
				}
			}
		}

		fclose( $fp );

		return $file_path;
	}

	/**
	 * Export to XLSX.
	 *
	 * @since  1.1.0
	 * @param  array  $data      Report data.
	 * @param  string $dir       Export directory.
	 * @param  string $filename  Filename without extension.
	 * @return string File path.
	 */
	private function export_to_xlsx( $data, $dir, $filename ) {
		// Simple XLSX implementation using XML
		$file_path = "{$dir}/{$filename}.xlsx";

		// For a full implementation, use PhpSpreadsheet library
		// This is a simplified version that creates a CSV file with .xlsx extension
		// In production, integrate PhpSpreadsheet for proper Excel files

		return $this->export_to_csv( $data, $dir, $filename );
	}

	/**
	 * Export to PDF.
	 *
	 * @since  1.1.0
	 * @param  array  $data      Report data.
	 * @param  string $dir       Export directory.
	 * @param  string $filename  Filename without extension.
	 * @param  string $type      Report type.
	 * @return string File path.
	 */
	private function export_to_pdf( $data, $dir, $filename, $type ) {
		$file_path = "{$dir}/{$filename}.pdf";

		// Generate HTML content
		$html = $this->generate_pdf_html( $data, $type );

		// For full PDF generation, integrate mPDF or TCPDF library
		// This is a placeholder that saves HTML
		file_put_contents( str_replace( '.pdf', '.html', $file_path ), $html );

		return str_replace( '.pdf', '.html', $file_path );
	}

	/**
	 * Generate HTML for PDF export.
	 *
	 * @since  1.1.0
	 * @param  array  $data Report data.
	 * @param  string $type Report type.
	 * @return string HTML content.
	 */
	private function generate_pdf_html( $data, $type ) {
		$title = str_replace( '_', ' ', ucwords( $type, '_' ) );

		$html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$html .= '<title>' . esc_html( $title ) . ' Report</title>';
		$html .= '<style>
			body { font-family: Arial, sans-serif; font-size: 12px; }
			h1 { color: #333; }
			h2 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
			table { width: 100%; border-collapse: collapse; margin: 10px 0; }
			th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
			th { background-color: #f5f5f5; }
			.summary { background-color: #f9f9f9; padding: 15px; margin: 10px 0; }
		</style></head><body>';

		$html .= '<h1>' . esc_html( $title ) . ' Report</h1>';
		$html .= '<p>Generated: ' . current_time( 'mysql' ) . '</p>';

		foreach ( $data as $section => $content ) {
			$html .= '<h2>' . esc_html( ucwords( str_replace( '_', ' ', $section ) ) ) . '</h2>';

			if ( is_array( $content ) && ! empty( $content ) ) {
				if ( isset( $content[0] ) && is_array( $content[0] ) ) {
					// Table data
					$html .= '<table><thead><tr>';
					foreach ( array_keys( $content[0] ) as $header ) {
						$html .= '<th>' . esc_html( ucwords( str_replace( '_', ' ', $header ) ) ) . '</th>';
					}
					$html .= '</tr></thead><tbody>';

					foreach ( $content as $row ) {
						$html .= '<tr>';
						foreach ( $row as $value ) {
							$html .= '<td>' . esc_html( $value ) . '</td>';
						}
						$html .= '</tr>';
					}

					$html .= '</tbody></table>';
				} else {
					// Key-value data
					$html .= '<div class="summary">';
					foreach ( $content as $key => $value ) {
						if ( ! is_array( $value ) ) {
							$html .= '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
						}
					}
					$html .= '</div>';
				}
			}
		}

		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Schedule recurring report.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function schedule_report( $request ) {
		$type       = $request->get_param( 'type' );
		$frequency  = $request->get_param( 'frequency' ); // daily, weekly, monthly
		$format     = $request->get_param( 'format' ) ?? 'pdf';
		$recipients = $request->get_param( 'recipients' ) ?? array();
		$filters    = $request->get_param( 'filters' ) ?? array();

		if ( ! in_array( $frequency, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => 'Invalid frequency',
			), 400 );
		}

		$schedule_id = wp_generate_uuid4();

		$schedules   = get_option( 'ict_scheduled_reports', array() );
		$schedules[] = array(
			'id'         => $schedule_id,
			'type'       => $type,
			'frequency'  => $frequency,
			'format'     => $format,
			'recipients' => $recipients,
			'filters'    => $filters,
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		);

		update_option( 'ict_scheduled_reports', $schedules );

		// Schedule cron event
		$hook      = 'ict_generate_scheduled_report';
		$timestamp = $this->get_next_schedule_time( $frequency );

		wp_schedule_event( $timestamp, $frequency, $hook, array( $schedule_id ) );

		return new WP_REST_Response( array(
			'success'     => true,
			'schedule_id' => $schedule_id,
		), 200 );
	}

	/**
	 * Get next schedule time.
	 *
	 * @since  1.1.0
	 * @param  string $frequency Frequency.
	 * @return int Timestamp.
	 */
	private function get_next_schedule_time( $frequency ) {
		$now = current_time( 'timestamp' );

		switch ( $frequency ) {
			case 'daily':
				return strtotime( 'tomorrow 6:00am', $now );

			case 'weekly':
				return strtotime( 'next monday 6:00am', $now );

			case 'monthly':
				return strtotime( 'first day of next month 6:00am', $now );

			default:
				return $now + DAY_IN_SECONDS;
		}
	}

	/**
	 * Generate scheduled report.
	 *
	 * @since  1.1.0
	 * @param  string $schedule_id Schedule ID.
	 * @return void
	 */
	public function generate_scheduled_report( $schedule_id ) {
		$schedules = get_option( 'ict_scheduled_reports', array() );
		$schedule  = null;

		foreach ( $schedules as $s ) {
			if ( $s['id'] === $schedule_id ) {
				$schedule = $s;
				break;
			}
		}

		if ( ! $schedule ) {
			return;
		}

		// Determine date range based on frequency
		$end_date = date( 'Y-m-d' );
		switch ( $schedule['frequency'] ) {
			case 'daily':
				$start_date = date( 'Y-m-d', strtotime( '-1 day' ) );
				break;

			case 'weekly':
				$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
				break;

			case 'monthly':
				$start_date = date( 'Y-m-01', strtotime( '-1 month' ) );
				$end_date   = date( 'Y-m-t', strtotime( '-1 month' ) );
				break;

			default:
				$start_date = date( 'Y-m-01' );
		}

		// Generate report
		$data = $this->generate_report_data(
			$schedule['type'],
			$start_date,
			$end_date,
			$schedule['filters'] ?? array()
		);

		// Export to file
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/ict-platform/exports';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$filename = "ict-scheduled-{$schedule['type']}-" . date( 'Y-m-d' );

		switch ( $schedule['format'] ) {
			case 'csv':
				$file_path = $this->export_to_csv( $data, $export_dir, $filename );
				break;

			case 'pdf':
				$file_path = $this->export_to_pdf( $data, $export_dir, $filename, $schedule['type'] );
				break;

			default:
				$file_path = "{$export_dir}/{$filename}.json";
				file_put_contents( $file_path, wp_json_encode( $data ) );
		}

		// Send to recipients
		if ( ! empty( $schedule['recipients'] ) ) {
			$email_handler = new ICT_Email_Notification();

			$title = str_replace( '_', ' ', ucwords( $schedule['type'], '_' ) );

			$data = array(
				'title'   => sprintf( __( 'Scheduled Report: %s', 'ict-platform' ), $title ),
				'message' => sprintf(
					__( 'Your scheduled %s report for %s to %s is attached.', 'ict-platform' ),
					strtolower( $title ),
					$start_date,
					$end_date
				),
			);

			foreach ( $schedule['recipients'] as $recipient ) {
				$email_handler->send( 'scheduled_report', array( $recipient ), $data );
			}
		}
	}

	/**
	 * Get report templates.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_report_templates( $request ) {
		$templates = array(
			array(
				'id'          => 'project_summary',
				'name'        => __( 'Project Summary', 'ict-platform' ),
				'description' => __( 'Overview of all projects with status, budget, and progress', 'ict-platform' ),
				'category'    => 'projects',
			),
			array(
				'id'          => 'time_entries',
				'name'        => __( 'Time Entries', 'ict-platform' ),
				'description' => __( 'Detailed time tracking report with breakdown by technician and project', 'ict-platform' ),
				'category'    => 'time_tracking',
			),
			array(
				'id'          => 'resource_utilization',
				'name'        => __( 'Resource Utilization', 'ict-platform' ),
				'description' => __( 'Technician utilization rates and allocation analysis', 'ict-platform' ),
				'category'    => 'resources',
			),
			array(
				'id'          => 'inventory_status',
				'name'        => __( 'Inventory Status', 'ict-platform' ),
				'description' => __( 'Current inventory levels, low stock alerts, and valuations', 'ict-platform' ),
				'category'    => 'inventory',
			),
			array(
				'id'          => 'purchase_orders',
				'name'        => __( 'Purchase Orders', 'ict-platform' ),
				'description' => __( 'PO summary with status breakdown and spending analysis', 'ict-platform' ),
				'category'    => 'procurement',
			),
			array(
				'id'          => 'financial_summary',
				'name'        => __( 'Financial Summary', 'ict-platform' ),
				'description' => __( 'Budget vs actual costs, labor and material expenses', 'ict-platform' ),
				'category'    => 'financial',
			),
			array(
				'id'          => 'technician_performance',
				'name'        => __( 'Technician Performance', 'ict-platform' ),
				'description' => __( 'Individual technician metrics and approval rates', 'ict-platform' ),
				'category'    => 'performance',
			),
			array(
				'id'          => 'project_profitability',
				'name'        => __( 'Project Profitability', 'ict-platform' ),
				'description' => __( 'Profit margins and cost analysis by project', 'ict-platform' ),
				'category'    => 'financial',
			),
			array(
				'id'          => 'overtime_analysis',
				'name'        => __( 'Overtime Analysis', 'ict-platform' ),
				'description' => __( 'Overtime hours and costs by technician and period', 'ict-platform' ),
				'category'    => 'time_tracking',
			),
			array(
				'id'          => 'productivity',
				'name'        => __( 'Productivity Analysis', 'ict-platform' ),
				'description' => __( 'Work patterns by day and hour', 'ict-platform' ),
				'category'    => 'performance',
			),
		);

		return new WP_REST_Response( array(
			'success'   => true,
			'templates' => $templates,
		), 200 );
	}
}
