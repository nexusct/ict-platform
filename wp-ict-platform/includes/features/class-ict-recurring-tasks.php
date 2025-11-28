<?php
/**
 * Recurring Tasks System
 *
 * Create and manage recurring tasks with flexible schedules.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Recurring_Tasks {

	private static $instance = null;
	private $templates_table;
	private $instances_table;
	private $history_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->templates_table = $wpdb->prefix . 'ict_recurring_templates';
		$this->instances_table = $wpdb->prefix . 'ict_recurring_instances';
		$this->history_table   = $wpdb->prefix . 'ict_recurring_history';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'ict_process_recurring_tasks', array( $this, 'process_due_tasks' ) );

		if ( ! wp_next_scheduled( 'ict_process_recurring_tasks' ) ) {
			wp_schedule_event( time(), 'hourly', 'ict_process_recurring_tasks' );
		}
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/recurring-tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_template' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_template' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/(?P<id>\d+)/pause',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pause_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/(?P<id>\d+)/resume',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resume_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/(?P<id>\d+)/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_instance' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/(?P<id>\d+)/instances',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_instances' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/(?P<id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/recurring-tasks/upcoming',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_upcoming' ),
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

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->templates_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            task_type enum('task','maintenance','inspection','report') DEFAULT 'task',
            frequency_type enum('daily','weekly','monthly','yearly','custom') NOT NULL,
            frequency_value int DEFAULT 1,
            days_of_week varchar(20),
            day_of_month int,
            month_of_year int,
            cron_expression varchar(100),
            start_date date NOT NULL,
            end_date date,
            next_occurrence datetime,
            default_assignee bigint(20) unsigned,
            default_project_id bigint(20) unsigned,
            estimated_hours decimal(5,2),
            priority enum('low','medium','high','urgent') DEFAULT 'medium',
            checklist text,
            attachments text,
            notify_before_hours int DEFAULT 24,
            auto_assign tinyint(1) DEFAULT 1,
            status enum('active','paused','completed','expired') DEFAULT 'active',
            occurrences_count int DEFAULT 0,
            max_occurrences int,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY next_occurrence (next_occurrence),
            KEY default_project_id (default_project_id)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->instances_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            scheduled_date datetime NOT NULL,
            actual_start datetime,
            actual_end datetime,
            assignee_id bigint(20) unsigned,
            project_id bigint(20) unsigned,
            task_id bigint(20) unsigned,
            status enum('scheduled','in_progress','completed','skipped','overdue') DEFAULT 'scheduled',
            notes text,
            completed_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY scheduled_date (scheduled_date),
            KEY status (status),
            KEY assignee_id (assignee_id)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->history_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            instance_id bigint(20) unsigned,
            action varchar(50) NOT NULL,
            details text,
            performed_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	public function get_templates( $request ) {
		global $wpdb;

		$status = $request->get_param( 'status' );
		$type   = $request->get_param( 'task_type' );

		$where  = array( '1=1' );
		$values = array();

		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}
		if ( $type ) {
			$where[]  = 'task_type = %s';
			$values[] = $type;
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT * FROM {$this->templates_table} WHERE {$where_clause} ORDER BY next_occurrence";

		$templates = ! empty( $values )
			? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
			: $wpdb->get_results( $query );

		foreach ( $templates as $template ) {
			$template->checklist   = json_decode( $template->checklist, true );
			$template->attachments = json_decode( $template->attachments, true );
		}

		return rest_ensure_response( $templates );
	}

	public function get_template( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->templates_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$template->checklist   = json_decode( $template->checklist, true );
		$template->attachments = json_decode( $template->attachments, true );

		$template->upcoming_instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->instances_table}
             WHERE template_id = %d AND status IN ('scheduled', 'in_progress')
             ORDER BY scheduled_date LIMIT 10",
				$id
			)
		);

		return rest_ensure_response( $template );
	}

	public function create_template( $request ) {
		global $wpdb;

		$data = array(
			'name'                => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'         => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'task_type'           => sanitize_text_field( $request->get_param( 'task_type' ) ?: 'task' ),
			'frequency_type'      => sanitize_text_field( $request->get_param( 'frequency_type' ) ),
			'frequency_value'     => (int) $request->get_param( 'frequency_value' ) ?: 1,
			'days_of_week'        => sanitize_text_field( $request->get_param( 'days_of_week' ) ),
			'day_of_month'        => (int) $request->get_param( 'day_of_month' ),
			'month_of_year'       => (int) $request->get_param( 'month_of_year' ),
			'cron_expression'     => sanitize_text_field( $request->get_param( 'cron_expression' ) ),
			'start_date'          => sanitize_text_field( $request->get_param( 'start_date' ) ),
			'end_date'            => sanitize_text_field( $request->get_param( 'end_date' ) ) ?: null,
			'default_assignee'    => (int) $request->get_param( 'default_assignee' ) ?: null,
			'default_project_id'  => (int) $request->get_param( 'default_project_id' ) ?: null,
			'estimated_hours'     => (float) $request->get_param( 'estimated_hours' ),
			'priority'            => sanitize_text_field( $request->get_param( 'priority' ) ?: 'medium' ),
			'notify_before_hours' => (int) $request->get_param( 'notify_before_hours' ) ?: 24,
			'auto_assign'         => (int) $request->get_param( 'auto_assign' ),
			'max_occurrences'     => (int) $request->get_param( 'max_occurrences' ) ?: null,
			'created_by'          => get_current_user_id(),
		);

		$checklist = $request->get_param( 'checklist' );
		if ( $checklist ) {
			$data['checklist'] = wp_json_encode( $checklist );
		}

		$attachments = $request->get_param( 'attachments' );
		if ( $attachments ) {
			$data['attachments'] = wp_json_encode( $attachments );
		}

		$data['next_occurrence'] = $this->calculate_next_occurrence( $data );

		$wpdb->insert( $this->templates_table, $data );
		$template_id = $wpdb->insert_id;

		$this->log_history( $template_id, null, 'created', 'Recurring task template created' );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $template_id,
			)
		);
	}

	public function update_template( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$fields = array(
			'name',
			'description',
			'task_type',
			'frequency_type',
			'frequency_value',
			'days_of_week',
			'day_of_month',
			'month_of_year',
			'cron_expression',
			'start_date',
			'end_date',
			'estimated_hours',
			'priority',
			'notify_before_hours',
			'auto_assign',
			'max_occurrences',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		$user_fields = array( 'default_assignee', 'default_project_id' );
		foreach ( $user_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = (int) $value ?: null;
			}
		}

		$checklist = $request->get_param( 'checklist' );
		if ( null !== $checklist ) {
			$data['checklist'] = wp_json_encode( $checklist );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		// Recalculate next occurrence if schedule changed
		if ( isset( $data['frequency_type'] ) || isset( $data['start_date'] ) ) {
			$template                = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->templates_table} WHERE id = %d",
					$id
				),
				ARRAY_A
			);
			$merged                  = array_merge( $template, $data );
			$data['next_occurrence'] = $this->calculate_next_occurrence( $merged );
		}

		$wpdb->update( $this->templates_table, $data, array( 'id' => $id ) );

		$this->log_history( $id, null, 'updated', 'Template settings updated' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_template( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->delete( $this->instances_table, array( 'template_id' => $id ) );
		$wpdb->delete( $this->history_table, array( 'template_id' => $id ) );
		$wpdb->delete( $this->templates_table, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function pause_template( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->update( $this->templates_table, array( 'status' => 'paused' ), array( 'id' => $id ) );
		$this->log_history( $id, null, 'paused', 'Recurring task paused' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function resume_template( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->templates_table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		$next = $this->calculate_next_occurrence( $template );

		$wpdb->update(
			$this->templates_table,
			array(
				'status'          => 'active',
				'next_occurrence' => $next,
			),
			array( 'id' => $id )
		);

		$this->log_history( $id, null, 'resumed', 'Recurring task resumed' );

		return rest_ensure_response(
			array(
				'success'         => true,
				'next_occurrence' => $next,
			)
		);
	}

	public function generate_instance( $request ) {
		global $wpdb;
		$id             = (int) $request->get_param( 'id' );
		$scheduled_date = sanitize_text_field( $request->get_param( 'scheduled_date' ) );

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->templates_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$instance_id = $this->create_instance( $template, $scheduled_date ?: $template->next_occurrence );

		return rest_ensure_response(
			array(
				'success'     => true,
				'instance_id' => $instance_id,
			)
		);
	}

	public function get_instances( $request ) {
		global $wpdb;
		$template_id = (int) $request->get_param( 'id' );
		$status      = $request->get_param( 'status' );

		$where  = 'template_id = %d';
		$values = array( $template_id );

		if ( $status ) {
			$where   .= ' AND status = %s';
			$values[] = $status;
		}

		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, u.display_name as assignee_name
             FROM {$this->instances_table} i
             LEFT JOIN {$wpdb->users} u ON i.assignee_id = u.ID
             WHERE {$where} ORDER BY scheduled_date DESC",
				$values
			)
		);

		return rest_ensure_response( $instances );
	}

	public function get_history( $request ) {
		global $wpdb;
		$template_id = (int) $request->get_param( 'id' );

		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.*, u.display_name as user_name
             FROM {$this->history_table} h
             LEFT JOIN {$wpdb->users} u ON h.performed_by = u.ID
             WHERE h.template_id = %d ORDER BY h.created_at DESC LIMIT 50",
				$template_id
			)
		);

		return rest_ensure_response( $history );
	}

	public function get_upcoming( $request ) {
		global $wpdb;
		$days    = (int) $request->get_param( 'days' ) ?: 7;
		$user_id = $request->get_param( 'user_id' );

		$end_date = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );

		$where  = "i.scheduled_date <= %s AND i.status = 'scheduled'";
		$values = array( $end_date );

		if ( $user_id ) {
			$where   .= ' AND i.assignee_id = %d';
			$values[] = (int) $user_id;
		}

		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, t.name as template_name, t.task_type, t.priority,
                    u.display_name as assignee_name
             FROM {$this->instances_table} i
             JOIN {$this->templates_table} t ON i.template_id = t.id
             LEFT JOIN {$wpdb->users} u ON i.assignee_id = u.ID
             WHERE {$where}
             ORDER BY i.scheduled_date",
				$values
			)
		);

		return rest_ensure_response( $instances );
	}

	public function process_due_tasks() {
		global $wpdb;

		// Get all active templates with due next_occurrence
		$templates = $wpdb->get_results(
			"SELECT * FROM {$this->templates_table}
             WHERE status = 'active' AND next_occurrence <= NOW()"
		);

		foreach ( $templates as $template ) {
			// Check if max occurrences reached
			if ( $template->max_occurrences && $template->occurrences_count >= $template->max_occurrences ) {
				$wpdb->update(
					$this->templates_table,
					array( 'status' => 'completed' ),
					array( 'id' => $template->id )
				);
				continue;
			}

			// Check if end date passed
			if ( $template->end_date && strtotime( $template->end_date ) < time() ) {
				$wpdb->update(
					$this->templates_table,
					array( 'status' => 'expired' ),
					array( 'id' => $template->id )
				);
				continue;
			}

			// Create the instance
			$this->create_instance( $template, $template->next_occurrence );

			// Calculate and update next occurrence
			$next = $this->calculate_next_occurrence( (array) $template );

			$wpdb->update(
				$this->templates_table,
				array(
					'next_occurrence'   => $next,
					'occurrences_count' => $template->occurrences_count + 1,
				),
				array( 'id' => $template->id )
			);
		}

		// Mark overdue instances
		$wpdb->query(
			"UPDATE {$this->instances_table}
             SET status = 'overdue'
             WHERE status = 'scheduled' AND scheduled_date < DATE_SUB(NOW(), INTERVAL 1 DAY)"
		);
	}

	private function create_instance( $template, $scheduled_date ) {
		global $wpdb;

		$wpdb->insert(
			$this->instances_table,
			array(
				'template_id'    => $template->id,
				'scheduled_date' => $scheduled_date,
				'assignee_id'    => $template->default_assignee,
				'project_id'     => $template->default_project_id,
			)
		);

		$instance_id = $wpdb->insert_id;

		$this->log_history(
			$template->id,
			$instance_id,
			'instance_created',
			"Instance scheduled for {$scheduled_date}"
		);

		// Send notification if configured
		if ( $template->notify_before_hours > 0 && $template->default_assignee ) {
			do_action( 'ict_recurring_task_notification', $template, $instance_id, $scheduled_date );
		}

		return $instance_id;
	}

	private function calculate_next_occurrence( $template ) {
		$start = strtotime( $template['start_date'] );
		$now   = time();
		$base  = max( $start, $now );
		$freq  = (int) $template['frequency_value'] ?: 1;

		switch ( $template['frequency_type'] ) {
			case 'daily':
				return date( 'Y-m-d H:i:s', strtotime( "+{$freq} days", $base ) );

			case 'weekly':
				$days = $template['days_of_week'] ? explode( ',', $template['days_of_week'] ) : array( 1 );
				$next = strtotime( 'next ' . $this->day_name( $days[0] ), $base );
				return date( 'Y-m-d H:i:s', $next );

			case 'monthly':
				$day  = (int) $template['day_of_month'] ?: 1;
				$next = strtotime( date( 'Y-m-' . sprintf( '%02d', $day ), strtotime( '+1 month', $base ) ) );
				return date( 'Y-m-d H:i:s', $next );

			case 'yearly':
				$month = (int) $template['month_of_year'] ?: 1;
				$day   = (int) $template['day_of_month'] ?: 1;
				$year  = date( 'Y', $base );
				$next  = strtotime( "{$year}-{$month}-{$day}" );
				if ( $next <= $base ) {
					$next = strtotime( ( $year + 1 ) . "-{$month}-{$day}" );
				}
				return date( 'Y-m-d H:i:s', $next );

			case 'custom':
				// Simple interval-based calculation
				return date( 'Y-m-d H:i:s', strtotime( "+{$freq} days", $base ) );

			default:
				return date( 'Y-m-d H:i:s', strtotime( '+1 day', $base ) );
		}
	}

	private function day_name( $day_num ) {
		$days = array(
			1 => 'Monday',
			2 => 'Tuesday',
			3 => 'Wednesday',
			4 => 'Thursday',
			5 => 'Friday',
			6 => 'Saturday',
			7 => 'Sunday',
		);
		return $days[ $day_num ] ?? 'Monday';
	}

	private function log_history( $template_id, $instance_id, $action, $details ) {
		global $wpdb;
		$wpdb->insert(
			$this->history_table,
			array(
				'template_id'  => $template_id,
				'instance_id'  => $instance_id,
				'action'       => $action,
				'details'      => $details,
				'performed_by' => get_current_user_id(),
			)
		);
	}
}
