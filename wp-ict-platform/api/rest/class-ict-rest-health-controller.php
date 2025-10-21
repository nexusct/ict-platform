<?php
/**
 * Health Check REST Controller
 *
 * Provides endpoints for testing all integrations and system health.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Health_Controller
 *
 * REST API endpoints for health checks and integration testing.
 */
class ICT_REST_Health_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->namespace = 'ict/v1';
		$this->rest_base = 'health';
	}

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// GET /health
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_system_health' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		// GET /health/database
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/database',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_database_health' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		// GET /health/zoho
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/zoho',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_zoho_health' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		// GET /health/quotewerks
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/quotewerks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_quotewerks_health' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		// GET /health/sync
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sync',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_sync_health' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);

		// POST /health/test-sync
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test-sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_sync_workflow' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
				'args'                => array(
					'workflow' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'quote_to_project', 'project_to_crm', 'project_to_fsm', 'inventory_to_books', 'full_workflow' ),
						'description'       => 'Workflow to test',
					),
					'entity_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'description'       => 'Entity ID for testing',
					),
				),
			)
		);
	}

	/**
	 * Get overall system health.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_system_health( $request ) {
		$health = array(
			'status'      => 'healthy',
			'timestamp'   => current_time( 'mysql' ),
			'version'     => ICT_PLATFORM_VERSION,
			'checks'      => array(),
		);

		// Database health
		$db_health = $this->get_database_status();
		$health['checks']['database'] = $db_health;
		if ( ! $db_health['healthy'] ) {
			$health['status'] = 'degraded';
		}

		// Zoho integrations health
		$zoho_health = $this->get_zoho_status();
		$health['checks']['zoho'] = $zoho_health;
		if ( $zoho_health['healthy_count'] < $zoho_health['total_count'] ) {
			$health['status'] = $health['status'] === 'healthy' ? 'degraded' : 'critical';
		}

		// QuoteWerks health
		$qw_health = $this->get_quotewerks_status();
		$health['checks']['quotewerks'] = $qw_health;
		if ( ! $qw_health['healthy'] ) {
			$health['status'] = 'degraded';
		}

		// Sync queue health
		$sync_health = $this->get_sync_status();
		$health['checks']['sync'] = $sync_health;
		if ( $sync_health['status'] !== 'healthy' ) {
			$health['status'] = $sync_health['status'] === 'warning' ? 'degraded' : 'critical';
		}

		return rest_ensure_response( $health );
	}

	/**
	 * Check database health.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_database_health( $request ) {
		return rest_ensure_response( $this->get_database_status() );
	}

	/**
	 * Check Zoho integrations health.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_zoho_health( $request ) {
		return rest_ensure_response( $this->get_zoho_status() );
	}

	/**
	 * Check QuoteWerks health.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_quotewerks_health( $request ) {
		return rest_ensure_response( $this->get_quotewerks_status() );
	}

	/**
	 * Check sync health.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_sync_health( $request ) {
		return rest_ensure_response( $this->get_sync_status() );
	}

	/**
	 * Test sync workflow.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function test_sync_workflow( $request ) {
		$workflow  = $request->get_param( 'workflow' );
		$entity_id = $request->get_param( 'entity_id' );

		$orchestrator = new ICT_Sync_Orchestrator();

		$result = array(
			'workflow'   => $workflow,
			'timestamp'  => current_time( 'mysql' ),
			'success'    => false,
			'steps'      => array(),
			'errors'     => array(),
		);

		try {
			switch ( $workflow ) {
				case 'quote_to_project':
					if ( ! $entity_id ) {
						return new WP_Error( 'missing_id', 'Quote ID required', array( 'status' => 400 ) );
					}
					$qw_adapter = new ICT_QuoteWerks_Adapter();
					$project_id = $qw_adapter->sync_quote_to_project( $entity_id );
					$result['steps'][] = array(
						'name'    => 'QuoteWerks to WordPress',
						'status'  => is_wp_error( $project_id ) ? 'error' : 'success',
						'result'  => is_wp_error( $project_id ) ? $project_id->get_error_message() : $project_id,
					);
					$result['success'] = ! is_wp_error( $project_id );
					break;

				case 'project_to_crm':
					if ( ! $entity_id ) {
						return new WP_Error( 'missing_id', 'Project ID required', array( 'status' => 400 ) );
					}
					$crm_result = $orchestrator->sync_project_to_zoho_crm( $entity_id );
					$result['steps'][] = array(
						'name'    => 'WordPress to Zoho CRM',
						'status'  => is_wp_error( $crm_result ) ? 'error' : 'success',
						'result'  => is_wp_error( $crm_result ) ? $crm_result->get_error_message() : $crm_result,
					);
					$result['success'] = ! is_wp_error( $crm_result );
					break;

				case 'project_to_fsm':
					if ( ! $entity_id ) {
						return new WP_Error( 'missing_id', 'Project ID required', array( 'status' => 400 ) );
					}
					$fsm_result = $orchestrator->sync_project_to_zoho_fsm( $entity_id );
					$result['steps'][] = array(
						'name'    => 'WordPress to Zoho FSM',
						'status'  => is_wp_error( $fsm_result ) ? 'error' : 'success',
						'result'  => is_wp_error( $fsm_result ) ? $fsm_result->get_error_message() : $fsm_result,
					);
					$result['success'] = ! is_wp_error( $fsm_result );
					break;

				case 'inventory_to_books':
					if ( ! $entity_id ) {
						return new WP_Error( 'missing_id', 'Inventory item ID required', array( 'status' => 400 ) );
					}
					$books_result = $orchestrator->sync_inventory_to_zoho_books( $entity_id );
					$result['steps'][] = array(
						'name'    => 'Inventory to Zoho Books',
						'status'  => is_wp_error( $books_result ) ? 'error' : 'success',
						'result'  => is_wp_error( $books_result ) ? $books_result->get_error_message() : $books_result,
					);
					$result['success'] = ! is_wp_error( $books_result );
					break;

				case 'full_workflow':
					if ( ! $entity_id ) {
						return new WP_Error( 'missing_id', 'Quote ID required', array( 'status' => 400 ) );
					}
					$full_result = $orchestrator->full_quote_sync( $entity_id );

					$result['steps'][] = array(
						'name'    => 'QuoteWerks to WordPress',
						'status'  => isset( $full_result['quotewerks_to_wordpress'] ) ? 'success' : 'error',
						'result'  => $full_result['quotewerks_to_wordpress'] ?? 'Failed',
					);

					$result['steps'][] = array(
						'name'    => 'WordPress to Zoho CRM',
						'status'  => isset( $full_result['wordpress_to_zoho_crm'] ) ? 'success' : 'error',
						'result'  => $full_result['wordpress_to_zoho_crm'] ?? 'Failed',
					);

					$result['steps'][] = array(
						'name'    => 'WordPress to Zoho FSM',
						'status'  => isset( $full_result['wordpress_to_zoho_fsm'] ) ? 'success' : 'error',
						'result'  => $full_result['wordpress_to_zoho_fsm'] ?? 'Failed',
					);

					$result['success'] = $full_result['success'];
					$result['errors']  = $full_result['errors'];
					break;
			}
		} catch ( Exception $e ) {
			$result['errors'][] = $e->getMessage();
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get database status.
	 *
	 * @since  1.0.0
	 * @return array Database status.
	 */
	protected function get_database_status() {
		global $wpdb;

		$tables = array(
			'projects'         => ICT_PROJECTS_TABLE,
			'time_entries'     => ICT_TIME_ENTRIES_TABLE,
			'inventory_items'  => ICT_INVENTORY_ITEMS_TABLE,
			'purchase_orders'  => ICT_PURCHASE_ORDERS_TABLE,
			'project_resources' => ICT_PROJECT_RESOURCES_TABLE,
			'sync_queue'       => ICT_SYNC_QUEUE_TABLE,
			'sync_log'         => ICT_SYNC_LOG_TABLE,
		);

		$status = array(
			'healthy'       => true,
			'tables'        => array(),
			'missing_tables' => array(),
		);

		foreach ( $tables as $name => $table_name ) {
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

			if ( $exists ) {
				$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
				$status['tables'][ $name ] = array(
					'exists' => true,
					'count'  => (int) $count,
				);
			} else {
				$status['healthy'] = false;
				$status['missing_tables'][] = $name;
				$status['tables'][ $name ] = array(
					'exists' => false,
					'count'  => 0,
				);
			}
		}

		// Check database connection
		$wpdb->check_connection();
		$status['connection'] = empty( $wpdb->last_error );

		if ( ! $status['connection'] ) {
			$status['healthy'] = false;
			$status['error'] = $wpdb->last_error;
		}

		return $status;
	}

	/**
	 * Get Zoho integrations status.
	 *
	 * @since  1.0.0
	 * @return array Zoho status.
	 */
	protected function get_zoho_status() {
		$services = array(
			'crm'    => new ICT_Zoho_CRM_Adapter(),
			'fsm'    => new ICT_Zoho_FSM_Adapter(),
			'books'  => new ICT_Zoho_Books_Adapter(),
			'people' => new ICT_Zoho_People_Adapter(),
			'desk'   => new ICT_Zoho_Desk_Adapter(),
		);

		$status = array(
			'healthy_count' => 0,
			'total_count'   => count( $services ),
			'services'      => array(),
		);

		foreach ( $services as $name => $adapter ) {
			try {
				$test_result = $adapter->test_connection();
				$is_healthy  = ! is_wp_error( $test_result );

				$status['services'][ $name ] = array(
					'healthy'   => $is_healthy,
					'message'   => $is_healthy ? 'Connected' : $test_result->get_error_message(),
					'last_test' => current_time( 'mysql' ),
				);

				if ( $is_healthy ) {
					$status['healthy_count']++;
				}
			} catch ( Exception $e ) {
				$status['services'][ $name ] = array(
					'healthy'   => false,
					'message'   => $e->getMessage(),
					'last_test' => current_time( 'mysql' ),
				);
			}
		}

		return $status;
	}

	/**
	 * Get QuoteWerks status.
	 *
	 * @since  1.0.0
	 * @return array QuoteWerks status.
	 */
	protected function get_quotewerks_status() {
		$status = array(
			'healthy'     => false,
			'configured'  => false,
			'message'     => '',
			'last_test'   => current_time( 'mysql' ),
		);

		$api_url = get_option( 'ict_quotewerks_api_url' );
		$api_key = get_option( 'ict_quotewerks_api_key' );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			$status['message'] = 'QuoteWerks API credentials not configured';
			return $status;
		}

		$status['configured'] = true;

		try {
			$adapter = new ICT_QuoteWerks_Adapter();
			$test_result = $adapter->test_connection();

			if ( ! is_wp_error( $test_result ) ) {
				$status['healthy'] = true;
				$status['message'] = 'Connected successfully';
			} else {
				$status['message'] = $test_result->get_error_message();
			}
		} catch ( Exception $e ) {
			$status['message'] = $e->getMessage();
		}

		return $status;
	}

	/**
	 * Get sync queue status.
	 *
	 * @since  1.0.0
	 * @return array Sync status.
	 */
	protected function get_sync_status() {
		global $wpdb;

		$sync_queue_table = ICT_SYNC_QUEUE_TABLE;
		$sync_log_table   = ICT_SYNC_LOG_TABLE;

		// Get queue statistics
		$pending_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_queue_table} WHERE status = 'pending'"
		);

		$processing_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_queue_table} WHERE status = 'processing'"
		);

		$failed_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_queue_table} WHERE status = 'failed'"
		);

		// Get recent sync statistics (last 24 hours)
		$success_count_24h = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_log_table}
			WHERE status = 'success'
			AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		$error_count_24h = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_log_table}
			WHERE status = 'error'
			AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		// Get last successful sync
		$last_success = $wpdb->get_var(
			"SELECT MAX(created_at) FROM {$sync_log_table} WHERE status = 'success'"
		);

		// Determine health status
		$health_status = 'healthy';
		if ( (int) $error_count_24h > 10 ) {
			$health_status = 'critical';
		} elseif ( (int) $error_count_24h > 3 || (int) $pending_count > 50 ) {
			$health_status = 'warning';
		}

		return array(
			'status'              => $health_status,
			'queue'               => array(
				'pending'    => (int) $pending_count,
				'processing' => (int) $processing_count,
				'failed'     => (int) $failed_count,
			),
			'last_24h'            => array(
				'success' => (int) $success_count_24h,
				'errors'  => (int) $error_count_24h,
			),
			'last_successful_sync' => $last_success,
		);
	}

	/**
	 * Check admin permissions.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_admin_permissions( $request ) {
		return current_user_can( 'manage_options' );
	}
}
