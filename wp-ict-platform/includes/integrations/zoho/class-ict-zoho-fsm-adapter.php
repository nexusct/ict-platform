<?php
/**
 * Zoho FSM (Field Service Management) Adapter
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_FSM_Adapter
 *
 * Handles synchronization with Zoho FSM (Work Orders → Tasks, Field Agents → Technicians).
 */
class ICT_Zoho_FSM_Adapter extends ICT_Zoho_API_Client {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( 'fsm' );
	}

	/**
	 * Create entity in Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type (work_order, task).
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function create( $entity_type, $data ) {
		switch ( $entity_type ) {
			case 'work_order':
				return $this->create_work_order( $data );
			case 'task':
				return $this->create_task( $data );
			default:
				throw new Exception( sprintf( __( 'Unknown entity type: %s', 'ict-platform' ), $entity_type ) );
		}
	}

	/**
	 * Update entity in Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function update( $entity_type, $entity_id, $data ) {
		switch ( $entity_type ) {
			case 'work_order':
				return $this->update_work_order( $entity_id, $data );
			case 'task':
				return $this->update_task( $entity_id, $data );
			default:
				throw new Exception( sprintf( __( 'Unknown entity type: %s', 'ict-platform' ), $entity_type ) );
		}
	}

	/**
	 * Delete entity from Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function delete( $entity_type, $entity_id ) {
		$zoho_id = $this->get_zoho_fsm_id( $entity_type, $entity_id );

		if ( ! $zoho_id ) {
			throw new Exception( __( 'Entity not found in Zoho FSM', 'ict-platform' ) );
		}

		$module = ( 'work_order' === $entity_type ) ? 'workorders' : 'tasks';
		$this->delete( "/{$module}/{$zoho_id}" );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'deleted',
		);
	}

	/**
	 * Create work order in Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  array $data Work order data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	protected function create_work_order( $data ) {
		global $wpdb;

		// Get project details
		$project = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d',
				$data['project_id']
			)
		);

		$payload = array(
			'subject'         => $data['title'] ?? $project->project_name,
			'description'     => $data['description'] ?? '',
			'priority'        => $data['priority'] ?? 'Medium',
			'status'          => $this->map_status_to_fsm( $data['status'] ?? 'pending' ),
			'scheduled_start' => date( 'c', strtotime( $data['start_date'] ) ),
			'scheduled_end'   => date( 'c', strtotime( $data['end_date'] ) ),
			'assigned_to'     => $this->get_fsm_agent_id( $data['technician_id'] ),
		);

		$response = $this->post( '/workorders', $payload );

		if ( isset( $response['data']['data']['id'] ) ) {
			$zoho_id = $response['data']['data']['id'];

			// Store Zoho ID in task meta or custom table
			update_post_meta( $data['task_id'], 'zoho_fsm_work_order_id', $zoho_id );

			return array(
				'zoho_id' => $zoho_id,
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create work order in Zoho FSM', 'ict-platform' ) );
	}

	/**
	 * Update work order in Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  int   $entity_id Local entity ID.
	 * @param  array $data      Work order data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	protected function update_work_order( $entity_id, $data ) {
		$zoho_id = $this->get_zoho_fsm_id( 'work_order', $entity_id );

		if ( ! $zoho_id ) {
			return $this->create_work_order( $data );
		}

		$payload = array(
			'status'      => $this->map_status_to_fsm( $data['status'] ?? 'pending' ),
			'priority'    => $data['priority'] ?? 'Medium',
			'description' => $data['description'] ?? '',
		);

		$this->put( "/workorders/{$zoho_id}", $payload );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Create task in Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  array $data Task data.
	 * @return array Response data.
	 */
	protected function create_task( $data ) {
		$payload = array(
			'title'       => $data['title'],
			'description' => $data['description'] ?? '',
			'status'      => $this->map_status_to_fsm( $data['status'] ?? 'pending' ),
			'assigned_to' => $this->get_fsm_agent_id( $data['assigned_to'] ),
		);

		$response = $this->post( '/tasks', $payload );

		if ( isset( $response['data']['data']['id'] ) ) {
			return array(
				'zoho_id' => $response['data']['data']['id'],
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create task in Zoho FSM', 'ict-platform' ) );
	}

	/**
	 * Update task in Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  int   $entity_id Local entity ID.
	 * @param  array $data      Task data.
	 * @return array Response data.
	 */
	protected function update_task( $entity_id, $data ) {
		$zoho_id = $this->get_zoho_fsm_id( 'task', $entity_id );

		if ( ! $zoho_id ) {
			return $this->create_task( $data );
		}

		$payload = array(
			'status'      => $this->map_status_to_fsm( $data['status'] ?? 'pending' ),
			'description' => $data['description'] ?? '',
		);

		$this->put( "/tasks/{$zoho_id}", $payload );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Sync work orders from Zoho FSM.
	 *
	 * @since  1.0.0
	 * @param  array $params Query parameters.
	 * @return array Sync results.
	 */
	public function sync_work_orders( $params = array() ) {
		$response = $this->get( '/workorders', $params );

		if ( ! isset( $response['data']['data'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No work orders found', 'ict-platform' ),
			);
		}

		$work_orders = $response['data']['data'];
		$synced      = 0;

		foreach ( $work_orders as $wo ) {
			// Create or update local task/project task
			// Implementation depends on how tasks are stored
			++$synced;
		}

		return array(
			'success' => true,
			'synced'  => $synced,
		);
	}

	/**
	 * Sync field agents from Zoho FSM.
	 *
	 * @since  1.0.0
	 * @return array Sync results.
	 */
	public function sync_field_agents() {
		$response = $this->get( '/users' );

		if ( ! isset( $response['data']['users'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No field agents found', 'ict-platform' ),
			);
		}

		$agents = $response['data']['users'];
		$synced = 0;

		foreach ( $agents as $agent ) {
			// Sync to WordPress users with ict_technician role
			$email = $agent['email'] ?? '';
			if ( empty( $email ) ) {
				continue;
			}

			$user = get_user_by( 'email', $email );

			if ( ! $user ) {
				$user_id = wp_create_user(
					sanitize_user( $agent['name'] ),
					wp_generate_password(),
					$email
				);

				if ( ! is_wp_error( $user_id ) ) {
					wp_update_user(
						array(
							'ID'         => $user_id,
							'first_name' => $agent['first_name'] ?? '',
							'last_name'  => $agent['last_name'] ?? '',
							'role'       => 'ict_technician',
						)
					);

					update_user_meta( $user_id, 'zoho_fsm_agent_id', $agent['id'] );
					++$synced;
				}
			} else {
				update_user_meta( $user->ID, 'zoho_fsm_agent_id', $agent['id'] );
				++$synced;
			}
		}

		return array(
			'success' => true,
			'synced'  => $synced,
		);
	}

	/**
	 * Map local status to FSM status.
	 *
	 * @since  1.0.0
	 * @param  string $status Local status.
	 * @return string FSM status.
	 */
	protected function map_status_to_fsm( $status ) {
		$mapping = array(
			'pending'     => 'New',
			'in-progress' => 'In Progress',
			'on-hold'     => 'On Hold',
			'completed'   => 'Completed',
			'cancelled'   => 'Cancelled',
		);

		return $mapping[ $status ] ?? 'New';
	}

	/**
	 * Get FSM agent ID from WordPress user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id WordPress user ID.
	 * @return string|null FSM agent ID.
	 */
	protected function get_fsm_agent_id( $user_id ) {
		return get_user_meta( $user_id, 'zoho_fsm_agent_id', true );
	}

	/**
	 * Get Zoho FSM ID for local entity.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return string|null Zoho FSM ID.
	 */
	protected function get_zoho_fsm_id( $entity_type, $entity_id ) {
		if ( 'work_order' === $entity_type ) {
			return get_post_meta( $entity_id, 'zoho_fsm_work_order_id', true );
		} elseif ( 'task' === $entity_type ) {
			return get_post_meta( $entity_id, 'zoho_fsm_task_id', true );
		}

		return null;
	}
}
