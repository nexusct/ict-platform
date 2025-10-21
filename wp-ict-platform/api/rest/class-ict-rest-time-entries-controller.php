<?php
/**
 * REST API Controller for Time Entries
 *
 * Handles clock in/out, timesheet management, and approval workflows.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ICT_REST_Time_Entries_Controller class.
 *
 * @since 1.0.0
 */
class ICT_REST_Time_Entries_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'ict/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'time-entries';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Constructor can be used for dependency injection if needed.
	}

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// List and create time entries.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		// Get, update, delete single time entry.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		// Clock in.
		register_rest_route(
			$this->namespace,
			'/time/clock-in',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clock_in' ),
				'permission_callback' => array( $this, 'clock_permissions_check' ),
				'args'                => array(
					'project_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'task_type' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'general',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'notes' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'gps_latitude' => array(
						'required'          => false,
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
					'gps_longitude' => array(
						'required'          => false,
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
				),
			)
		);

		// Clock out.
		register_rest_route(
			$this->namespace,
			'/time/clock-out',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clock_out' ),
				'permission_callback' => array( $this, 'clock_permissions_check' ),
				'args'                => array(
					'entry_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'notes' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'gps_latitude' => array(
						'required'          => false,
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
					'gps_longitude' => array(
						'required'          => false,
						'type'              => 'number',
						'sanitize_callback' => 'floatval',
					),
				),
			)
		);

		// Get active time entry.
		register_rest_route(
			$this->namespace,
			'/time/active',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active_entry' ),
				'permission_callback' => array( $this, 'clock_permissions_check' ),
			)
		);

		// Approve time entry.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_item' ),
				'permission_callback' => array( $this, 'approve_permissions_check' ),
				'args'                => array(
					'notes' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Reject time entry.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reject_item' ),
				'permission_callback' => array( $this, 'approve_permissions_check' ),
				'args'                => array(
					'reason' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Sync time entry to Zoho.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_item' ),
				'permission_callback' => array( $this, 'sync_permissions_check' ),
			)
		);
	}

	/**
	 * Get a collection of time entries.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$per_page = $request->get_param( 'per_page' ) ?? 20;
		$page     = $request->get_param( 'page' ) ?? 1;
		$offset   = ( $page - 1 ) * $per_page;
		$status   = $request->get_param( 'status' );
		$project_id = $request->get_param( 'project_id' );
		$technician_id = $request->get_param( 'technician_id' );
		$date_from = $request->get_param( 'date_from' );
		$date_to = $request->get_param( 'date_to' );
		$search = $request->get_param( 'search' );

		// Base query.
		$where = array( '1=1' );
		$query_params = array();

		// Add filters.
		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$query_params[] = $status;
		}

		if ( ! empty( $project_id ) ) {
			$where[] = 'project_id = %d';
			$query_params[] = absint( $project_id );
		}

		if ( ! empty( $technician_id ) ) {
			$where[] = 'technician_id = %d';
			$query_params[] = absint( $technician_id );
		}

		if ( ! empty( $date_from ) ) {
			$where[] = 'clock_in >= %s';
			$query_params[] = sanitize_text_field( $date_from );
		}

		if ( ! empty( $date_to ) ) {
			$where[] = 'clock_in <= %s';
			$query_params[] = sanitize_text_field( $date_to );
		}

		if ( ! empty( $search ) ) {
			$where[] = '(notes LIKE %s OR task_type LIKE %s)';
			$query_params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$query_params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count.
		$count_query = "SELECT COUNT(*) FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE {$where_clause}";
		if ( ! empty( $query_params ) ) {
			$count_query = $wpdb->prepare( $count_query, $query_params );
		}
		$total_items = (int) $wpdb->get_var( $count_query );

		// Get items.
		$query = "SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE {$where_clause} ORDER BY clock_in DESC LIMIT %d OFFSET %d";
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $query, $query_params ), ARRAY_A );

		// Prepare response items.
		$items = array();
		foreach ( $results as $row ) {
			$items[] = $this->prepare_item_for_response( $row, $request );
		}

		$response = rest_ensure_response( $items );

		// Add pagination headers.
		$response->header( 'X-WP-Total', $total_items );
		$response->header( 'X-WP-TotalPages', ceil( $total_items / $per_page ) );

		return $response;
	}

	/**
	 * Get a single time entry.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $result ) ) {
			return new WP_Error(
				'ict_time_entry_not_found',
				__( 'Time entry not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$item = $this->prepare_item_for_response( $result, $request );

		return rest_ensure_response( $item );
	}

	/**
	 * Create a time entry.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_item( $request ) {
		global $wpdb;

		$data = array(
			'project_id'      => absint( $request->get_param( 'project_id' ) ),
			'technician_id'   => get_current_user_id(),
			'task_type'       => sanitize_text_field( $request->get_param( 'task_type' ) ?? 'general' ),
			'clock_in'        => current_time( 'mysql' ),
			'clock_out'       => null,
			'total_hours'     => 0,
			'billable_hours'  => 0,
			'notes'           => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'status'          => 'in-progress',
			'gps_latitude'    => $request->get_param( 'gps_latitude' ),
			'gps_longitude'   => $request->get_param( 'gps_longitude' ),
			'sync_status'     => 'pending',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( ICT_TIME_ENTRIES_TABLE, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ict_time_entry_create_failed',
				__( 'Failed to create time entry.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$id = $wpdb->insert_id;

		// Queue sync to Zoho People.
		$this->queue_sync( $id, 'create' );

		$item = $this->get_item( new WP_REST_Request( 'GET', '/' . $this->rest_base . '/' . $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Time entry created successfully.', 'ict-platform' ),
				'data'    => $item->get_data(),
			)
		);
	}

	/**
	 * Update a time entry.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		// Check if entry exists.
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d", $id ) );
		if ( ! $exists ) {
			return new WP_Error(
				'ict_time_entry_not_found',
				__( 'Time entry not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		// Allow updating specific fields.
		$allowed_fields = array( 'task_type', 'notes', 'billable_hours', 'status' );
		foreach ( $allowed_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$data[ $field ] = sanitize_textarea_field( $request->get_param( $field ) );
			}
		}

		$result = $wpdb->update(
			ICT_TIME_ENTRIES_TABLE,
			$data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_time_entry_update_failed',
				__( 'Failed to update time entry.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		// Queue sync.
		$this->queue_sync( $id, 'update' );

		$item = $this->get_item( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Time entry updated successfully.', 'ict-platform' ),
				'data'    => $item->get_data(),
			)
		);
	}

	/**
	 * Delete a time entry.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		$result = $wpdb->delete(
			ICT_TIME_ENTRIES_TABLE,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result || 0 === $result ) {
			return new WP_Error(
				'ict_time_entry_delete_failed',
				__( 'Failed to delete time entry.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		// Queue sync (delete action).
		$this->queue_sync( $id, 'delete' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Time entry deleted successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Clock in a technician.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function clock_in( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		// Check if user already has an active entry.
		$active_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE technician_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( ! empty( $active_entry ) ) {
			return new WP_Error(
				'ict_already_clocked_in',
				__( 'You already have an active time entry. Please clock out first.', 'ict-platform' ),
				array( 'status' => 400, 'active_entry' => $active_entry )
			);
		}

		$data = array(
			'project_id'      => absint( $request->get_param( 'project_id' ) ),
			'technician_id'   => $user_id,
			'task_type'       => sanitize_text_field( $request->get_param( 'task_type' ) ?? 'general' ),
			'clock_in'        => current_time( 'mysql' ),
			'clock_out'       => null,
			'total_hours'     => 0,
			'billable_hours'  => 0,
			'notes'           => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'status'          => 'in-progress',
			'location_in'     => null,
			'sync_status'     => 'pending',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		// Combine GPS into a single validated location string if provided
		if ( null !== $request->get_param( 'gps_latitude' ) && null !== $request->get_param( 'gps_longitude' ) ) {
			$coords = $request->get_param( 'gps_latitude' ) . ',' . $request->get_param( 'gps_longitude' );
			$sanitized = ICT_Helper::sanitize_coordinates( $coords );
			if ( null === $sanitized ) {
				return new WP_Error( 'invalid_gps', __( 'Invalid coordinates.', 'ict-platform' ), array( 'status' => 400 ) );
			}
			$data['location_in'] = $sanitized;
		}

		$result = $wpdb->insert( ICT_TIME_ENTRIES_TABLE, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ict_clock_in_failed',
				__( 'Failed to clock in.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$id = $wpdb->insert_id;

		// Queue sync.
		$this->queue_sync( $id, 'create' );

		$entry = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d", $id ),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Clocked in successfully.', 'ict-platform' ),
				'data'    => $this->prepare_item_for_response( $entry, $request ),
			)
		);
	}

	/**
	 * Clock out a technician.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function clock_out( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$entry_id = $request->get_param( 'entry_id' );

		// If entry_id provided, use it; otherwise find the active entry.
		if ( $entry_id ) {
			$entry = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d AND technician_id = %d AND clock_out IS NULL",
					absint( $entry_id ),
					$user_id
				),
				ARRAY_A
			);
		} else {
			$entry = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE technician_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
					$user_id
				),
				ARRAY_A
			);
		}

		if ( empty( $entry ) ) {
			return new WP_Error(
				'ict_no_active_entry',
				__( 'No active time entry found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$clock_out = current_time( 'mysql' );
		$clock_in_time = strtotime( $entry['clock_in'] );
		$clock_out_time = strtotime( $clock_out );
		$total_hours = round( ( $clock_out_time - $clock_in_time ) / 3600, 2 );

		$data = array(
			'clock_out'       => $clock_out,
			'total_hours'     => $total_hours,
			'billable_hours'  => $total_hours, // Default to same as total.
			'status'          => 'submitted',
			'location_out'    => null,
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( null !== $request->get_param( 'gps_latitude' ) && null !== $request->get_param( 'gps_longitude' ) ) {
			$coords = $request->get_param( 'gps_latitude' ) . ',' . $request->get_param( 'gps_longitude' );
			$sanitized = ICT_Helper::sanitize_coordinates( $coords );
			if ( null === $sanitized ) {
				return new WP_Error( 'invalid_gps', __( 'Invalid coordinates.', 'ict-platform' ), array( 'status' => 400 ) );
			}
			$data['location_out'] = $sanitized;
		}

		// Append notes if provided.
		if ( $request->has_param( 'notes' ) ) {
			$existing_notes = $entry['notes'] ?? '';
			$new_notes = sanitize_textarea_field( $request->get_param( 'notes' ) );
			$data['notes'] = trim( $existing_notes . "\n" . $new_notes );
		}

		$result = $wpdb->update(
			ICT_TIME_ENTRIES_TABLE,
			$data,
			array( 'id' => $entry['id'] )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_clock_out_failed',
				__( 'Failed to clock out.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		// Queue sync.
		$this->queue_sync( $entry['id'], 'update' );

		$updated_entry = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d", $entry['id'] ),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Clocked out successfully.', 'ict-platform' ),
				'data'    => $this->prepare_item_for_response( $updated_entry, $request ),
			)
		);
	}

	/**
	 * Get active time entry for current user.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_active_entry( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE technician_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( empty( $entry ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => null,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->prepare_item_for_response( $entry, $request ),
			)
		);
	}

	/**
	 * Approve a time entry.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function approve_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		$data = array(
			'status'      => 'approved',
			'approved_by' => get_current_user_id(),
			'approved_at' => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		);

		if ( $request->has_param( 'notes' ) ) {
			$data['approval_notes'] = sanitize_textarea_field( $request->get_param( 'notes' ) );
		}

		$result = $wpdb->update(
			ICT_TIME_ENTRIES_TABLE,
			$data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_approve_failed',
				__( 'Failed to approve time entry.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		// Queue sync to Zoho.
		$this->queue_sync( $id, 'update' );

		$item = $this->get_item( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Time entry approved successfully.', 'ict-platform' ),
				'data'    => $item->get_data(),
			)
		);
	}

	/**
	 * Reject a time entry.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function reject_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		$data = array(
			'status'         => 'rejected',
			'approved_by'    => get_current_user_id(),
			'approved_at'    => current_time( 'mysql' ),
			'approval_notes' => sanitize_textarea_field( $request->get_param( 'reason' ) ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$result = $wpdb->update(
			ICT_TIME_ENTRIES_TABLE,
			$data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_reject_failed',
				__( 'Failed to reject time entry.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item = $this->get_item( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Time entry rejected successfully.', 'ict-platform' ),
				'data'    => $item->get_data(),
			)
		);
	}

	/**
	 * Sync time entry to Zoho.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function sync_item( $request ) {
		$id = absint( $request['id'] );

		$this->queue_sync( $id, 'update' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Sync queued successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Prepare item for response.
	 *
	 * @param array           $item    Raw database row.
	 * @param WP_REST_Request $request Request object.
	 * @return array Prepared item.
	 */
	protected function prepare_item_for_response( $item, $request ) {
		return array(
			'id'                => (int) $item['id'],
			'project_id'        => (int) $item['project_id'],
			'technician_id'     => (int) $item['technician_id'],
			'zoho_people_id'    => $item['zoho_people_id'],
			'task_type'         => $item['task_type'],
			'clock_in'          => $item['clock_in'],
			'clock_out'         => $item['clock_out'],
			'total_hours'       => (float) $item['total_hours'],
			'billable_hours'    => (float) $item['billable_hours'],
			'hourly_rate'       => (float) $item['hourly_rate'],
			'notes'             => $item['notes'],
			'status'            => $item['status'],
			'approved_by'       => $item['approved_by'] ? (int) $item['approved_by'] : null,
			'approved_at'       => $item['approved_at'],
			'approval_notes'    => $item['approval_notes'],
			'gps_latitude'      => $item['gps_latitude'] ? (float) $item['gps_latitude'] : null,
			'gps_longitude'     => $item['gps_longitude'] ? (float) $item['gps_longitude'] : null,
			'gps_latitude_out'  => $item['gps_latitude_out'] ? (float) $item['gps_latitude_out'] : null,
			'gps_longitude_out' => $item['gps_longitude_out'] ? (float) $item['gps_longitude_out'] : null,
			'sync_status'       => $item['sync_status'],
			'last_synced_at'    => $item['last_synced_at'],
			'created_at'        => $item['created_at'],
			'updated_at'        => $item['updated_at'],
		);
	}

	/**
	 * Queue sync to Zoho.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $action   Action type (create, update, delete).
	 */
	protected function queue_sync( $entry_id, $action ) {
		global $wpdb;

		$wpdb->insert(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'entity_type'  => 'time_entry',
				'entity_id'    => $entry_id,
				'action'       => $action,
				'zoho_service' => 'people',
				'priority'     => 'medium',
				'status'       => 'pending',
				'created_at'   => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Check permissions for getting items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'read_ict_time_entries' );
	}

	/**
	 * Check permissions for getting a single item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'read_ict_time_entries' );
	}

	/**
	 * Check permissions for creating items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'create_ict_time_entries' );
	}

	/**
	 * Check permissions for updating items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'edit_ict_time_entries' );
	}

	/**
	 * Check permissions for deleting items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'delete_ict_time_entries' );
	}

	/**
	 * Check permissions for clock in/out.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function clock_permissions_check( $request ) {
		return is_user_logged_in() && current_user_can( 'create_ict_time_entries' );
	}

	/**
	 * Check permissions for approving time entries.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function approve_permissions_check( $request ) {
		return current_user_can( 'approve_ict_time_entries' );
	}

	/**
	 * Check permissions for syncing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function sync_permissions_check( $request ) {
		return current_user_can( 'manage_ict_sync' );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'per_page' => array(
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
			'page' => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'status' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'project_id' => array(
				'sanitize_callback' => 'absint',
			),
			'technician_id' => array(
				'sanitize_callback' => 'absint',
			),
			'date_from' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'search' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
