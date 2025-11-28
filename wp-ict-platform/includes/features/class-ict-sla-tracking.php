<?php
/**
 * SLA Tracking
 *
 * Track and enforce service level agreements.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_SLA_Tracking {

	private static $instance = null;
	private $slas_table;
	private $breaches_table;
	private $metrics_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->slas_table     = $wpdb->prefix . 'ict_slas';
		$this->breaches_table = $wpdb->prefix . 'ict_sla_breaches';
		$this->metrics_table  = $wpdb->prefix . 'ict_sla_metrics';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'ict_check_sla_compliance', array( $this, 'check_all_slas' ) );

		if ( ! wp_next_scheduled( 'ict_check_sla_compliance' ) ) {
			wp_schedule_event( time(), 'hourly', 'ict_check_sla_compliance' );
		}
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/slas',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_slas' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_sla' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/slas/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sla' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_sla' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_sla' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/slas/(?P<id>\d+)/breaches',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_breaches' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/slas/(?P<id>\d+)/metrics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_metrics' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/slas/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/slas/at-risk',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_at_risk' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/slas/client/(?P<client_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_slas' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'view_ict_reports' ) || current_user_can( 'manage_options' );
	}

	public function check_edit_permission() {
		return current_user_can( 'manage_ict_settings' ) || current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->slas_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            sla_type enum('response_time','resolution_time','uptime','custom') NOT NULL,
            applies_to enum('all','client','project_type','priority') DEFAULT 'all',
            applies_to_value varchar(255),
            target_value decimal(10,2) NOT NULL,
            target_unit enum('minutes','hours','days','percent') NOT NULL,
            warning_threshold decimal(5,2) DEFAULT 80,
            business_hours_only tinyint(1) DEFAULT 1,
            business_start time DEFAULT '09:00:00',
            business_end time DEFAULT '17:00:00',
            business_days varchar(20) DEFAULT '1,2,3,4,5',
            escalation_rules text,
            is_active tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sla_type (sla_type),
            KEY is_active (is_active)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->breaches_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sla_id bigint(20) unsigned NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            breach_type enum('warning','breach') NOT NULL,
            target_value decimal(10,2),
            actual_value decimal(10,2),
            breach_time datetime NOT NULL,
            resolved_at datetime,
            notes text,
            notified tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sla_id (sla_id),
            KEY entity (entity_type, entity_id),
            KEY breach_time (breach_time)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->metrics_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sla_id bigint(20) unsigned NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            total_items int DEFAULT 0,
            items_met int DEFAULT 0,
            items_breached int DEFAULT 0,
            average_value decimal(10,2),
            compliance_rate decimal(5,2),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sla_period (sla_id, period_start, period_end),
            KEY sla_id (sla_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	public function get_slas( $request ) {
		global $wpdb;

		$active_only = $request->get_param( 'active_only' ) === 'true';
		$where       = $active_only ? 'WHERE is_active = 1' : '';

		$slas = $wpdb->get_results(
			"SELECT s.*,
                    (SELECT compliance_rate FROM {$this->metrics_table}
                     WHERE sla_id = s.id ORDER BY period_end DESC LIMIT 1) as current_compliance,
                    (SELECT COUNT(*) FROM {$this->breaches_table}
                     WHERE sla_id = s.id AND breach_type = 'breach' AND resolved_at IS NULL) as active_breaches
             FROM {$this->slas_table} s
             {$where}
             ORDER BY s.name"
		);

		foreach ( $slas as $sla ) {
			$sla->escalation_rules = json_decode( $sla->escalation_rules, true );
		}

		return rest_ensure_response( $slas );
	}

	public function get_sla( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$sla = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->slas_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $sla ) {
			return new WP_Error( 'not_found', 'SLA not found', array( 'status' => 404 ) );
		}

		$sla->escalation_rules = json_decode( $sla->escalation_rules, true );

		// Get recent metrics
		$sla->metrics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->metrics_table}
             WHERE sla_id = %d ORDER BY period_end DESC LIMIT 12",
				$id
			)
		);

		// Get active breaches
		$sla->active_breaches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->breaches_table}
             WHERE sla_id = %d AND resolved_at IS NULL
             ORDER BY breach_time DESC",
				$id
			)
		);

		return rest_ensure_response( $sla );
	}

	public function create_sla( $request ) {
		global $wpdb;

		$data = array(
			'name'                => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'         => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'sla_type'            => sanitize_text_field( $request->get_param( 'sla_type' ) ),
			'applies_to'          => sanitize_text_field( $request->get_param( 'applies_to' ) ?: 'all' ),
			'applies_to_value'    => sanitize_text_field( $request->get_param( 'applies_to_value' ) ),
			'target_value'        => (float) $request->get_param( 'target_value' ),
			'target_unit'         => sanitize_text_field( $request->get_param( 'target_unit' ) ),
			'warning_threshold'   => (float) $request->get_param( 'warning_threshold' ) ?: 80,
			'business_hours_only' => (int) $request->get_param( 'business_hours_only' ),
			'business_start'      => sanitize_text_field( $request->get_param( 'business_start' ) ?: '09:00:00' ),
			'business_end'        => sanitize_text_field( $request->get_param( 'business_end' ) ?: '17:00:00' ),
			'business_days'       => sanitize_text_field( $request->get_param( 'business_days' ) ?: '1,2,3,4,5' ),
			'created_by'          => get_current_user_id(),
		);

		$escalation = $request->get_param( 'escalation_rules' );
		if ( $escalation ) {
			$data['escalation_rules'] = wp_json_encode( $escalation );
		}

		$wpdb->insert( $this->slas_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function update_sla( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$fields = array(
			'name',
			'description',
			'applies_to',
			'applies_to_value',
			'business_start',
			'business_end',
			'business_days',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( $value );
			}
		}

		$float_fields = array( 'target_value', 'warning_threshold' );
		foreach ( $float_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = (float) $value;
			}
		}

		$int_fields = array( 'business_hours_only', 'is_active' );
		foreach ( $int_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = (int) $value;
			}
		}

		$escalation = $request->get_param( 'escalation_rules' );
		if ( null !== $escalation ) {
			$data['escalation_rules'] = wp_json_encode( $escalation );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->slas_table, $data, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_sla( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->delete( $this->breaches_table, array( 'sla_id' => $id ) );
		$wpdb->delete( $this->metrics_table, array( 'sla_id' => $id ) );
		$wpdb->delete( $this->slas_table, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_breaches( $request ) {
		global $wpdb;
		$sla_id = (int) $request->get_param( 'id' );
		$status = $request->get_param( 'status' );

		$where  = 'sla_id = %d';
		$values = array( $sla_id );

		if ( $status === 'active' ) {
			$where .= ' AND resolved_at IS NULL';
		} elseif ( $status === 'resolved' ) {
			$where .= ' AND resolved_at IS NOT NULL';
		}

		$breaches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->breaches_table}
             WHERE {$where}
             ORDER BY breach_time DESC",
				$values
			)
		);

		return rest_ensure_response( $breaches );
	}

	public function get_metrics( $request ) {
		global $wpdb;
		$sla_id = (int) $request->get_param( 'id' );
		$months = (int) $request->get_param( 'months' ) ?: 12;

		$metrics = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->metrics_table}
             WHERE sla_id = %d
             ORDER BY period_end DESC LIMIT %d",
				$sla_id,
				$months
			)
		);

		return rest_ensure_response( $metrics );
	}

	public function get_dashboard( $request ) {
		global $wpdb;

		// Overall compliance
		$overall = $wpdb->get_row(
			"SELECT AVG(compliance_rate) as avg_compliance,
                    SUM(items_breached) as total_breaches,
                    SUM(total_items) as total_items
             FROM {$this->metrics_table}
             WHERE period_end >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
		);

		// By SLA type
		$by_type = $wpdb->get_results(
			"SELECT s.sla_type, AVG(m.compliance_rate) as avg_compliance, COUNT(*) as count
             FROM {$this->slas_table} s
             LEFT JOIN {$this->metrics_table} m ON s.id = m.sla_id
             WHERE s.is_active = 1 AND m.period_end >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY s.sla_type"
		);

		// Active breaches
		$active_breaches = $wpdb->get_results(
			"SELECT b.*, s.name as sla_name
             FROM {$this->breaches_table} b
             JOIN {$this->slas_table} s ON b.sla_id = s.id
             WHERE b.resolved_at IS NULL
             ORDER BY b.breach_time DESC LIMIT 10"
		);

		// Trend
		$trend = $wpdb->get_results(
			"SELECT DATE_FORMAT(period_end, '%Y-%m') as month,
                    AVG(compliance_rate) as compliance,
                    SUM(items_breached) as breaches
             FROM {$this->metrics_table}
             WHERE period_end >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY month ORDER BY month"
		);

		return rest_ensure_response(
			array(
				'overall'         => $overall,
				'by_type'         => $by_type,
				'active_breaches' => $active_breaches,
				'trend'           => $trend,
			)
		);
	}

	public function get_at_risk( $request ) {
		global $wpdb;

		// Projects/tickets at risk of SLA breach
		$at_risk = array();

		// Response time SLAs
		$response_slas = $wpdb->get_results(
			"SELECT * FROM {$this->slas_table}
             WHERE sla_type = 'response_time' AND is_active = 1"
		);

		foreach ( $response_slas as $sla ) {
			$threshold_minutes = $this->get_target_in_minutes( $sla );
			$warning_minutes   = $threshold_minutes * ( $sla->warning_threshold / 100 );

			// Find tickets approaching threshold
			$tickets = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ict_tickets
                 WHERE status = 'open' AND first_response_at IS NULL
                 AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= %d
                 AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) < %d",
					$warning_minutes,
					$threshold_minutes
				)
			);

			foreach ( $tickets as $ticket ) {
				$elapsed   = ( time() - strtotime( $ticket->created_at ) ) / 60;
				$at_risk[] = array(
					'type'      => 'ticket',
					'id'        => $ticket->id,
					'sla'       => $sla->name,
					'target'    => $threshold_minutes,
					'elapsed'   => round( $elapsed ),
					'remaining' => round( $threshold_minutes - $elapsed ),
					'urgency'   => round( ( $elapsed / $threshold_minutes ) * 100 ),
				);
			}
		}

		// Sort by urgency
		usort(
			$at_risk,
			function ( $a, $b ) {
				return $b['urgency'] <=> $a['urgency'];
			}
		);

		return rest_ensure_response( $at_risk );
	}

	public function get_client_slas( $request ) {
		global $wpdb;
		$client_id = (int) $request->get_param( 'client_id' );

		// Get SLAs applicable to this client
		$slas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*,
                    (SELECT compliance_rate FROM {$this->metrics_table}
                     WHERE sla_id = s.id ORDER BY period_end DESC LIMIT 1) as current_compliance
             FROM {$this->slas_table} s
             WHERE s.is_active = 1
             AND (s.applies_to = 'all' OR (s.applies_to = 'client' AND s.applies_to_value = %s))
             ORDER BY s.name",
				$client_id
			)
		);

		// Get client-specific metrics
		$breaches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, s.name as sla_name
             FROM {$this->breaches_table} b
             JOIN {$this->slas_table} s ON b.sla_id = s.id
             WHERE b.entity_type = 'client' AND b.entity_id = %d
             ORDER BY b.breach_time DESC LIMIT 20",
				$client_id
			)
		);

		return rest_ensure_response(
			array(
				'slas'     => $slas,
				'breaches' => $breaches,
			)
		);
	}

	public function check_all_slas() {
		global $wpdb;

		$slas = $wpdb->get_results(
			"SELECT * FROM {$this->slas_table} WHERE is_active = 1"
		);

		foreach ( $slas as $sla ) {
			$this->check_sla( $sla );
		}

		// Calculate daily metrics
		$this->calculate_daily_metrics();
	}

	private function check_sla( $sla ) {
		global $wpdb;

		switch ( $sla->sla_type ) {
			case 'response_time':
				$this->check_response_time_sla( $sla );
				break;
			case 'resolution_time':
				$this->check_resolution_time_sla( $sla );
				break;
		}
	}

	private function check_response_time_sla( $sla ) {
		global $wpdb;

		$threshold = $this->get_target_in_minutes( $sla );

		// Find breached tickets
		$breached = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_tickets
             WHERE status != 'closed' AND first_response_at IS NULL
             AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > %d",
				$threshold
			)
		);

		foreach ( $breached as $ticket ) {
			$elapsed = ( time() - strtotime( $ticket->created_at ) ) / 60;

			// Check if breach already recorded
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->breaches_table}
                 WHERE sla_id = %d AND entity_type = 'ticket' AND entity_id = %d AND resolved_at IS NULL",
					$sla->id,
					$ticket->id
				)
			);

			if ( ! $existing ) {
				$wpdb->insert(
					$this->breaches_table,
					array(
						'sla_id'       => $sla->id,
						'entity_type'  => 'ticket',
						'entity_id'    => $ticket->id,
						'breach_type'  => 'breach',
						'target_value' => $threshold,
						'actual_value' => $elapsed,
						'breach_time'  => current_time( 'mysql' ),
					)
				);

				do_action( 'ict_sla_breached', $sla, $ticket, $elapsed );
			}
		}
	}

	private function check_resolution_time_sla( $sla ) {
		global $wpdb;

		$threshold = $this->get_target_in_minutes( $sla );

		// Find breached tickets
		$breached = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_tickets
             WHERE status != 'closed'
             AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > %d",
				$threshold
			)
		);

		foreach ( $breached as $ticket ) {
			$elapsed = ( time() - strtotime( $ticket->created_at ) ) / 60;

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->breaches_table}
                 WHERE sla_id = %d AND entity_type = 'ticket' AND entity_id = %d AND resolved_at IS NULL",
					$sla->id,
					$ticket->id
				)
			);

			if ( ! $existing ) {
				$wpdb->insert(
					$this->breaches_table,
					array(
						'sla_id'       => $sla->id,
						'entity_type'  => 'ticket',
						'entity_id'    => $ticket->id,
						'breach_type'  => 'breach',
						'target_value' => $threshold,
						'actual_value' => $elapsed,
						'breach_time'  => current_time( 'mysql' ),
					)
				);

				do_action( 'ict_sla_breached', $sla, $ticket, $elapsed );
			}
		}
	}

	private function calculate_daily_metrics() {
		global $wpdb;

		$slas = $wpdb->get_results(
			"SELECT * FROM {$this->slas_table} WHERE is_active = 1"
		);

		$today = date( 'Y-m-d' );

		foreach ( $slas as $sla ) {
			$threshold = $this->get_target_in_minutes( $sla );

			// Count items for today
			$total = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ict_tickets
                 WHERE DATE(created_at) = CURDATE()"
			);

			$breached = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->breaches_table}
                 WHERE sla_id = %d AND DATE(breach_time) = CURDATE()",
					$sla->id
				)
			);

			$met        = $total - $breached;
			$compliance = $total > 0 ? ( $met / $total ) * 100 : 100;

			// Update or insert metric
			$wpdb->replace(
				$this->metrics_table,
				array(
					'sla_id'          => $sla->id,
					'period_start'    => $today,
					'period_end'      => $today,
					'total_items'     => $total,
					'items_met'       => $met,
					'items_breached'  => $breached,
					'compliance_rate' => round( $compliance, 2 ),
				)
			);
		}
	}

	private function get_target_in_minutes( $sla ) {
		$value = $sla->target_value;

		switch ( $sla->target_unit ) {
			case 'hours':
				return $value * 60;
			case 'days':
				return $value * 60 * 24;
			default:
				return $value;
		}
	}
}
