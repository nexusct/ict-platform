<?php
/**
 * Offline Mode Manager
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Offline_Manager
 *
 * Handles offline data storage, sync queue, and conflict resolution.
 */
class ICT_Offline_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Offline_Manager
	 */
	private static $instance = null;

	/**
	 * Supported entity types for offline sync.
	 *
	 * @var array
	 */
	private $supported_entities = array(
		'time_entries',
		'projects',
		'tasks',
		'inventory',
		'expenses',
	);

	/**
	 * Get singleton instance.
	 *
	 * @since  1.1.0
	 * @return ICT_Offline_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes() {
		$this->register_sync_endpoints();
		add_action( 'ict_process_offline_queue', array( $this, 'process_offline_queue' ) );
	}

	/**
	 * Register REST API endpoints for offline sync.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_sync_endpoints() {
		register_rest_route(
			'ict/v1',
			'/offline/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_sync_request' ),
				'permission_callback' => array( $this, 'check_sync_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/offline/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_sync_manifest' ),
				'permission_callback' => array( $this, 'check_sync_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/offline/conflicts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_conflicts' ),
				'permission_callback' => array( $this, 'check_sync_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/offline/conflicts/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'resolve_conflict' ),
				'permission_callback' => array( $this, 'check_sync_permission' ),
			)
		);
	}

	/**
	 * Check sync permission.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function check_sync_permission( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Handle sync request from client.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function handle_sync_request( $request ) {
		$user_id     = get_current_user_id();
		$client_data = $request->get_json_params();

		if ( empty( $client_data ) || ! is_array( $client_data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid sync data',
				),
				400
			);
		}

		$results = array(
			'processed' => array(),
			'conflicts' => array(),
			'errors'    => array(),
		);

		foreach ( $client_data as $item ) {
			$entity_type = $item['entity_type'] ?? null;
			$action      = $item['action'] ?? null;
			$data        = $item['data'] ?? null;
			$client_id   = $item['client_id'] ?? null;
			$timestamp   = $item['timestamp'] ?? null;

			if ( ! in_array( $entity_type, $this->supported_entities, true ) ) {
				$results['errors'][] = array(
					'client_id' => $client_id,
					'error'     => 'Unsupported entity type',
				);
				continue;
			}

			$result = $this->process_sync_item( $entity_type, $action, $data, $client_id, $timestamp, $user_id );

			if ( $result['conflict'] ) {
				$results['conflicts'][] = $result;
			} elseif ( $result['success'] ) {
				$results['processed'][] = $result;
			} else {
				$results['errors'][] = $result;
			}
		}

		// Get server changes since last sync
		$last_sync      = $request->get_param( 'last_sync' ) ?? 0;
		$server_changes = $this->get_server_changes( $user_id, $last_sync );

		return new WP_REST_Response(
			array(
				'success'        => true,
				'results'        => $results,
				'server_changes' => $server_changes,
				'server_time'    => current_time( 'timestamp' ),
			),
			200
		);
	}

	/**
	 * Process a single sync item.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  string $action      Action (create, update, delete).
	 * @param  array  $data        Entity data.
	 * @param  string $client_id   Client-side ID.
	 * @param  int    $timestamp   Client timestamp.
	 * @param  int    $user_id     User ID.
	 * @return array Result.
	 */
	private function process_sync_item( $entity_type, $action, $data, $client_id, $timestamp, $user_id ) {
		$result = array(
			'client_id' => $client_id,
			'success'   => false,
			'conflict'  => false,
		);

		switch ( $action ) {
			case 'create':
				$server_id = $this->create_entity( $entity_type, $data, $user_id );
				if ( $server_id ) {
					$result['success']   = true;
					$result['server_id'] = $server_id;
				} else {
					$result['error'] = 'Failed to create entity';
				}
				break;

			case 'update':
				$server_id = $data['id'] ?? $data['server_id'] ?? null;
				if ( ! $server_id ) {
					$result['error'] = 'Missing server ID for update';
					break;
				}

				// Check for conflicts
				$server_version = $this->get_entity_version( $entity_type, $server_id );
				$client_version = $data['version'] ?? $timestamp;

				if ( $server_version > $client_version ) {
					// Conflict detected
					$conflict_id           = $this->create_conflict( $entity_type, $server_id, $data, $timestamp, $user_id );
					$result['conflict']    = true;
					$result['conflict_id'] = $conflict_id;
					$result['server_data'] = $this->get_entity( $entity_type, $server_id );
				} else {
					$updated = $this->update_entity( $entity_type, $server_id, $data, $user_id );
					if ( $updated ) {
						$result['success']   = true;
						$result['server_id'] = $server_id;
					} else {
						$result['error'] = 'Failed to update entity';
					}
				}
				break;

			case 'delete':
				$server_id = $data['id'] ?? $data['server_id'] ?? null;
				if ( $server_id ) {
					$deleted           = $this->delete_entity( $entity_type, $server_id, $user_id );
					$result['success'] = $deleted;
				} else {
					$result['error'] = 'Missing server ID for delete';
				}
				break;

			default:
				$result['error'] = 'Unknown action';
		}

		return $result;
	}

	/**
	 * Create entity from offline data.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  array  $data        Entity data.
	 * @param  int    $user_id     User ID.
	 * @return int|false Entity ID or false.
	 */
	private function create_entity( $entity_type, $data, $user_id ) {
		global $wpdb;

		$table = $this->get_table_name( $entity_type );

		if ( ! $table ) {
			return false;
		}

		// Remove client-side fields
		unset( $data['client_id'], $data['_offline'], $data['_synced'] );

		// Add metadata
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		// Add user reference if applicable
		if ( $entity_type === 'time_entries' && empty( $data['technician_id'] ) ) {
			$data['technician_id'] = $user_id;
		}

		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			$entity_id = $wpdb->insert_id;

			// Trigger sync to Zoho
			do_action(
				"ict_{$entity_type}_created",
				$wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entity_id ),
					ARRAY_A
				)
			);

			return $entity_id;
		}

		return false;
	}

	/**
	 * Update entity from offline data.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @param  array  $data        Entity data.
	 * @param  int    $user_id     User ID.
	 * @return bool True on success.
	 */
	private function update_entity( $entity_type, $entity_id, $data, $user_id ) {
		global $wpdb;

		$table = $this->get_table_name( $entity_type );

		if ( ! $table ) {
			return false;
		}

		// Remove meta fields
		unset( $data['id'], $data['client_id'], $data['_offline'], $data['_synced'], $data['version'] );

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $entity_id )
		);

		if ( false !== $result ) {
			// Trigger update action
			do_action(
				"ict_{$entity_type}_updated",
				$wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entity_id ),
					ARRAY_A
				),
				array_keys( $data )
			);

			return true;
		}

		return false;
	}

	/**
	 * Delete entity.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @param  int    $user_id     User ID.
	 * @return bool True on success.
	 */
	private function delete_entity( $entity_type, $entity_id, $user_id ) {
		global $wpdb;

		$table = $this->get_table_name( $entity_type );

		if ( ! $table ) {
			return false;
		}

		// Soft delete if supported, otherwise hard delete
		$entity = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entity_id ),
			ARRAY_A
		);

		if ( ! $entity ) {
			return false;
		}

		// Check if table has deleted_at column (soft delete)
		$columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

		if ( in_array( 'deleted_at', $columns, true ) ) {
			$result = $wpdb->update(
				$table,
				array( 'deleted_at' => current_time( 'mysql' ) ),
				array( 'id' => $entity_id )
			);
		} else {
			$result = $wpdb->delete( $table, array( 'id' => $entity_id ) );
		}

		if ( false !== $result ) {
			do_action( "ict_{$entity_type}_deleted", $entity );
			return true;
		}

		return false;
	}

	/**
	 * Get entity from database.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @return array|null Entity data or null.
	 */
	private function get_entity( $entity_type, $entity_id ) {
		global $wpdb;

		$table = $this->get_table_name( $entity_type );

		if ( ! $table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entity_id ),
			ARRAY_A
		);
	}

	/**
	 * Get entity version (timestamp).
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @return int Timestamp.
	 */
	private function get_entity_version( $entity_type, $entity_id ) {
		global $wpdb;

		$table = $this->get_table_name( $entity_type );

		if ( ! $table ) {
			return 0;
		}

		$updated_at = $wpdb->get_var(
			$wpdb->prepare( "SELECT updated_at FROM {$table} WHERE id = %d", $entity_id )
		);

		return $updated_at ? strtotime( $updated_at ) : 0;
	}

	/**
	 * Get table name for entity type.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @return string|null Table name or null.
	 */
	private function get_table_name( $entity_type ) {
		global $wpdb;

		$tables = array(
			'time_entries' => ICT_TIME_ENTRIES_TABLE,
			'projects'     => ICT_PROJECTS_TABLE,
			'inventory'    => ICT_INVENTORY_ITEMS_TABLE,
			'expenses'     => $wpdb->prefix . 'ict_expenses',
			'tasks'        => $wpdb->prefix . 'ict_tasks',
		);

		return $tables[ $entity_type ] ?? null;
	}

	/**
	 * Create conflict record.
	 *
	 * @since  1.1.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @param  array  $client_data Client data.
	 * @param  int    $timestamp   Client timestamp.
	 * @param  int    $user_id     User ID.
	 * @return int Conflict ID.
	 */
	private function create_conflict( $entity_type, $entity_id, $client_data, $timestamp, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_sync_conflicts';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'client_data' => wp_json_encode( $client_data ),
				'client_time' => date( 'Y-m-d H:i:s', $timestamp ),
				'server_data' => wp_json_encode( $this->get_entity( $entity_type, $entity_id ) ),
				'user_id'     => $user_id,
				'status'      => 'pending',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get pending conflicts for user.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_conflicts( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'ict_sync_conflicts';

		$conflicts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'pending' ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		foreach ( $conflicts as &$conflict ) {
			$conflict['client_data'] = json_decode( $conflict['client_data'], true );
			$conflict['server_data'] = json_decode( $conflict['server_data'], true );
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'conflicts' => $conflicts,
			),
			200
		);
	}

	/**
	 * Resolve conflict.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function resolve_conflict( $request ) {
		global $wpdb;

		$conflict_id = $request->get_param( 'id' );
		$resolution  = $request->get_param( 'resolution' ); // 'client', 'server', or 'merge'
		$merged_data = $request->get_param( 'merged_data' );

		$table    = $wpdb->prefix . 'ict_sync_conflicts';
		$conflict = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $conflict_id ),
			ARRAY_A
		);

		if ( ! $conflict ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Conflict not found',
				),
				404
			);
		}

		$user_id = get_current_user_id();

		if ( (int) $conflict['user_id'] !== $user_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Unauthorized',
				),
				403
			);
		}

		switch ( $resolution ) {
			case 'client':
				$client_data = json_decode( $conflict['client_data'], true );
				$this->update_entity( $conflict['entity_type'], $conflict['entity_id'], $client_data, $user_id );
				break;

			case 'server':
				// Keep server version, no action needed
				break;

			case 'merge':
				if ( $merged_data ) {
					$this->update_entity( $conflict['entity_type'], $conflict['entity_id'], $merged_data, $user_id );
				}
				break;
		}

		// Mark conflict as resolved
		$wpdb->update(
			$table,
			array(
				'status'      => 'resolved',
				'resolution'  => $resolution,
				'resolved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $conflict_id )
		);

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Get server changes since last sync.
	 *
	 * @since  1.1.0
	 * @param  int $user_id   User ID.
	 * @param  int $last_sync Last sync timestamp.
	 * @return array Changes.
	 */
	private function get_server_changes( $user_id, $last_sync ) {
		global $wpdb;

		$changes        = array();
		$last_sync_date = $last_sync ? date( 'Y-m-d H:i:s', $last_sync ) : '1970-01-01 00:00:00';

		// Get time entry changes
		$time_entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . '
				WHERE technician_id = %d AND updated_at > %s',
				$user_id,
				$last_sync_date
			),
			ARRAY_A
		);

		foreach ( $time_entries as $entry ) {
			$changes[] = array(
				'entity_type' => 'time_entries',
				'entity_id'   => $entry['id'],
				'action'      => 'update',
				'data'        => $entry,
			);
		}

		// Get project changes (for assigned projects)
		$assigned_projects = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT project_id FROM ' . ICT_PROJECT_RESOURCES_TABLE . "
				WHERE resource_type = 'user' AND resource_id = %d",
				$user_id
			)
		);

		if ( ! empty( $assigned_projects ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $assigned_projects ), '%d' ) );

			$projects = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . ICT_PROJECTS_TABLE . "
					WHERE id IN ({$placeholders}) AND updated_at > %s",
					array_merge( $assigned_projects, array( $last_sync_date ) )
				),
				ARRAY_A
			);

			foreach ( $projects as $project ) {
				$changes[] = array(
					'entity_type' => 'projects',
					'entity_id'   => $project['id'],
					'action'      => 'update',
					'data'        => $project,
				);
			}
		}

		return $changes;
	}

	/**
	 * Get sync manifest for offline use.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_sync_manifest( $request ) {
		$user_id = get_current_user_id();

		$manifest = array(
			'version'      => ICT_PLATFORM_VERSION,
			'server_time'  => current_time( 'timestamp' ),
			'user_id'      => $user_id,
			'capabilities' => $this->get_user_offline_capabilities( $user_id ),
			'endpoints'    => array(
				'sync'      => rest_url( 'ict/v1/offline/sync' ),
				'conflicts' => rest_url( 'ict/v1/offline/conflicts' ),
			),
			'cache_config' => array(
				'max_age'                => 86400, // 24 hours
				'stale_while_revalidate' => 3600, // 1 hour
			),
			'entities'     => $this->supported_entities,
		);

		return new WP_REST_Response( $manifest, 200 );
	}

	/**
	 * Get user's offline capabilities.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return array Capabilities.
	 */
	private function get_user_offline_capabilities( $user_id ) {
		$capabilities = array();

		if ( user_can( $user_id, 'edit_ict_time_entry' ) ) {
			$capabilities[] = 'time_entries';
		}

		if ( user_can( $user_id, 'view_ict_projects' ) ) {
			$capabilities[] = 'projects';
		}

		if ( user_can( $user_id, 'manage_ict_inventory' ) ) {
			$capabilities[] = 'inventory';
		}

		return $capabilities;
	}

	/**
	 * Process offline queue (cron job).
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function process_offline_queue() {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_offline_queue';

		$items = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT 50",
			ARRAY_A
		);

		foreach ( $items as $item ) {
			$data   = json_decode( $item['data'], true );
			$result = $this->process_sync_item(
				$item['entity_type'],
				$item['action'],
				$data,
				$item['client_id'],
				strtotime( $item['created_at'] ),
				$item['user_id']
			);

			$status = $result['success'] ? 'completed' : ( $result['conflict'] ? 'conflict' : 'failed' );

			$wpdb->update(
				$table,
				array(
					'status'       => $status,
					'result'       => wp_json_encode( $result ),
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item['id'] )
			);
		}
	}
}
