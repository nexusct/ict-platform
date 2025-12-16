<?php
/**
 * Extended Time Tracking Features
 *
 * Break time tracking, overtime calculations, and approval workflow.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Time_Tracking_Extended
 *
 * Handles break tracking, overtime, and approvals.
 */
class ICT_Time_Tracking_Extended {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Time_Tracking_Extended|null
	 */
	private static $instance = null;

	/**
	 * Breaks table name.
	 *
	 * @var string
	 */
	private $breaks_table;

	/**
	 * Overtime rules table.
	 *
	 * @var string
	 */
	private $overtime_rules_table;

	/**
	 * Approvals table.
	 *
	 * @var string
	 */
	private $approvals_table;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Time_Tracking_Extended
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->breaks_table         = $wpdb->prefix . 'ict_time_breaks';
		$this->overtime_rules_table = $wpdb->prefix . 'ict_overtime_rules';
		$this->approvals_table      = $wpdb->prefix . 'ict_time_approvals';
	}

	/**
	 * Initialize the feature.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );

		// Overtime calculation hooks
		add_action( 'ict_time_entry_created', array( $this, 'calculate_overtime' ), 10, 2 );
		add_action( 'ict_weekly_overtime_check', array( $this, 'check_weekly_overtime' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Break tracking
		register_rest_route(
			'ict/v1',
			'/time-entries/(?P<entry_id>\d+)/breaks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_breaks' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_break' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/breaks/(?P<id>\d+)/end',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'end_break' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/breaks/active',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active_break' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Overtime
		register_rest_route(
			'ict/v1',
			'/overtime/rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_overtime_rules' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_overtime_rule' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/overtime/rules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_overtime_rule' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_overtime_rule' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/overtime/calculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calculate_overtime_for_period' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/overtime/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overtime_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Time approval workflow
		register_rest_route(
			'ict/v1',
			'/time-approvals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pending_approvals' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/time-entries/(?P<entry_id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_for_approval' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/time-entries/(?P<entry_id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_time_entry' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/time-entries/(?P<entry_id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject_time_entry' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/timesheets/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_timesheet' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/timesheets/approve-batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_batch' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return is_user_logged_in();
	}

	/**
	 * Check manager permission.
	 *
	 * @return bool
	 */
	public function check_manager_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create database tables if needed.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Breaks table
		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->breaks_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            time_entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            break_type enum('lunch','short','other') DEFAULT 'short',
            start_time datetime NOT NULL,
            end_time datetime,
            duration_minutes int DEFAULT 0,
            is_paid tinyint(1) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY time_entry_id (time_entry_id),
            KEY user_id (user_id),
            KEY start_time (start_time)
        ) {$charset_collate};";

		// Overtime rules table
		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->overtime_rules_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            rule_type enum('daily','weekly','holiday') NOT NULL,
            threshold_hours decimal(5,2) NOT NULL,
            multiplier decimal(3,2) DEFAULT 1.5,
            applies_to varchar(255),
            is_active tinyint(1) DEFAULT 1,
            priority int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rule_type (rule_type),
            KEY is_active (is_active)
        ) {$charset_collate};";

		// Time approvals table
		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->approvals_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            time_entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            status enum('pending','approved','rejected') DEFAULT 'pending',
            submitted_at datetime NOT NULL,
            reviewed_by bigint(20) unsigned,
            reviewed_at datetime,
            comments text,
            rejection_reason text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY time_entry_id (time_entry_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY reviewed_by (reviewed_by)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );

		// Insert default overtime rules
		$this->insert_default_rules();
	}

	/**
	 * Insert default overtime rules.
	 */
	private function insert_default_rules() {
		global $wpdb;

		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->overtime_rules_table}" );
		if ( $existing > 0 ) {
			return;
		}

		$default_rules = array(
			array(
				'name'            => 'Daily Overtime',
				'description'     => 'Overtime after 8 hours in a day',
				'rule_type'       => 'daily',
				'threshold_hours' => 8,
				'multiplier'      => 1.5,
				'priority'        => 1,
			),
			array(
				'name'            => 'Daily Double Time',
				'description'     => 'Double time after 12 hours in a day',
				'rule_type'       => 'daily',
				'threshold_hours' => 12,
				'multiplier'      => 2.0,
				'priority'        => 2,
			),
			array(
				'name'            => 'Weekly Overtime',
				'description'     => 'Overtime after 40 hours in a week',
				'rule_type'       => 'weekly',
				'threshold_hours' => 40,
				'multiplier'      => 1.5,
				'priority'        => 1,
			),
			array(
				'name'            => 'Holiday Pay',
				'description'     => 'Holiday premium pay',
				'rule_type'       => 'holiday',
				'threshold_hours' => 0,
				'multiplier'      => 1.5,
				'priority'        => 3,
			),
		);

		foreach ( $default_rules as $rule ) {
			$wpdb->insert( $this->overtime_rules_table, $rule );
		}
	}

	// Break Tracking Methods

	/**
	 * Get breaks for a time entry.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_breaks( $request ) {
		global $wpdb;

		$entry_id = (int) $request->get_param( 'entry_id' );

		$breaks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->breaks_table}
                 WHERE time_entry_id = %d
                 ORDER BY start_time",
				$entry_id
			)
		);

		$total_break_time = 0;
		$paid_break_time  = 0;

		foreach ( $breaks as $break ) {
			$total_break_time += $break->duration_minutes;
			if ( $break->is_paid ) {
				$paid_break_time += $break->duration_minutes;
			}
		}

		return rest_ensure_response(
			array(
				'breaks'         => $breaks,
				'total_minutes'  => $total_break_time,
				'paid_minutes'   => $paid_break_time,
				'unpaid_minutes' => $total_break_time - $paid_break_time,
			)
		);
	}

	/**
	 * Start a break.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function start_break( $request ) {
		global $wpdb;

		$entry_id = (int) $request->get_param( 'entry_id' );
		$user_id  = get_current_user_id();

		// Check for active break
		$active = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->breaks_table}
                 WHERE user_id = %d AND end_time IS NULL",
				$user_id
			)
		);

		if ( $active ) {
			return new WP_Error( 'break_active', 'You already have an active break', array( 'status' => 400 ) );
		}

		$data = array(
			'time_entry_id' => $entry_id,
			'user_id'       => $user_id,
			'break_type'    => sanitize_text_field( $request->get_param( 'break_type' ) ?: 'short' ),
			'start_time'    => current_time( 'mysql' ),
			'is_paid'       => (int) $request->get_param( 'is_paid' ),
			'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);

		$wpdb->insert( $this->breaks_table, $data );
		$break_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'success'    => true,
				'id'         => $break_id,
				'start_time' => $data['start_time'],
				'message'    => 'Break started',
			)
		);
	}

	/**
	 * End a break.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function end_break( $request ) {
		global $wpdb;

		$break_id = (int) $request->get_param( 'id' );

		$break = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->breaks_table} WHERE id = %d", $break_id )
		);

		if ( ! $break ) {
			return new WP_Error( 'not_found', 'Break not found', array( 'status' => 404 ) );
		}

		if ( $break->end_time ) {
			return new WP_Error( 'already_ended', 'Break already ended', array( 'status' => 400 ) );
		}

		$end_time = current_time( 'mysql' );
		$duration = ( strtotime( $end_time ) - strtotime( $break->start_time ) ) / 60;

		$wpdb->update(
			$this->breaks_table,
			array(
				'end_time'         => $end_time,
				'duration_minutes' => round( $duration ),
			),
			array( 'id' => $break_id )
		);

		// Update time entry with break deduction
		$this->update_time_entry_breaks( $break->time_entry_id );

		return rest_ensure_response(
			array(
				'success'          => true,
				'end_time'         => $end_time,
				'duration_minutes' => round( $duration ),
				'message'          => 'Break ended',
			)
		);
	}

	/**
	 * Get active break for current user.
	 *
	 * @return WP_REST_Response
	 */
	public function get_active_break() {
		global $wpdb;

		$user_id = get_current_user_id();

		$break = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, te.project_id
                 FROM {$this->breaks_table} b
                 LEFT JOIN {$wpdb->prefix}ict_time_entries te ON b.time_entry_id = te.id
                 WHERE b.user_id = %d AND b.end_time IS NULL",
				$user_id
			)
		);

		if ( ! $break ) {
			return rest_ensure_response( array( 'active' => false ) );
		}

		$elapsed = ( current_time( 'timestamp' ) - strtotime( $break->start_time ) ) / 60;

		return rest_ensure_response(
			array(
				'active'          => true,
				'break'           => $break,
				'elapsed_minutes' => round( $elapsed ),
			)
		);
	}

	/**
	 * Update time entry with total break time.
	 *
	 * @param int $entry_id Time entry ID.
	 */
	private function update_time_entry_breaks( $entry_id ) {
		global $wpdb;

		$unpaid_breaks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(duration_minutes) FROM {$this->breaks_table}
                 WHERE time_entry_id = %d AND is_paid = 0 AND end_time IS NOT NULL",
				$entry_id
			)
		) ?: 0;

		$wpdb->update(
			$wpdb->prefix . 'ict_time_entries',
			array( 'break_minutes' => $unpaid_breaks ),
			array( 'id' => $entry_id )
		);
	}

	// Overtime Methods

	/**
	 * Get overtime rules.
	 *
	 * @return WP_REST_Response
	 */
	public function get_overtime_rules() {
		global $wpdb;

		$rules = $wpdb->get_results(
			"SELECT * FROM {$this->overtime_rules_table} ORDER BY rule_type, priority"
		);

		return rest_ensure_response( $rules );
	}

	/**
	 * Create overtime rule.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_overtime_rule( $request ) {
		global $wpdb;

		$data = array(
			'name'            => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'     => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'rule_type'       => sanitize_text_field( $request->get_param( 'rule_type' ) ),
			'threshold_hours' => (float) $request->get_param( 'threshold_hours' ),
			'multiplier'      => (float) $request->get_param( 'multiplier' ) ?: 1.5,
			'applies_to'      => sanitize_text_field( $request->get_param( 'applies_to' ) ),
			'priority'        => (int) $request->get_param( 'priority' ),
		);

		$wpdb->insert( $this->overtime_rules_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
				'message' => 'Overtime rule created',
			)
		);
	}

	/**
	 * Update overtime rule.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_overtime_rule( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$data   = array();
		$fields = array( 'name', 'description', 'rule_type', 'applies_to' );

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( $value );
			}
		}

		$numeric = array( 'threshold_hours', 'multiplier', 'priority', 'is_active' );
		foreach ( $numeric as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = is_float( $value + 0 ) ? (float) $value : (int) $value;
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->overtime_rules_table, $data, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Overtime rule updated',
			)
		);
	}

	/**
	 * Delete overtime rule.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_overtime_rule( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );
		$wpdb->delete( $this->overtime_rules_table, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Overtime rule deleted',
			)
		);
	}

	/**
	 * Calculate overtime for a time entry.
	 *
	 * @param int   $entry_id Entry ID.
	 * @param array $data     Entry data.
	 */
	public function calculate_overtime( $entry_id, $data ) {
		global $wpdb;

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_time_entries WHERE id = %d",
				$entry_id
			)
		);

		if ( ! $entry ) {
			return;
		}

		$hours   = $entry->hours;
		$date    = $entry->entry_date;
		$user_id = $entry->user_id;

		// Get daily overtime rules
		$daily_rules = $wpdb->get_results(
			"SELECT * FROM {$this->overtime_rules_table}
             WHERE rule_type = 'daily' AND is_active = 1
             ORDER BY threshold_hours DESC"
		);

		$regular_hours       = $hours;
		$overtime_hours      = 0;
		$overtime_multiplier = 1;

		foreach ( $daily_rules as $rule ) {
			if ( $hours > $rule->threshold_hours ) {
				$overtime_hours      = $hours - $rule->threshold_hours;
				$regular_hours       = $rule->threshold_hours;
				$overtime_multiplier = $rule->multiplier;
				break;
			}
		}

		// Update entry with overtime info
		$wpdb->update(
			$wpdb->prefix . 'ict_time_entries',
			array(
				'regular_hours'       => $regular_hours,
				'overtime_hours'      => $overtime_hours,
				'overtime_multiplier' => $overtime_multiplier,
			),
			array( 'id' => $entry_id )
		);

		// Check if overtime threshold exceeded
		if ( $overtime_hours > 0 ) {
			do_action( 'ict_overtime_logged', $entry_id, $user_id, $overtime_hours, $overtime_multiplier );
		}
	}

	/**
	 * Calculate overtime for a period.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function calculate_overtime_for_period( $request ) {
		global $wpdb;

		$user_id    = (int) $request->get_param( 'user_id' ) ?: get_current_user_id();
		$start_date = sanitize_text_field( $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( $request->get_param( 'end_date' ) );

		if ( ! $start_date || ! $end_date ) {
			return new WP_Error( 'missing_dates', 'Start and end dates required', array( 'status' => 400 ) );
		}

		// Get entries for period
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_time_entries
                 WHERE user_id = %d
                 AND entry_date BETWEEN %s AND %s
                 ORDER BY entry_date",
				$user_id,
				$start_date,
				$end_date
			)
		);

		// Group by date
		$daily_hours = array();
		foreach ( $entries as $entry ) {
			$date = $entry->entry_date;
			if ( ! isset( $daily_hours[ $date ] ) ) {
				$daily_hours[ $date ] = 0;
			}
			$daily_hours[ $date ] += $entry->hours;
		}

		// Calculate weekly totals
		$weekly_hours = array();
		foreach ( $daily_hours as $date => $hours ) {
			$week = date( 'W', strtotime( $date ) );
			if ( ! isset( $weekly_hours[ $week ] ) ) {
				$weekly_hours[ $week ] = 0;
			}
			$weekly_hours[ $week ] += $hours;
		}

		// Get rules
		$daily_rule = $wpdb->get_row(
			"SELECT * FROM {$this->overtime_rules_table}
             WHERE rule_type = 'daily' AND is_active = 1
             ORDER BY threshold_hours ASC LIMIT 1"
		);

		$weekly_rule = $wpdb->get_row(
			"SELECT * FROM {$this->overtime_rules_table}
             WHERE rule_type = 'weekly' AND is_active = 1
             ORDER BY threshold_hours ASC LIMIT 1"
		);

		$total_regular   = 0;
		$total_daily_ot  = 0;
		$total_weekly_ot = 0;

		$daily_threshold  = $daily_rule ? $daily_rule->threshold_hours : 8;
		$weekly_threshold = $weekly_rule ? $weekly_rule->threshold_hours : 40;

		// Calculate daily overtime
		foreach ( $daily_hours as $date => $hours ) {
			if ( $hours > $daily_threshold ) {
				$total_daily_ot += $hours - $daily_threshold;
				$total_regular  += $daily_threshold;
			} else {
				$total_regular += $hours;
			}
		}

		// Calculate weekly overtime (additional)
		foreach ( $weekly_hours as $week => $hours ) {
			$weekly_regular = min( $hours, $weekly_threshold );
			if ( $hours > $weekly_threshold && $total_regular > $weekly_threshold ) {
				$additional_ot    = min( $total_regular - $weekly_threshold, $hours - $weekly_threshold );
				$total_weekly_ot += $additional_ot;
				$total_regular   -= $additional_ot;
			}
		}

		return rest_ensure_response(
			array(
				'period'            => array(
					'start' => $start_date,
					'end'   => $end_date,
				),
				'total_hours'       => array_sum( $daily_hours ),
				'regular_hours'     => $total_regular,
				'daily_overtime'    => $total_daily_ot,
				'weekly_overtime'   => $total_weekly_ot,
				'total_overtime'    => $total_daily_ot + $total_weekly_ot,
				'daily_breakdown'   => $daily_hours,
				'weekly_breakdown'  => $weekly_hours,
				'daily_threshold'   => $daily_threshold,
				'weekly_threshold'  => $weekly_threshold,
				'daily_multiplier'  => $daily_rule ? $daily_rule->multiplier : 1.5,
				'weekly_multiplier' => $weekly_rule ? $weekly_rule->multiplier : 1.5,
			)
		);
	}

	/**
	 * Get overtime summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_overtime_summary( $request ) {
		global $wpdb;

		$period  = $request->get_param( 'period' ) ?: 'week';
		$user_id = (int) $request->get_param( 'user_id' );

		switch ( $period ) {
			case 'week':
				$start_date = date( 'Y-m-d', strtotime( 'monday this week' ) );
				$end_date   = date( 'Y-m-d', strtotime( 'sunday this week' ) );
				break;
			case 'month':
				$start_date = date( 'Y-m-01' );
				$end_date   = date( 'Y-m-t' );
				break;
			case 'year':
				$start_date = date( 'Y-01-01' );
				$end_date   = date( 'Y-12-31' );
				break;
			default:
				$start_date = date( 'Y-m-d', strtotime( 'monday this week' ) );
				$end_date   = date( 'Y-m-d', strtotime( 'sunday this week' ) );
		}

		$where  = 'entry_date BETWEEN %s AND %s';
		$values = array( $start_date, $end_date );

		if ( $user_id ) {
			$where   .= ' AND user_id = %d';
			$values[] = $user_id;
		}

		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                    SUM(hours) as total_hours,
                    SUM(COALESCE(regular_hours, hours)) as regular_hours,
                    SUM(COALESCE(overtime_hours, 0)) as overtime_hours,
                    COUNT(DISTINCT user_id) as employees,
                    COUNT(*) as entries
                 FROM {$wpdb->prefix}ict_time_entries
                 WHERE {$where}",
				$values
			)
		);

		// Top overtime users
		$top_ot = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, u.display_name,
                        SUM(COALESCE(overtime_hours, 0)) as ot_hours
                 FROM {$wpdb->prefix}ict_time_entries te
                 LEFT JOIN {$wpdb->users} u ON te.user_id = u.ID
                 WHERE entry_date BETWEEN %s AND %s
                 GROUP BY user_id
                 HAVING ot_hours > 0
                 ORDER BY ot_hours DESC
                 LIMIT 10",
				$start_date,
				$end_date
			)
		);

		return rest_ensure_response(
			array(
				'period'         => array(
					'start' => $start_date,
					'end'   => $end_date,
					'type'  => $period,
				),
				'total_hours'    => (float) $summary->total_hours,
				'regular_hours'  => (float) $summary->regular_hours,
				'overtime_hours' => (float) $summary->overtime_hours,
				'employees'      => (int) $summary->employees,
				'entries'        => (int) $summary->entries,
				'top_overtime'   => $top_ot,
			)
		);
	}

	// Approval Workflow Methods

	/**
	 * Get pending approvals.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_pending_approvals( $request ) {
		global $wpdb;

		$status   = $request->get_param( 'status' ) ?: 'pending';
		$user_id  = (int) $request->get_param( 'user_id' );
		$page     = (int) $request->get_param( 'page' ) ?: 1;
		$per_page = (int) $request->get_param( 'per_page' ) ?: 20;
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( 'a.status = %s' );
		$values = array( $status );

		if ( $user_id ) {
			$where[]  = 'a.user_id = %d';
			$values[] = $user_id;
		}

		$where_clause = implode( ' AND ', $where );

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->approvals_table} a WHERE {$where_clause}",
				$values
			)
		);

		$values[] = $per_page;
		$values[] = $offset;

		$approvals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, te.*, u.display_name as employee_name,
                        p.name as project_name, r.display_name as reviewer_name
                 FROM {$this->approvals_table} a
                 LEFT JOIN {$wpdb->prefix}ict_time_entries te ON a.time_entry_id = te.id
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON te.project_id = p.id
                 LEFT JOIN {$wpdb->users} r ON a.reviewed_by = r.ID
                 WHERE {$where_clause}
                 ORDER BY a.submitted_at DESC
                 LIMIT %d OFFSET %d",
				$values
			)
		);

		return rest_ensure_response(
			array(
				'approvals' => $approvals,
				'total'     => (int) $total,
				'pages'     => ceil( $total / $per_page ),
				'page'      => $page,
			)
		);
	}

	/**
	 * Submit time entry for approval.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function submit_for_approval( $request ) {
		global $wpdb;

		$entry_id = (int) $request->get_param( 'entry_id' );

		// Check if already submitted
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->approvals_table} WHERE time_entry_id = %d",
				$entry_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'already_submitted', 'Already submitted for approval', array( 'status' => 400 ) );
		}

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_time_entries WHERE id = %d",
				$entry_id
			)
		);

		if ( ! $entry ) {
			return new WP_Error( 'not_found', 'Time entry not found', array( 'status' => 404 ) );
		}

		$data = array(
			'time_entry_id' => $entry_id,
			'user_id'       => $entry->user_id,
			'status'        => 'pending',
			'submitted_at'  => current_time( 'mysql' ),
			'comments'      => sanitize_textarea_field( $request->get_param( 'comments' ) ),
		);

		$wpdb->insert( $this->approvals_table, $data );

		// Update entry status
		$wpdb->update(
			$wpdb->prefix . 'ict_time_entries',
			array( 'approval_status' => 'pending' ),
			array( 'id' => $entry_id )
		);

		do_action( 'ict_time_submitted_for_approval', $entry_id, $entry->user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Submitted for approval',
			)
		);
	}

	/**
	 * Approve time entry.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function approve_time_entry( $request ) {
		global $wpdb;

		$entry_id = (int) $request->get_param( 'entry_id' );

		$approval = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->approvals_table} WHERE time_entry_id = %d",
				$entry_id
			)
		);

		if ( ! $approval ) {
			return new WP_Error( 'not_found', 'Approval record not found', array( 'status' => 404 ) );
		}

		$wpdb->update(
			$this->approvals_table,
			array(
				'status'      => 'approved',
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql' ),
				'comments'    => sanitize_textarea_field( $request->get_param( 'comments' ) ),
			),
			array( 'id' => $approval->id )
		);

		$wpdb->update(
			$wpdb->prefix . 'ict_time_entries',
			array( 'approval_status' => 'approved' ),
			array( 'id' => $entry_id )
		);

		do_action( 'ict_time_approved', $entry_id, $approval->user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Time entry approved',
			)
		);
	}

	/**
	 * Reject time entry.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reject_time_entry( $request ) {
		global $wpdb;

		$entry_id = (int) $request->get_param( 'entry_id' );
		$reason   = sanitize_textarea_field( $request->get_param( 'reason' ) );

		if ( empty( $reason ) ) {
			return new WP_Error( 'reason_required', 'Rejection reason is required', array( 'status' => 400 ) );
		}

		$approval = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->approvals_table} WHERE time_entry_id = %d",
				$entry_id
			)
		);

		if ( ! $approval ) {
			return new WP_Error( 'not_found', 'Approval record not found', array( 'status' => 404 ) );
		}

		$wpdb->update(
			$this->approvals_table,
			array(
				'status'           => 'rejected',
				'reviewed_by'      => get_current_user_id(),
				'reviewed_at'      => current_time( 'mysql' ),
				'rejection_reason' => $reason,
			),
			array( 'id' => $approval->id )
		);

		$wpdb->update(
			$wpdb->prefix . 'ict_time_entries',
			array( 'approval_status' => 'rejected' ),
			array( 'id' => $entry_id )
		);

		do_action( 'ict_time_rejected', $entry_id, $approval->user_id, $reason );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Time entry rejected',
			)
		);
	}

	/**
	 * Submit entire timesheet for approval.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function submit_timesheet( $request ) {
		global $wpdb;

		$user_id    = get_current_user_id();
		$start_date = sanitize_text_field( $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( $request->get_param( 'end_date' ) );

		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ict_time_entries
                 WHERE user_id = %d
                 AND entry_date BETWEEN %s AND %s
                 AND (approval_status IS NULL OR approval_status = 'draft')",
				$user_id,
				$start_date,
				$end_date
			)
		);

		$submitted = 0;
		foreach ( $entries as $entry ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->approvals_table} WHERE time_entry_id = %d",
					$entry->id
				)
			);

			if ( ! $existing ) {
				$wpdb->insert(
					$this->approvals_table,
					array(
						'time_entry_id' => $entry->id,
						'user_id'       => $user_id,
						'status'        => 'pending',
						'submitted_at'  => current_time( 'mysql' ),
					)
				);

				$wpdb->update(
					$wpdb->prefix . 'ict_time_entries',
					array( 'approval_status' => 'pending' ),
					array( 'id' => $entry->id )
				);

				++$submitted;
			}
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'submitted' => $submitted,
				'message'   => sprintf( '%d time entries submitted for approval', $submitted ),
			)
		);
	}

	/**
	 * Approve batch of time entries.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function approve_batch( $request ) {
		global $wpdb;

		$entry_ids = $request->get_param( 'entry_ids' );

		if ( ! is_array( $entry_ids ) || empty( $entry_ids ) ) {
			return new WP_Error( 'no_entries', 'No entries specified', array( 'status' => 400 ) );
		}

		$approved    = 0;
		$reviewer_id = get_current_user_id();
		$now         = current_time( 'mysql' );

		foreach ( $entry_ids as $entry_id ) {
			$entry_id = (int) $entry_id;

			$wpdb->update(
				$this->approvals_table,
				array(
					'status'      => 'approved',
					'reviewed_by' => $reviewer_id,
					'reviewed_at' => $now,
				),
				array(
					'time_entry_id' => $entry_id,
					'status'        => 'pending',
				)
			);

			if ( $wpdb->rows_affected > 0 ) {
				$wpdb->update(
					$wpdb->prefix . 'ict_time_entries',
					array( 'approval_status' => 'approved' ),
					array( 'id' => $entry_id )
				);
				++$approved;
			}
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'approved' => $approved,
				'message'  => sprintf( '%d time entries approved', $approved ),
			)
		);
	}

	/**
	 * Check weekly overtime for alerts.
	 */
	public function check_weekly_overtime() {
		global $wpdb;

		$weekly_rule = $wpdb->get_row(
			"SELECT * FROM {$this->overtime_rules_table}
             WHERE rule_type = 'weekly' AND is_active = 1
             ORDER BY threshold_hours ASC LIMIT 1"
		);

		if ( ! $weekly_rule ) {
			return;
		}

		$threshold         = $weekly_rule->threshold_hours;
		$warning_threshold = $threshold * 0.9; // 90% warning

		$start_of_week = date( 'Y-m-d', strtotime( 'monday this week' ) );
		$today         = date( 'Y-m-d' );

		$users_approaching = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, SUM(hours) as total_hours
                 FROM {$wpdb->prefix}ict_time_entries
                 WHERE entry_date BETWEEN %s AND %s
                 GROUP BY user_id
                 HAVING total_hours >= %f",
				$start_of_week,
				$today,
				$warning_threshold
			)
		);

		foreach ( $users_approaching as $user ) {
			if ( $user->total_hours >= $threshold ) {
				do_action( 'ict_weekly_overtime_exceeded', $user->user_id, $user->total_hours, $threshold );
			} else {
				do_action( 'ict_weekly_overtime_approaching', $user->user_id, $user->total_hours, $threshold );
			}
		}
	}
}
