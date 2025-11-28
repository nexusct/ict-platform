<?php
/**
 * Profitability Analysis
 *
 * Track and analyze project and overall business profitability.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Profitability {

	private static $instance = null;
	private $costs_table;
	private $rates_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->costs_table = $wpdb->prefix . 'ict_cost_entries';
		$this->rates_table = $wpdb->prefix . 'ict_billing_rates';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/profitability/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/projects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_profitability' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/projects/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_analysis' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/employees',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_employee_profitability' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/clients',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_profitability' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/trends',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_trends' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/rates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rates' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_rate' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/costs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_costs' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_cost' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/profitability/forecast',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_forecast' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'view_ict_reports' ) || current_user_can( 'manage_options' );
	}

	public function check_edit_permission() {
		return current_user_can( 'manage_ict_finance' ) || current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->costs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned,
            cost_type enum('labor','material','equipment','overhead','subcontractor','other') NOT NULL,
            description varchar(255) NOT NULL,
            amount decimal(15,2) NOT NULL,
            quantity decimal(10,2) DEFAULT 1,
            unit_cost decimal(15,2),
            date date NOT NULL,
            vendor varchar(255),
            reference_number varchar(100),
            is_billable tinyint(1) DEFAULT 0,
            is_reimbursable tinyint(1) DEFAULT 0,
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY cost_type (cost_type),
            KEY date (date)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->rates_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rate_type enum('user','role','project','client','default') NOT NULL,
            reference_id bigint(20) unsigned,
            billing_rate decimal(10,2) NOT NULL,
            cost_rate decimal(10,2),
            overtime_multiplier decimal(3,2) DEFAULT 1.5,
            effective_from date NOT NULL,
            effective_to date,
            currency varchar(3) DEFAULT 'USD',
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rate_type_ref (rate_type, reference_id),
            KEY effective_from (effective_from)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	public function get_overview( $request ) {
		global $wpdb;

		$period = $request->get_param( 'period' ) ?: 'month';
		$dates  = $this->get_period_dates( $period );

		// Revenue
		$revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total_amount), 0) FROM {$wpdb->prefix}ict_projects
             WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
				$dates['start'],
				$dates['end']
			)
		);

		// Labor costs (from time entries)
		$labor = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(
                TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time) / 60 *
                COALESCE(r.cost_rate, 50)
            ), 0)
             FROM {$wpdb->prefix}ict_time_entries t
             LEFT JOIN {$this->rates_table} r ON r.rate_type = 'user' AND r.reference_id = t.user_id
             WHERE t.start_time BETWEEN %s AND %s",
				$dates['start'],
				$dates['end']
			)
		);

		// Material and other costs
		$materials = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->costs_table}
             WHERE cost_type = 'material' AND date BETWEEN %s AND %s",
				$dates['start'],
				$dates['end']
			)
		);

		$other_costs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->costs_table}
             WHERE cost_type NOT IN ('material', 'labor') AND date BETWEEN %s AND %s",
				$dates['start'],
				$dates['end']
			)
		);

		$total_costs  = $labor + $materials + $other_costs;
		$gross_profit = $revenue - $total_costs;
		$margin       = $revenue > 0 ? round( ( $gross_profit / $revenue ) * 100, 1 ) : 0;

		// Previous period for comparison
		$prev_dates   = $this->get_period_dates( $period, -1 );
		$prev_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total_amount), 0) FROM {$wpdb->prefix}ict_projects
             WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
				$prev_dates['start'],
				$prev_dates['end']
			)
		);

		return rest_ensure_response(
			array(
				'period'         => $period,
				'revenue'        => (float) $revenue,
				'labor_cost'     => (float) $labor,
				'material_cost'  => (float) $materials,
				'other_costs'    => (float) $other_costs,
				'total_costs'    => (float) $total_costs,
				'gross_profit'   => (float) $gross_profit,
				'profit_margin'  => $margin,
				'revenue_change' => $prev_revenue > 0 ? round( ( ( $revenue - $prev_revenue ) / $prev_revenue ) * 100, 1 ) : null,
			)
		);
	}

	public function get_project_profitability( $request ) {
		global $wpdb;

		$status = $request->get_param( 'status' );
		$limit  = (int) $request->get_param( 'limit' ) ?: 20;
		$sort   = $request->get_param( 'sort' ) ?: 'margin';

		$where  = '1=1';
		$values = array();

		if ( $status ) {
			$where   .= ' AND p.status = %s';
			$values[] = $status;
		}

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.project_number, p.name, p.status, p.total_amount as revenue,
                    p.start_date, p.end_date
             FROM {$wpdb->prefix}ict_projects p
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT %d",
				array_merge( $values, array( $limit ) )
			)
		);

		foreach ( $projects as $project ) {
			$costs                  = $this->get_project_costs( $project->id );
			$project->labor_cost    = $costs['labor'];
			$project->material_cost = $costs['material'];
			$project->other_costs   = $costs['other'];
			$project->total_costs   = $costs['total'];
			$project->gross_profit  = $project->revenue - $project->total_costs;
			$project->profit_margin = $project->revenue > 0
				? round( ( $project->gross_profit / $project->revenue ) * 100, 1 )
				: 0;
		}

		// Sort by requested field
		usort(
			$projects,
			function ( $a, $b ) use ( $sort ) {
				switch ( $sort ) {
					case 'margin':
						return $b->profit_margin <=> $a->profit_margin;
					case 'profit':
						return $b->gross_profit <=> $a->gross_profit;
					case 'revenue':
						return $b->revenue <=> $a->revenue;
					default:
						return 0;
				}
			}
		);

		return rest_ensure_response( $projects );
	}

	public function get_project_analysis( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_projects WHERE id = %d",
				$id
			)
		);

		if ( ! $project ) {
			return new WP_Error( 'not_found', 'Project not found', array( 'status' => 404 ) );
		}

		// Get all costs breakdown
		$costs = $this->get_project_costs( $id, true );

		// Get time entries with rates
		$time_entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, u.display_name,
                    TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time) / 60 as hours,
                    COALESCE(r.billing_rate, 0) as billing_rate,
                    COALESCE(r.cost_rate, 0) as cost_rate
             FROM {$wpdb->prefix}ict_time_entries t
             LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
             LEFT JOIN {$this->rates_table} r ON r.rate_type = 'user' AND r.reference_id = t.user_id
             WHERE t.project_id = %d
             ORDER BY t.start_time DESC",
				$id
			)
		);

		$billable_value = 0;
		$labor_cost     = 0;
		foreach ( $time_entries as $entry ) {
			$entry->billable_value = $entry->hours * $entry->billing_rate;
			$entry->cost           = $entry->hours * $entry->cost_rate;
			$billable_value       += $entry->billable_value;
			$labor_cost           += $entry->cost;
		}

		// Budget analysis
		$budget_used = ( $costs['total'] / max( $project->budget, 1 ) ) * 100;

		return rest_ensure_response(
			array(
				'project'         => $project,
				'revenue'         => (float) $project->total_amount,
				'budget'          => (float) $project->budget,
				'costs'           => $costs,
				'billable_value'  => $billable_value,
				'labor_cost'      => $labor_cost,
				'gross_profit'    => $project->total_amount - $costs['total'],
				'profit_margin'   => $project->total_amount > 0
					? round( ( ( $project->total_amount - $costs['total'] ) / $project->total_amount ) * 100, 1 )
					: 0,
				'budget_used_pct' => round( $budget_used, 1 ),
				'time_entries'    => $time_entries,
			)
		);
	}

	public function get_employee_profitability( $request ) {
		global $wpdb;

		$period = $request->get_param( 'period' ) ?: 'month';
		$dates  = $this->get_period_dates( $period );

		$employees = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.user_id, u.display_name,
                    SUM(TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time)) / 60 as total_hours,
                    SUM(CASE WHEN t.is_billable = 1 THEN TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time) ELSE 0 END) / 60 as billable_hours
             FROM {$wpdb->prefix}ict_time_entries t
             JOIN {$wpdb->users} u ON t.user_id = u.ID
             WHERE t.start_time BETWEEN %s AND %s
             GROUP BY t.user_id
             ORDER BY billable_hours DESC",
				$dates['start'],
				$dates['end']
			)
		);

		foreach ( $employees as $emp ) {
			$rate = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT billing_rate, cost_rate FROM {$this->rates_table}
                 WHERE rate_type = 'user' AND reference_id = %d
                 ORDER BY effective_from DESC LIMIT 1",
					$emp->user_id
				)
			);

			$emp->billing_rate = $rate ? (float) $rate->billing_rate : 0;
			$emp->cost_rate    = $rate ? (float) $rate->cost_rate : 0;
			$emp->revenue      = $emp->billable_hours * $emp->billing_rate;
			$emp->cost         = $emp->total_hours * $emp->cost_rate;
			$emp->profit       = $emp->revenue - $emp->cost;
			$emp->utilization  = $emp->total_hours > 0
				? round( ( $emp->billable_hours / $emp->total_hours ) * 100, 1 )
				: 0;
		}

		return rest_ensure_response( $employees );
	}

	public function get_client_profitability( $request ) {
		global $wpdb;

		$period = $request->get_param( 'period' ) ?: 'year';
		$dates  = $this->get_period_dates( $period );

		$clients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.client_id,
                    COUNT(*) as project_count,
                    SUM(p.total_amount) as total_revenue
             FROM {$wpdb->prefix}ict_projects p
             WHERE p.status = 'completed' AND p.end_date BETWEEN %s AND %s
             GROUP BY p.client_id
             ORDER BY total_revenue DESC",
				$dates['start'],
				$dates['end']
			)
		);

		foreach ( $clients as $client ) {
			// Get client name from user meta or posts
			$client_data  = get_userdata( $client->client_id );
			$client->name = $client_data ? $client_data->display_name : 'Unknown Client';

			// Calculate costs for all client projects
			$project_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ict_projects
                 WHERE client_id = %d AND status = 'completed' AND end_date BETWEEN %s AND %s",
					$client->client_id,
					$dates['start'],
					$dates['end']
				)
			);

			$total_costs = 0;
			foreach ( $project_ids as $pid ) {
				$costs        = $this->get_project_costs( $pid );
				$total_costs += $costs['total'];
			}

			$client->total_costs       = $total_costs;
			$client->gross_profit      = $client->total_revenue - $total_costs;
			$client->profit_margin     = $client->total_revenue > 0
				? round( ( $client->gross_profit / $client->total_revenue ) * 100, 1 )
				: 0;
			$client->avg_project_value = $client->project_count > 0
				? round( $client->total_revenue / $client->project_count, 2 )
				: 0;
		}

		return rest_ensure_response( $clients );
	}

	public function get_trends( $request ) {
		global $wpdb;

		$months = (int) $request->get_param( 'months' ) ?: 12;

		$trends = array();
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$start = date( 'Y-m-01', strtotime( "-{$i} months" ) );
			$end   = date( 'Y-m-t', strtotime( "-{$i} months" ) );

			$revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(total_amount), 0) FROM {$wpdb->prefix}ict_projects
                 WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
					$start,
					$end
				)
			);

			$costs = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(amount), 0) FROM {$this->costs_table}
                 WHERE date BETWEEN %s AND %s",
					$start,
					$end
				)
			);

			$labor = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60 * 50), 0)
                 FROM {$wpdb->prefix}ict_time_entries WHERE start_time BETWEEN %s AND %s",
					$start,
					$end
				)
			);

			$total_costs = $costs + $labor;
			$profit      = $revenue - $total_costs;

			$trends[] = array(
				'period'        => date( 'Y-m', strtotime( $start ) ),
				'revenue'       => (float) $revenue,
				'costs'         => (float) $total_costs,
				'profit'        => (float) $profit,
				'profit_margin' => $revenue > 0 ? round( ( $profit / $revenue ) * 100, 1 ) : 0,
			);
		}

		return rest_ensure_response( $trends );
	}

	public function get_rates( $request ) {
		global $wpdb;

		$type = $request->get_param( 'type' );

		$where  = '1=1';
		$values = array();

		if ( $type ) {
			$where   .= ' AND rate_type = %s';
			$values[] = $type;
		}

		$query = "SELECT * FROM {$this->rates_table} WHERE {$where} ORDER BY rate_type, reference_id";

		$rates = ! empty( $values )
			? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
			: $wpdb->get_results( $query );

		return rest_ensure_response( $rates );
	}

	public function save_rate( $request ) {
		global $wpdb;

		$data = array(
			'rate_type'           => sanitize_text_field( $request->get_param( 'rate_type' ) ),
			'reference_id'        => (int) $request->get_param( 'reference_id' ) ?: null,
			'billing_rate'        => (float) $request->get_param( 'billing_rate' ),
			'cost_rate'           => (float) $request->get_param( 'cost_rate' ),
			'overtime_multiplier' => (float) $request->get_param( 'overtime_multiplier' ) ?: 1.5,
			'effective_from'      => sanitize_text_field( $request->get_param( 'effective_from' ) ),
			'effective_to'        => sanitize_text_field( $request->get_param( 'effective_to' ) ) ?: null,
			'currency'            => sanitize_text_field( $request->get_param( 'currency' ) ) ?: 'USD',
			'notes'               => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'created_by'          => get_current_user_id(),
		);

		$id = $request->get_param( 'id' );
		if ( $id ) {
			unset( $data['created_by'] );
			$wpdb->update( $this->rates_table, $data, array( 'id' => (int) $id ) );
			return rest_ensure_response(
				array(
					'success' => true,
					'id'      => (int) $id,
				)
			);
		}

		$wpdb->insert( $this->rates_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function get_costs( $request ) {
		global $wpdb;

		$project_id = $request->get_param( 'project_id' );
		$type       = $request->get_param( 'type' );
		$start      = $request->get_param( 'start' );
		$end        = $request->get_param( 'end' );

		$where  = '1=1';
		$values = array();

		if ( $project_id ) {
			$where   .= ' AND project_id = %d';
			$values[] = (int) $project_id;
		}
		if ( $type ) {
			$where   .= ' AND cost_type = %s';
			$values[] = $type;
		}
		if ( $start ) {
			$where   .= ' AND date >= %s';
			$values[] = $start;
		}
		if ( $end ) {
			$where   .= ' AND date <= %s';
			$values[] = $end;
		}

		$query = "SELECT c.*, u.display_name as created_by_name
                  FROM {$this->costs_table} c
                  LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID
                  WHERE {$where} ORDER BY c.date DESC";

		$costs = ! empty( $values )
			? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
			: $wpdb->get_results( $query );

		return rest_ensure_response( $costs );
	}

	public function add_cost( $request ) {
		global $wpdb;

		$data = array(
			'project_id'       => (int) $request->get_param( 'project_id' ) ?: null,
			'cost_type'        => sanitize_text_field( $request->get_param( 'cost_type' ) ),
			'description'      => sanitize_text_field( $request->get_param( 'description' ) ),
			'amount'           => (float) $request->get_param( 'amount' ),
			'quantity'         => (float) $request->get_param( 'quantity' ) ?: 1,
			'unit_cost'        => (float) $request->get_param( 'unit_cost' ),
			'date'             => sanitize_text_field( $request->get_param( 'date' ) ),
			'vendor'           => sanitize_text_field( $request->get_param( 'vendor' ) ),
			'reference_number' => sanitize_text_field( $request->get_param( 'reference_number' ) ),
			'is_billable'      => (int) $request->get_param( 'is_billable' ),
			'is_reimbursable'  => (int) $request->get_param( 'is_reimbursable' ),
			'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'created_by'       => get_current_user_id(),
		);

		$wpdb->insert( $this->costs_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function get_forecast( $request ) {
		global $wpdb;

		$months = (int) $request->get_param( 'months' ) ?: 6;

		// Get pipeline projects
		$pipeline = $wpdb->get_results(
			"SELECT id, name, total_amount, probability, expected_close_date
             FROM {$wpdb->prefix}ict_projects
             WHERE status IN ('proposal', 'negotiation')
             ORDER BY expected_close_date"
		);

		// Get historical average margin
		$avg_margin = $wpdb->get_var(
			"SELECT AVG((total_amount - budget) / NULLIF(total_amount, 0)) * 100
             FROM {$wpdb->prefix}ict_projects WHERE status = 'completed'"
		) ?: 20;

		// Group by month
		$forecast = array();
		for ( $i = 0; $i < $months; $i++ ) {
			$month              = date( 'Y-m', strtotime( "+{$i} months" ) );
			$forecast[ $month ] = array(
				'period'           => $month,
				'expected_revenue' => 0,
				'weighted_revenue' => 0,
				'expected_profit'  => 0,
				'projects'         => array(),
			);
		}

		foreach ( $pipeline as $project ) {
			$close_month = date( 'Y-m', strtotime( $project->expected_close_date ) );
			if ( isset( $forecast[ $close_month ] ) ) {
				$weighted                                      = $project->total_amount * ( $project->probability / 100 );
				$forecast[ $close_month ]['expected_revenue'] += $project->total_amount;
				$forecast[ $close_month ]['weighted_revenue'] += $weighted;
				$forecast[ $close_month ]['expected_profit']  += $weighted * ( $avg_margin / 100 );
				$forecast[ $close_month ]['projects'][]        = array(
					'id'          => $project->id,
					'name'        => $project->name,
					'value'       => $project->total_amount,
					'probability' => $project->probability,
				);
			}
		}

		return rest_ensure_response(
			array(
				'forecast'       => array_values( $forecast ),
				'avg_margin'     => round( $avg_margin, 1 ),
				'total_pipeline' => array_sum( wp_list_pluck( $pipeline, 'total_amount' ) ),
			)
		);
	}

	private function get_project_costs( $project_id, $detailed = false ) {
		global $wpdb;

		// Direct costs
		$costs_by_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cost_type, SUM(amount) as total
             FROM {$this->costs_table}
             WHERE project_id = %d
             GROUP BY cost_type",
				$project_id
			),
			OBJECT_K
		);

		// Labor costs from time entries
		$labor = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(
                TIMESTAMPDIFF(MINUTE, t.start_time, t.end_time) / 60 *
                COALESCE(r.cost_rate, 50)
            ), 0)
             FROM {$wpdb->prefix}ict_time_entries t
             LEFT JOIN {$this->rates_table} r ON r.rate_type = 'user' AND r.reference_id = t.user_id
             WHERE t.project_id = %d",
				$project_id
			)
		);

		$result = array(
			'labor'    => (float) $labor,
			'material' => isset( $costs_by_type['material'] ) ? (float) $costs_by_type['material']->total : 0,
			'other'    => 0,
			'total'    => (float) $labor,
		);

		foreach ( $costs_by_type as $type => $data ) {
			if ( $type !== 'material' && $type !== 'labor' ) {
				$result['other'] += (float) $data->total;
			}
			$result['total'] += (float) $data->total;
		}

		if ( $detailed ) {
			$result['breakdown'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT cost_type, description, amount, date
                 FROM {$this->costs_table}
                 WHERE project_id = %d ORDER BY date DESC",
					$project_id
				)
			);
		}

		return $result;
	}

	private function get_period_dates( $period, $offset = 0 ) {
		switch ( $period ) {
			case 'week':
				$start = date( 'Y-m-d', strtotime( "monday this week {$offset} weeks" ) );
				$end   = date( 'Y-m-d', strtotime( "sunday this week {$offset} weeks" ) );
				break;
			case 'month':
				$start = date( 'Y-m-01', strtotime( "{$offset} months" ) );
				$end   = date( 'Y-m-t', strtotime( "{$offset} months" ) );
				break;
			case 'quarter':
				$quarter = ceil( date( 'n' ) / 3 ) + $offset;
				$year    = date( 'Y' );
				if ( $quarter < 1 ) {
					$quarter += 4;
					--$year;
				} elseif ( $quarter > 4 ) {
					$quarter -= 4;
					++$year;
				}
				$start_month = ( $quarter - 1 ) * 3 + 1;
				$start       = "{$year}-" . sprintf( '%02d', $start_month ) . '-01';
				$end         = date( 'Y-m-t', strtotime( "{$year}-" . sprintf( '%02d', $start_month + 2 ) . '-01' ) );
				break;
			case 'year':
				$year  = date( 'Y' ) + $offset;
				$start = "{$year}-01-01";
				$end   = "{$year}-12-31";
				break;
			default:
				$start = date( 'Y-m-01' );
				$end   = date( 'Y-m-t' );
		}

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}
}
