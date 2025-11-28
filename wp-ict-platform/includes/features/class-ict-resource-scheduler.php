<?php
/**
 * Resource Scheduler & Availability Management
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Resource_Scheduler {

	private static $instance = null;
	private $schedules_table;
	private $availability_table;
	private $shifts_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->schedules_table    = $wpdb->prefix . 'ict_resource_schedules';
		$this->availability_table = $wpdb->prefix . 'ict_user_availability';
		$this->shifts_table       = $wpdb->prefix . 'ict_shifts';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	public function register_routes() {
		// Resource scheduling
		register_rest_route(
			'ict/v1',
			'/schedules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_schedules' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_schedule' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/schedules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_schedule' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_schedule' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/schedules/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar_events' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/schedules/conflicts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_conflicts' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Availability
		register_rest_route(
			'ict/v1',
			'/availability',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_availability' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_availability' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/availability/users/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_availability' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/availability/find',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'find_available_resources' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Shifts
		register_rest_route(
			'ict/v1',
			'/shifts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_shifts' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_shift' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/shifts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_shift' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_shift' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/shifts/assign',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assign_shift' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		// Workload
		register_rest_route(
			'ict/v1',
			'/schedules/workload',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_workload' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'edit_ict_projects' ) || current_user_can( 'manage_options' );
	}

	public function check_edit_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->schedules_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned,
            task_id bigint(20) unsigned,
            title varchar(255) NOT NULL,
            description text,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            all_day tinyint(1) DEFAULT 0,
            location varchar(255),
            status enum('scheduled','confirmed','in_progress','completed','cancelled') DEFAULT 'scheduled',
            priority enum('low','medium','high') DEFAULT 'medium',
            color varchar(20),
            is_recurring tinyint(1) DEFAULT 0,
            recurrence_rule varchar(255),
            parent_id bigint(20) unsigned,
            reminder_minutes int,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY project_id (project_id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY status (status)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->availability_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint(1),
            specific_date date,
            start_time time NOT NULL,
            end_time time NOT NULL,
            availability_type enum('available','unavailable','limited') DEFAULT 'available',
            reason varchar(255),
            is_recurring tinyint(1) DEFAULT 1,
            valid_from date,
            valid_until date,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY day_of_week (day_of_week),
            KEY specific_date (specific_date)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->shifts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            break_duration_minutes int DEFAULT 0,
            color varchar(20),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

		$sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ict_shift_assignments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            shift_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assignment_date date NOT NULL,
            status enum('assigned','confirmed','completed','absent') DEFAULT 'assigned',
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY shift_user_date (shift_id, user_id, assignment_date),
            KEY user_id (user_id),
            KEY assignment_date (assignment_date)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );
	}

	public function get_schedules( $request ) {
		global $wpdb;

		$user_id    = (int) $request->get_param( 'user_id' );
		$project_id = (int) $request->get_param( 'project_id' );
		$start      = $request->get_param( 'start' );
		$end        = $request->get_param( 'end' );

		$where  = array( '1=1' );
		$values = array();

		if ( $user_id ) {
			$where[]  = 's.user_id = %d';
			$values[] = $user_id;
		}
		if ( $project_id ) {
			$where[]  = 's.project_id = %d';
			$values[] = $project_id;
		}
		if ( $start ) {
			$where[]  = 's.end_datetime >= %s';
			$values[] = $start;
		}
		if ( $end ) {
			$where[]  = 's.start_datetime <= %s';
			$values[] = $end;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT s.*, u.display_name as user_name, p.name as project_name
                  FROM {$this->schedules_table} s
                  LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}ict_projects p ON s.project_id = p.id
                  WHERE {$where_clause}
                  ORDER BY s.start_datetime";

		$schedules = ! empty( $values )
			? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
			: $wpdb->get_results( $query );

		return rest_ensure_response( $schedules );
	}

	public function create_schedule( $request ) {
		global $wpdb;

		$data = array(
			'user_id'          => (int) $request->get_param( 'user_id' ),
			'project_id'       => (int) $request->get_param( 'project_id' ) ?: null,
			'task_id'          => (int) $request->get_param( 'task_id' ) ?: null,
			'title'            => sanitize_text_field( $request->get_param( 'title' ) ),
			'description'      => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'start_datetime'   => sanitize_text_field( $request->get_param( 'start_datetime' ) ),
			'end_datetime'     => sanitize_text_field( $request->get_param( 'end_datetime' ) ),
			'all_day'          => (int) $request->get_param( 'all_day' ),
			'location'         => sanitize_text_field( $request->get_param( 'location' ) ),
			'status'           => sanitize_text_field( $request->get_param( 'status' ) ?: 'scheduled' ),
			'priority'         => sanitize_text_field( $request->get_param( 'priority' ) ?: 'medium' ),
			'color'            => sanitize_hex_color( $request->get_param( 'color' ) ),
			'is_recurring'     => (int) $request->get_param( 'is_recurring' ),
			'recurrence_rule'  => sanitize_text_field( $request->get_param( 'recurrence_rule' ) ),
			'reminder_minutes' => (int) $request->get_param( 'reminder_minutes' ) ?: null,
			'created_by'       => get_current_user_id(),
		);

		// Check for conflicts
		$conflicts = $this->find_conflicts( $data['user_id'], $data['start_datetime'], $data['end_datetime'] );
		if ( ! empty( $conflicts ) && ! $request->get_param( 'ignore_conflicts' ) ) {
			return rest_ensure_response(
				array(
					'success'   => false,
					'conflicts' => $conflicts,
					'message'   => 'Schedule conflicts detected',
				)
			);
		}

		$wpdb->insert( $this->schedules_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function update_schedule( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$data        = array();
		$text_fields = array( 'title', 'description', 'location', 'status', 'priority', 'recurrence_rule' );
		foreach ( $text_fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = sanitize_text_field( $v );
			}
		}

		$datetime_fields = array( 'start_datetime', 'end_datetime' );
		foreach ( $datetime_fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = sanitize_text_field( $v );
			}
		}

		$int_fields = array( 'user_id', 'project_id', 'task_id', 'all_day', 'is_recurring', 'reminder_minutes' );
		foreach ( $int_fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = (int) $v;
			}
		}

		$color = $request->get_param( 'color' );
		if ( null !== $color ) {
			$data['color'] = sanitize_hex_color( $color );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->schedules_table, $data, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_schedule( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		$wpdb->delete( $this->schedules_table, array( 'id' => $id ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_calendar_events( $request ) {
		global $wpdb;

		$start    = $request->get_param( 'start' ) ?: date( 'Y-m-01' );
		$end      = $request->get_param( 'end' ) ?: date( 'Y-m-t' );
		$user_ids = $request->get_param( 'user_ids' );

		$where  = array( 's.start_datetime <= %s', 's.end_datetime >= %s' );
		$values = array( $end, $start );

		if ( $user_ids && is_array( $user_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
			$where[]      = "s.user_id IN ({$placeholders})";
			$values       = array_merge( $values, array_map( 'intval', $user_ids ) );
		}

		$where_clause = implode( ' AND ', $where );

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.title, s.start_datetime as start, s.end_datetime as end,
                    s.all_day as allDay, s.color, s.status, s.user_id,
                    u.display_name as resourceTitle, p.name as project_name
             FROM {$this->schedules_table} s
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             LEFT JOIN {$wpdb->prefix}ict_projects p ON s.project_id = p.id
             WHERE {$where_clause}
             ORDER BY s.start_datetime",
				$values
			)
		);

		return rest_ensure_response( $events );
	}

	public function check_conflicts( $request ) {
		$user_id    = (int) $request->get_param( 'user_id' );
		$start      = sanitize_text_field( $request->get_param( 'start' ) );
		$end        = sanitize_text_field( $request->get_param( 'end' ) );
		$exclude_id = (int) $request->get_param( 'exclude_id' );

		$conflicts = $this->find_conflicts( $user_id, $start, $end, $exclude_id );

		return rest_ensure_response(
			array(
				'has_conflicts' => ! empty( $conflicts ),
				'conflicts'     => $conflicts,
			)
		);
	}

	private function find_conflicts( $user_id, $start, $end, $exclude_id = 0 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->schedules_table}
             WHERE user_id = %d
             AND status NOT IN ('cancelled', 'completed')
             AND ((start_datetime < %s AND end_datetime > %s)
                  OR (start_datetime >= %s AND start_datetime < %s))
             AND id != %d",
			$user_id,
			$end,
			$start,
			$start,
			$end,
			$exclude_id
		);

		return $wpdb->get_results( $query );
	}

	public function get_availability( $request ) {
		global $wpdb;
		$user_id = (int) $request->get_param( 'user_id' ) ?: get_current_user_id();

		$availability = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->availability_table}
             WHERE user_id = %d
             ORDER BY day_of_week, specific_date, start_time",
				$user_id
			)
		);

		return rest_ensure_response( $availability );
	}

	public function set_availability( $request ) {
		global $wpdb;
		$user_id = (int) $request->get_param( 'user_id' ) ?: get_current_user_id();

		$data = array(
			'user_id'           => $user_id,
			'day_of_week'       => $request->get_param( 'day_of_week' ),
			'specific_date'     => $request->get_param( 'specific_date' ),
			'start_time'        => sanitize_text_field( $request->get_param( 'start_time' ) ),
			'end_time'          => sanitize_text_field( $request->get_param( 'end_time' ) ),
			'availability_type' => sanitize_text_field( $request->get_param( 'availability_type' ) ?: 'available' ),
			'reason'            => sanitize_text_field( $request->get_param( 'reason' ) ),
			'is_recurring'      => (int) $request->get_param( 'is_recurring' ),
			'valid_from'        => $request->get_param( 'valid_from' ),
			'valid_until'       => $request->get_param( 'valid_until' ),
		);

		$wpdb->insert( $this->availability_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function get_user_availability( $request ) {
		global $wpdb;
		$user_id     = (int) $request->get_param( 'user_id' );
		$date        = $request->get_param( 'date' ) ?: date( 'Y-m-d' );
		$day_of_week = date( 'w', strtotime( $date ) );

		$availability = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->availability_table}
             WHERE user_id = %d
             AND (specific_date = %s OR (is_recurring = 1 AND day_of_week = %d))
             AND (valid_from IS NULL OR valid_from <= %s)
             AND (valid_until IS NULL OR valid_until >= %s)
             ORDER BY specific_date DESC, start_time",
				$user_id,
				$date,
				$day_of_week,
				$date,
				$date
			)
		);

		return rest_ensure_response( $availability );
	}

	public function find_available_resources( $request ) {
		global $wpdb;

		$start  = sanitize_text_field( $request->get_param( 'start' ) );
		$end    = sanitize_text_field( $request->get_param( 'end' ) );
		$skills = $request->get_param( 'skills' );

		// Get all technicians
		$users = get_users( array( 'role__in' => array( 'ict_technician', 'ict_project_manager' ) ) );

		$available = array();
		foreach ( $users as $user ) {
			$conflicts = $this->find_conflicts( $user->ID, $start, $end );
			if ( empty( $conflicts ) ) {
				$available[] = array(
					'id'           => $user->ID,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
				);
			}
		}

		return rest_ensure_response( $available );
	}

	public function get_shifts( $request ) {
		global $wpdb;
		$shifts = $wpdb->get_results( "SELECT * FROM {$this->shifts_table} WHERE is_active = 1 ORDER BY start_time" );
		return rest_ensure_response( $shifts );
	}

	public function create_shift( $request ) {
		global $wpdb;

		$data = array(
			'name'                   => sanitize_text_field( $request->get_param( 'name' ) ),
			'start_time'             => sanitize_text_field( $request->get_param( 'start_time' ) ),
			'end_time'               => sanitize_text_field( $request->get_param( 'end_time' ) ),
			'break_duration_minutes' => (int) $request->get_param( 'break_duration_minutes' ),
			'color'                  => sanitize_hex_color( $request->get_param( 'color' ) ),
		);

		$wpdb->insert( $this->shifts_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function update_shift( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$data   = array();
		$fields = array( 'name', 'start_time', 'end_time' );
		foreach ( $fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = sanitize_text_field( $v );
			}
		}

		$int_fields = array( 'break_duration_minutes', 'is_active' );
		foreach ( $int_fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = (int) $v;
			}
		}

		$color = $request->get_param( 'color' );
		if ( null !== $color ) {
			$data['color'] = sanitize_hex_color( $color );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->shifts_table, $data, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_shift( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		$wpdb->update( $this->shifts_table, array( 'is_active' => 0 ), array( 'id' => $id ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function assign_shift( $request ) {
		global $wpdb;

		$data = array(
			'shift_id'        => (int) $request->get_param( 'shift_id' ),
			'user_id'         => (int) $request->get_param( 'user_id' ),
			'assignment_date' => sanitize_text_field( $request->get_param( 'date' ) ),
			'notes'           => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'created_by'      => get_current_user_id(),
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ict_shift_assignments
             WHERE shift_id = %d AND user_id = %d AND assignment_date = %s",
				$data['shift_id'],
				$data['user_id'],
				$data['assignment_date']
			)
		);

		if ( $existing ) {
			return new WP_Error( 'exists', 'Assignment already exists', array( 'status' => 400 ) );
		}

		$wpdb->insert( $wpdb->prefix . 'ict_shift_assignments', $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function get_workload( $request ) {
		global $wpdb;

		$start = $request->get_param( 'start' ) ?: date( 'Y-m-d', strtotime( 'monday this week' ) );
		$end   = $request->get_param( 'end' ) ?: date( 'Y-m-d', strtotime( 'sunday this week' ) );

		$workload = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.user_id, u.display_name,
                    COUNT(*) as total_assignments,
                    SUM(TIMESTAMPDIFF(HOUR, s.start_datetime, s.end_datetime)) as total_hours
             FROM {$this->schedules_table} s
             JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE DATE(s.start_datetime) BETWEEN %s AND %s
             AND s.status NOT IN ('cancelled')
             GROUP BY s.user_id
             ORDER BY total_hours DESC",
				$start,
				$end
			)
		);

		return rest_ensure_response( $workload );
	}
}
