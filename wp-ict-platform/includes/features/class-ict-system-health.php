<?php
/**
 * System Health Monitor
 *
 * Monitor system health, performance, and diagnostics.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_System_Health {

	private static $instance = null;
	private $metrics_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->metrics_table = $wpdb->prefix . 'ict_system_metrics';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'ict_collect_metrics', array( $this, 'collect_metrics' ) );

		if ( ! wp_next_scheduled( 'ict_collect_metrics' ) ) {
			wp_schedule_event( time(), 'hourly', 'ict_collect_metrics' );
		}
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/system/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/diagnostics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_diagnostics' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/metrics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_metrics' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/database',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_database_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/integrations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_integration_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/optimize',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'optimize' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/system/test-connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->metrics_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(15,4),
            metric_unit varchar(20),
            category varchar(50),
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY metric_name (metric_name),
            KEY category (category),
            KEY recorded_at (recorded_at)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function get_health( $request ) {
		$checks = array(
			'database'     => $this->check_database(),
			'filesystem'   => $this->check_filesystem(),
			'memory'       => $this->check_memory(),
			'cron'         => $this->check_cron(),
			'integrations' => $this->check_integrations(),
			'ssl'          => $this->check_ssl(),
			'php'          => $this->check_php(),
		);

		$status = 'healthy';
		$issues = array();

		foreach ( $checks as $name => $check ) {
			if ( $check['status'] === 'error' ) {
				$status   = 'critical';
				$issues[] = $check;
			} elseif ( $check['status'] === 'warning' && $status !== 'critical' ) {
				$status   = 'degraded';
				$issues[] = $check;
			}
		}

		return rest_ensure_response(
			array(
				'status'     => $status,
				'checks'     => $checks,
				'issues'     => $issues,
				'checked_at' => current_time( 'mysql' ),
			)
		);
	}

	public function get_diagnostics( $request ) {
		global $wpdb;

		$diagnostics = array(
			'wordpress' => array(
				'version'      => get_bloginfo( 'version' ),
				'multisite'    => is_multisite(),
				'debug_mode'   => WP_DEBUG,
				'memory_limit' => WP_MEMORY_LIMIT,
				'timezone'     => get_option( 'timezone_string' ) ?: 'UTC',
				'locale'       => get_locale(),
			),
			'php'       => array(
				'version'            => PHP_VERSION,
				'memory_limit'       => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'upload_max_size'    => ini_get( 'upload_max_filesize' ),
				'post_max_size'      => ini_get( 'post_max_size' ),
				'extensions'         => get_loaded_extensions(),
			),
			'database'  => array(
				'version' => $wpdb->db_version(),
				'prefix'  => $wpdb->prefix,
				'charset' => $wpdb->charset,
				'collate' => $wpdb->collate,
			),
			'server'    => array(
				'software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
				'os'       => PHP_OS,
				'php_sapi' => php_sapi_name(),
			),
			'plugin'    => array(
				'version'    => defined( 'ICT_VERSION' ) ? ICT_VERSION : 'Unknown',
				'db_version' => get_option( 'ict_db_version' ),
				'tables'     => $this->get_table_info(),
			),
		);

		return rest_ensure_response( $diagnostics );
	}

	public function get_metrics( $request ) {
		global $wpdb;

		$period   = $request->get_param( 'period' ) ?: '24h';
		$category = $request->get_param( 'category' );

		$time_map = array(
			'1h'  => '1 HOUR',
			'24h' => '24 HOUR',
			'7d'  => '7 DAY',
			'30d' => '30 DAY',
		);

		$interval = $time_map[ $period ] ?? '24 HOUR';

		$where = "recorded_at >= DATE_SUB(NOW(), INTERVAL {$interval})";
		if ( $category ) {
			$where .= $wpdb->prepare( ' AND category = %s', $category );
		}

		$metrics = $wpdb->get_results(
			"SELECT metric_name, category, metric_unit,
                    AVG(metric_value) as avg_value,
                    MIN(metric_value) as min_value,
                    MAX(metric_value) as max_value,
                    COUNT(*) as data_points
             FROM {$this->metrics_table}
             WHERE {$where}
             GROUP BY metric_name, category, metric_unit"
		);

		// Get timeline data
		$timeline = $wpdb->get_results(
			"SELECT metric_name,
                    DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') as hour,
                    AVG(metric_value) as value
             FROM {$this->metrics_table}
             WHERE {$where}
             GROUP BY metric_name, hour
             ORDER BY hour"
		);

		return rest_ensure_response(
			array(
				'summary'  => $metrics,
				'timeline' => $timeline,
			)
		);
	}

	public function get_database_status( $request ) {
		global $wpdb;

		$tables = $this->get_table_info();

		// Get query stats
		$slow_queries = $wpdb->get_var(
			"SHOW GLOBAL STATUS LIKE 'Slow_queries'"
		);

		// Get table sizes
		$db_size = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(data_length + index_length)
             FROM information_schema.TABLES
             WHERE table_schema = %s',
				DB_NAME
			)
		);

		// Check for orphaned data
		$orphaned = $this->check_orphaned_data();

		return rest_ensure_response(
			array(
				'tables'       => $tables,
				'total_size'   => size_format( $db_size ),
				'slow_queries' => (int) $slow_queries,
				'orphaned'     => $orphaned,
				'optimizable'  => $this->get_optimizable_tables(),
			)
		);
	}

	public function get_integration_status( $request ) {
		$integrations = array();

		// Zoho integrations
		$zoho_services = array( 'crm', 'fsm', 'books', 'people', 'desk' );
		foreach ( $zoho_services as $service ) {
			$integrations[ "zoho_{$service}" ] = array(
				'name'      => 'Zoho ' . ucfirst( $service ),
				'enabled'   => (bool) get_option( "ict_zoho_{$service}_enabled" ),
				'connected' => (bool) get_option( "ict_zoho_{$service}_token" ),
				'last_sync' => get_option( "ict_zoho_{$service}_last_sync" ),
			);
		}

		// Check sync queue
		global $wpdb;
		$queue_stats = $wpdb->get_row(
			"SELECT COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$wpdb->prefix}ict_sync_queue"
		);

		return rest_ensure_response(
			array(
				'integrations' => $integrations,
				'sync_queue'   => $queue_stats,
			)
		);
	}

	public function get_logs( $request ) {
		global $wpdb;

		$type  = $request->get_param( 'type' ) ?: 'error';
		$limit = (int) $request->get_param( 'limit' ) ?: 100;

		$logs = array();

		// Error log
		if ( $type === 'error' || $type === 'all' ) {
			$error_log = WP_CONTENT_DIR . '/debug.log';
			if ( file_exists( $error_log ) ) {
				$content       = file_get_contents( $error_log );
				$lines         = array_filter( explode( "\n", $content ) );
				$logs['error'] = array_slice( $lines, -$limit );
			}
		}

		// Sync log
		if ( $type === 'sync' || $type === 'all' ) {
			$logs['sync'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ict_sync_log
                 ORDER BY created_at DESC LIMIT %d",
					$limit
				)
			);
		}

		// Audit log
		if ( $type === 'audit' || $type === 'all' ) {
			$logs['audit'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ict_audit_log
                 WHERE severity IN ('warning', 'error', 'critical')
                 ORDER BY created_at DESC LIMIT %d",
					$limit
				)
			);
		}

		return rest_ensure_response( $logs );
	}

	public function optimize( $request ) {
		global $wpdb;

		$actions = $request->get_param( 'actions' ) ?: array( 'tables', 'transients', 'logs' );
		$results = array();

		// Optimize tables
		if ( in_array( 'tables', $actions, true ) ) {
			$tables = $wpdb->get_col(
				"SHOW TABLES LIKE '{$wpdb->prefix}ict_%'"
			);

			foreach ( $tables as $table ) {
				$wpdb->query( "OPTIMIZE TABLE {$table}" );
			}
			$results['tables_optimized'] = count( $tables );
		}

		// Clear transients
		if ( in_array( 'transients', $actions, true ) ) {
			$deleted                       = $wpdb->query(
				"DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_ict_%'
                 OR option_name LIKE '_transient_timeout_ict_%'"
			);
			$results['transients_cleared'] = (int) $deleted;
		}

		// Cleanup old logs
		if ( in_array( 'logs', $actions, true ) ) {
			$deleted                 = $wpdb->query(
				"DELETE FROM {$wpdb->prefix}ict_sync_log
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
			);
			$results['logs_cleaned'] = (int) $deleted;

			$deleted                    = $wpdb->query(
				"DELETE FROM {$this->metrics_table}
                 WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
			);
			$results['metrics_cleaned'] = (int) $deleted;
		}

		// Clear orphaned data
		if ( in_array( 'orphans', $actions, true ) ) {
			$results['orphans_cleaned'] = $this->cleanup_orphaned_data();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'results' => $results,
			)
		);
	}

	public function test_connection( $request ) {
		$service = sanitize_text_field( $request->get_param( 'service' ) );

		switch ( $service ) {
			case 'database':
				return rest_ensure_response( $this->check_database() );

			case 'zoho':
				// Would test Zoho connection
				return rest_ensure_response(
					array(
						'status'  => 'ok',
						'message' => 'Zoho connection test would run here',
					)
				);

			default:
				return new WP_Error( 'invalid_service', 'Unknown service', array( 'status' => 400 ) );
		}
	}

	public function collect_metrics() {
		global $wpdb;

		$metrics = array(
			array(
				'name'     => 'active_projects',
				'value'    => $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects WHERE status = 'in_progress'"
				),
				'category' => 'projects',
			),
			array(
				'name'     => 'time_entries_today',
				'value'    => $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ict_time_entries WHERE DATE(start_time) = CURDATE()"
				),
				'category' => 'time',
			),
			array(
				'name'     => 'low_stock_items',
				'value'    => $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ict_inventory_items WHERE quantity <= reorder_point"
				),
				'category' => 'inventory',
			),
			array(
				'name'     => 'sync_queue_size',
				'value'    => $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ict_sync_queue WHERE status = 'pending'"
				),
				'category' => 'sync',
			),
			array(
				'name'     => 'db_size_mb',
				'value'    => $wpdb->get_var(
					$wpdb->prepare(
						'SELECT SUM(data_length + index_length) / 1024 / 1024
                 FROM information_schema.TABLES WHERE table_schema = %s',
						DB_NAME
					)
				),
				'category' => 'system',
				'unit'     => 'MB',
			),
		);

		foreach ( $metrics as $metric ) {
			$wpdb->insert(
				$this->metrics_table,
				array(
					'metric_name'  => $metric['name'],
					'metric_value' => $metric['value'],
					'metric_unit'  => $metric['unit'] ?? null,
					'category'     => $metric['category'],
				)
			);
		}
	}

	private function check_database() {
		global $wpdb;

		try {
			$result = $wpdb->get_var( 'SELECT 1' );
			$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}ict_%'" );

			return array(
				'name'    => 'Database',
				'status'  => 'ok',
				'message' => count( $tables ) . ' ICT tables found',
			);
		} catch ( Exception $e ) {
			return array(
				'name'    => 'Database',
				'status'  => 'error',
				'message' => 'Database connection failed: ' . $e->getMessage(),
			);
		}
	}

	private function check_filesystem() {
		$upload_dir = wp_upload_dir();
		$writable   = wp_is_writable( $upload_dir['basedir'] );

		return array(
			'name'    => 'Filesystem',
			'status'  => $writable ? 'ok' : 'error',
			'message' => $writable ? 'Upload directory writable' : 'Upload directory not writable',
		);
	}

	private function check_memory() {
		$limit       = ini_get( 'memory_limit' );
		$limit_bytes = wp_convert_hr_to_bytes( $limit );
		$usage       = memory_get_usage( true );
		$percentage  = round( ( $usage / $limit_bytes ) * 100 );

		$status = 'ok';
		if ( $percentage > 90 ) {
			$status = 'error';
		} elseif ( $percentage > 75 ) {
			$status = 'warning';
		}

		return array(
			'name'    => 'Memory',
			'status'  => $status,
			'message' => "{$percentage}% used ({$limit} limit)",
		);
	}

	private function check_cron() {
		$next_sync = wp_next_scheduled( 'ict_process_sync_queue' );
		$overdue   = $next_sync && $next_sync < ( time() - 3600 );

		return array(
			'name'    => 'Cron Jobs',
			'status'  => $overdue ? 'warning' : 'ok',
			'message' => $overdue ? 'Cron jobs may be delayed' : 'Cron running normally',
		);
	}

	private function check_integrations() {
		$connected = 0;
		$total     = 0;

		$services = array( 'crm', 'fsm', 'books' );
		foreach ( $services as $service ) {
			if ( get_option( "ict_zoho_{$service}_enabled" ) ) {
				++$total;
				if ( get_option( "ict_zoho_{$service}_token" ) ) {
					++$connected;
				}
			}
		}

		if ( $total === 0 ) {
			return array(
				'name'    => 'Integrations',
				'status'  => 'ok',
				'message' => 'No integrations configured',
			);
		}

		$status = $connected === $total ? 'ok' : ( $connected > 0 ? 'warning' : 'error' );

		return array(
			'name'    => 'Integrations',
			'status'  => $status,
			'message' => "{$connected}/{$total} integrations connected",
		);
	}

	private function check_ssl() {
		$ssl = is_ssl();

		return array(
			'name'    => 'SSL',
			'status'  => $ssl ? 'ok' : 'warning',
			'message' => $ssl ? 'SSL enabled' : 'SSL not detected',
		);
	}

	private function check_php() {
		$version     = PHP_VERSION;
		$min_version = '8.0';

		$status = version_compare( $version, $min_version, '>=' ) ? 'ok' : 'warning';

		return array(
			'name'    => 'PHP Version',
			'status'  => $status,
			'message' => "PHP {$version} (recommended: {$min_version}+)",
		);
	}

	private function get_table_info() {
		global $wpdb;

		$tables = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name, table_rows, data_length, index_length
             FROM information_schema.TABLES
             WHERE table_schema = %s AND table_name LIKE %s',
				DB_NAME,
				$wpdb->prefix . 'ict_%'
			)
		);

		$info = array();
		foreach ( $tables as $table ) {
			$info[] = array(
				'name'       => $table->table_name,
				'rows'       => (int) $table->table_rows,
				'size'       => size_format( $table->data_length + $table->index_length ),
				'data_size'  => (int) $table->data_length,
				'index_size' => (int) $table->index_length,
			);
		}

		return $info;
	}

	private function check_orphaned_data() {
		global $wpdb;

		$orphaned = array();

		// Time entries with invalid project
		$orphaned['time_entries'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ict_time_entries t
             LEFT JOIN {$wpdb->prefix}ict_projects p ON t.project_id = p.id
             WHERE t.project_id IS NOT NULL AND p.id IS NULL"
		);

		return $orphaned;
	}

	private function get_optimizable_tables() {
		global $wpdb;

		$tables = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name, data_free
             FROM information_schema.TABLES
             WHERE table_schema = %s AND table_name LIKE %s AND data_free > 0',
				DB_NAME,
				$wpdb->prefix . 'ict_%'
			)
		);

		return array_map(
			function ( $t ) {
				return array(
					'name'        => $t->table_name,
					'reclaimable' => size_format( $t->data_free ),
				);
			},
			$tables
		);
	}

	private function cleanup_orphaned_data() {
		global $wpdb;

		$cleaned = 0;

		// Clean orphaned time entries
		$cleaned += $wpdb->query(
			"DELETE t FROM {$wpdb->prefix}ict_time_entries t
             LEFT JOIN {$wpdb->prefix}ict_projects p ON t.project_id = p.id
             WHERE t.project_id IS NOT NULL AND p.id IS NULL"
		);

		return $cleaned;
	}
}
