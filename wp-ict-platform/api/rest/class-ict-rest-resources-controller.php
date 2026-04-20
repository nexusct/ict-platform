<?php
/**
 * REST API Controller for Resource Management
 *
 * Handles resource allocation, availability, and scheduling.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ICT_REST_Resources_Controller class.
 *
 * @since 1.0.0
 */
class ICT_REST_Resources_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'resources';

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
		// List and create resource allocations.
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

		// Get, update, delete single resource allocation.
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

		// Get availability for a resource type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/availability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_availability' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'resource_type' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_from'     => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'       => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Check for conflicts.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check-conflicts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_conflicts' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'resource_type'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'resource_id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'allocation_start' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'allocation_end'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'exclude_id'       => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Get calendar events.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar_events' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'start'         => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'resource_type' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get technician skills.
		register_rest_route(
			$this->namespace,
			'/technicians/(?P<id>[\d]+)/skills',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_technician_skills' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Update technician skills.
		register_rest_route(
			$this->namespace,
			'/technicians/(?P<id>[\d]+)/skills',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_technician_skills' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'skills' => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => array( $this, 'sanitize_skills' ),
					),
				),
			)
		);
	}

	/**
	 * Get a collection of resource allocations.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$per_page = $request->get_param( 'per_page' ) ?? 20;
		$page     = $request->get_param( 'page' ) ?? 1;
		$offset   = ( $page - 1 ) * $per_page;

		$project_id    = $request->get_param( 'project_id' );
		$resource_type = $request->get_param( 'resource_type' );
		$resource_id   = $request->get_param( 'resource_id' );
		$status        = $request->get_param( 'status' );
		$date_from     = $request->get_param( 'date_from' );
		$date_to       = $request->get_param( 'date_to' );

		// Base query.
		$where        = array( '1=1' );
		$query_params = array();

		// Add filters.
		if ( ! empty( $project_id ) ) {
			$where[]        = 'project_id = %d';
			$query_params[] = absint( $project_id );
		}

		if ( ! empty( $resource_type ) ) {
			$where[]        = 'resource_type = %s';
			$query_params[] = $resource_type;
		}

		if ( ! empty( $resource_id ) ) {
			$where[]        = 'resource_id = %d';
			$query_params[] = absint( $resource_id );
		}

		if ( ! empty( $status ) ) {
			$where[]        = 'status = %s';
			$query_params[] = $status;
		}

		if ( ! empty( $date_from ) ) {
			$where[]        = 'allocation_end >= %s';
			$query_params[] = sanitize_text_field( $date_from );
		}

		if ( ! empty( $date_to ) ) {
			$where[]        = 'allocation_start <= %s';
			$query_params[] = sanitize_text_field( $date_to );
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count.
		$count_query = 'SELECT COUNT(*) FROM ' . ICT_PROJECT_RESOURCES_TABLE . " WHERE {$where_clause}";
		if ( ! empty( $query_params ) ) {
			$count_query = $wpdb->prepare( $count_query, $query_params );
		}
		$total_items = (int) $wpdb->get_var( $count_query );

		// Get items.
		$query          = 'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . " WHERE {$where_clause} ORDER BY allocation_start DESC LIMIT %d OFFSET %d";
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
	 * Get a single resource allocation.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		$result = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		if ( empty( $result ) ) {
			return new WP_Error(
				'ict_resource_not_found',
				__( 'Resource allocation not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$item = $this->prepare_item_for_response( $result, $request );

		return rest_ensure_response( $item );
	}

	/**
	 * Create a resource allocation.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_item( $request ) {
		global $wpdb;

		// Check for conflicts.
		$conflicts = $this->find_conflicts(
			$request->get_param( 'resource_type' ),
			$request->get_param( 'resource_id' ),
			$request->get_param( 'allocation_start' ),
			$request->get_param( 'allocation_end' )
		);

		if ( ! empty( $conflicts ) ) {
			return new WP_Error(
				'ict_resource_conflict',
				__( 'Resource allocation conflicts with existing allocations.', 'ict-platform' ),
				array(
					'status'    => 400,
					'conflicts' => $conflicts,
				)
			);
		}

		$data = array(
			'project_id'            => absint( $request->get_param( 'project_id' ) ),
			'resource_type'         => sanitize_text_field( $request->get_param( 'resource_type' ) ),
			'resource_id'           => absint( $request->get_param( 'resource_id' ) ),
			'allocation_start'      => sanitize_text_field( $request->get_param( 'allocation_start' ) ),
			'allocation_end'        => sanitize_text_field( $request->get_param( 'allocation_end' ) ),
			'allocation_percentage' => floatval( $request->get_param( 'allocation_percentage' ) ?? 100 ),
			'estimated_hours'       => floatval( $request->get_param( 'estimated_hours' ) ?? 0 ),
			'actual_hours'          => 0,
			'status'                => sanitize_text_field( $request->get_param( 'status' ) ?? 'scheduled' ),
			'notes'                 => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'created_at'            => current_time( 'mysql' ),
			'updated_at'            => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( ICT_PROJECT_RESOURCES_TABLE, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ict_resource_create_failed',
				__( 'Failed to create resource allocation.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$id   = $wpdb->insert_id;
		$item = $this->get_item( new WP_REST_Request( 'GET', '/' . $this->rest_base . '/' . $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Resource allocation created successfully.', 'ict-platform' ),
				'data'    => $item->get_data(),
			)
		);
	}

	/**
	 * Update a resource allocation.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		// Check if exists.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE id = %d', $id ) );
		if ( ! $exists ) {
			return new WP_Error(
				'ict_resource_not_found',
				__( 'Resource allocation not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		// Check for conflicts if dates are being changed.
		if ( $request->has_param( 'allocation_start' ) || $request->has_param( 'allocation_end' ) ) {
			$current = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE id = %d', $id ),
				ARRAY_A
			);

			$start = $request->get_param( 'allocation_start' ) ?? $current['allocation_start'];
			$end   = $request->get_param( 'allocation_end' ) ?? $current['allocation_end'];

			$conflicts = $this->find_conflicts(
				$current['resource_type'],
				$current['resource_id'],
				$start,
				$end,
				$id
			);

			if ( ! empty( $conflicts ) ) {
				return new WP_Error(
					'ict_resource_conflict',
					__( 'Resource allocation conflicts with existing allocations.', 'ict-platform' ),
					array(
						'status'    => 400,
						'conflicts' => $conflicts,
					)
				);
			}
		}

		$data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		// Allow updating specific fields.
		$allowed_fields = array(
			'allocation_start',
			'allocation_end',
			'allocation_percentage',
			'estimated_hours',
			'actual_hours',
			'status',
			'notes',
		);

		foreach ( $allowed_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$value = $request->get_param( $field );
				if ( in_array( $field, array( 'allocation_percentage', 'estimated_hours', 'actual_hours' ), true ) ) {
					$data[ $field ] = floatval( $value );
				} elseif ( in_array( $field, array( 'allocation_start', 'allocation_end', 'status' ), true ) ) {
					$data[ $field ] = sanitize_text_field( $value );
				} else {
					$data[ $field ] = sanitize_textarea_field( $value );
				}
			}
		}

		$result = $wpdb->update(
			ICT_PROJECT_RESOURCES_TABLE,
			$data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_resource_update_failed',
				__( 'Failed to update resource allocation.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item = $this->get_item( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Resource allocation updated successfully.', 'ict-platform' ),
				'data'    => $item->get_data(),
			)
		);
	}

	/**
	 * Delete a resource allocation.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$id = absint( $request['id'] );

		$result = $wpdb->delete(
			ICT_PROJECT_RESOURCES_TABLE,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result || 0 === $result ) {
			return new WP_Error(
				'ict_resource_delete_failed',
				__( 'Failed to delete resource allocation.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Resource allocation deleted successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Get availability for resources.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response Response object.
	 */
	public function get_availability( $request ) {
		global $wpdb;

		$resource_type = $request->get_param( 'resource_type' );
		$date_from     = $request->get_param( 'date_from' ) ?? current_time( 'mysql' );
		$date_to       = $request->get_param( 'date_to' ) ?? date( 'Y-m-d H:i:s', strtotime( '+30 days' ) );

		// Get all resources of the specified type.
		$resources = array();

		if ( 'technician' === $resource_type || empty( $resource_type ) ) {
			$users = get_users( array( 'role__in' => array( 'ict_technician', 'ict_project_manager' ) ) );
			foreach ( $users as $user ) {
				$resources[] = array(
					'id'    => $user->ID,
					'type'  => 'technician',
					'name'  => $user->display_name,
					'email' => $user->user_email,
				);
			}
		}

		// Get allocations for the date range.
		$where  = 'allocation_start <= %s AND allocation_end >= %s';
		$params = array( $date_to, $date_from );

		if ( ! empty( $resource_type ) ) {
			$where   .= ' AND resource_type = %s';
			$params[] = $resource_type;
		}

		$allocations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . " WHERE {$where} ORDER BY allocation_start",
				$params
			),
			ARRAY_A
		);

		// Calculate availability for each resource.
		$availability = array();
		foreach ( $resources as $resource ) {
			$resource_allocations = array_filter(
				$allocations,
				function ( $alloc ) use ( $resource ) {
					return $alloc['resource_id'] === $resource['id'] && $alloc['resource_type'] === $resource['type'];
				}
			);

			$total_allocation = array_sum( array_column( $resource_allocations, 'allocation_percentage' ) );

			$availability[] = array(
				'resource'          => $resource,
				'allocations'       => array_values( $resource_allocations ),
				'total_allocated'   => $total_allocation,
				'available_percent' => max( 0, 100 - $total_allocation ),
				'is_available'      => $total_allocation < 100,
			);
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'date_from'    => $date_from,
				'date_to'      => $date_to,
				'availability' => $availability,
			)
		);
	}

	/**
	 * Check for conflicts.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response Response object.
	 */
	public function check_conflicts( $request ) {
		$conflicts = $this->find_conflicts(
			$request->get_param( 'resource_type' ),
			$request->get_param( 'resource_id' ),
			$request->get_param( 'allocation_start' ),
			$request->get_param( 'allocation_end' ),
			$request->get_param( 'exclude_id' )
		);

		return rest_ensure_response(
			array(
				'success'      => true,
				'conflicts'    => $conflicts,
				'has_conflict' => ! empty( $conflicts ),
			)
		);
	}

	/**
	 * Get calendar events.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response Response object.
	 */
	public function get_calendar_events( $request ) {
		global $wpdb;

		$start         = sanitize_text_field( $request->get_param( 'start' ) );
		$end           = sanitize_text_field( $request->get_param( 'end' ) );
		$resource_type = $request->get_param( 'resource_type' );

		$where  = 'allocation_start <= %s AND allocation_end >= %s';
		$params = array( $end, $start );

		if ( ! empty( $resource_type ) ) {
			$where   .= ' AND resource_type = %s';
			$params[] = $resource_type;
		}

		$allocations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . " WHERE {$where}",
				$params
			),
			ARRAY_A
		);

		// Format for FullCalendar.
		$events = array();
		foreach ( $allocations as $allocation ) {
			$project = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d', $allocation['project_id'] ),
				ARRAY_A
			);

			$color_map = array(
				'scheduled' => '#2271b1',
				'active'    => '#00a32a',
				'completed' => '#646970',
				'cancelled' => '#d63638',
			);

			$events[] = array(
				'id'              => $allocation['id'],
				'title'           => ( $project['project_name'] ?? 'Unknown Project' ) . ' (' . $allocation['allocation_percentage'] . '%)',
				'start'           => $allocation['allocation_start'],
				'end'             => $allocation['allocation_end'],
				'backgroundColor' => $color_map[ $allocation['status'] ] ?? '#646970',
				'borderColor'     => $color_map[ $allocation['status'] ] ?? '#646970',
				'extendedProps'   => array(
					'project_id'            => $allocation['project_id'],
					'resource_type'         => $allocation['resource_type'],
					'resource_id'           => $allocation['resource_id'],
					'allocation_percentage' => $allocation['allocation_percentage'],
					'estimated_hours'       => $allocation['estimated_hours'],
					'actual_hours'          => $allocation['actual_hours'],
					'status'                => $allocation['status'],
					'notes'                 => $allocation['notes'],
				),
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'events'  => $events,
			)
		);
	}

	/**
	 * Get technician skills.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response Response object.
	 */
	public function get_technician_skills( $request ) {
		$user_id = absint( $request['id'] );
		$skills  = get_user_meta( $user_id, 'ict_skills', true );

		if ( empty( $skills ) ) {
			$skills = array();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'user_id' => $user_id,
				'skills'  => $skills,
			)
		);
	}

	/**
	 * Update technician skills.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response Response object.
	 */
	public function update_technician_skills( $request ) {
		$user_id = absint( $request['id'] );
		$skills  = $request->get_param( 'skills' );

		update_user_meta( $user_id, 'ict_skills', $skills );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Skills updated successfully.', 'ict-platform' ),
				'user_id' => $user_id,
				'skills'  => $skills,
			)
		);
	}

	/**
	 * Find conflicts for a resource allocation.
	 *
	 * @param string $resource_type Resource type.
	 * @param int    $resource_id   Resource ID.
	 * @param string $start         Start date.
	 * @param string $end           End date.
	 * @param int    $exclude_id    ID to exclude (for updates).
	 * @return array Conflicting allocations.
	 */
	protected function find_conflicts( $resource_type, $resource_id, $start, $end, $exclude_id = 0 ) {
		global $wpdb;

		$where = 'resource_type = %s AND resource_id = %d AND
		         ((allocation_start BETWEEN %s AND %s) OR
		          (allocation_end BETWEEN %s AND %s) OR
		          (allocation_start <= %s AND allocation_end >= %s))';

		$params = array(
			$resource_type,
			$resource_id,
			$start,
			$end,
			$start,
			$end,
			$start,
			$end,
		);

		if ( $exclude_id > 0 ) {
			$where   .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		$conflicts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . " WHERE {$where}",
				$params
			),
			ARRAY_A
		);

		return $conflicts;
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
			'id'                    => (int) $item['id'],
			'project_id'            => (int) $item['project_id'],
			'resource_type'         => $item['resource_type'],
			'resource_id'           => (int) $item['resource_id'],
			'allocation_start'      => $item['allocation_start'],
			'allocation_end'        => $item['allocation_end'],
			'allocation_percentage' => (float) $item['allocation_percentage'],
			'estimated_hours'       => (float) $item['estimated_hours'],
			'actual_hours'          => (float) $item['actual_hours'],
			'status'                => $item['status'],
			'notes'                 => $item['notes'],
			'created_at'            => $item['created_at'],
			'updated_at'            => $item['updated_at'],
		);
	}

	/**
	 * Sanitize skills array.
	 *
	 * @param mixed $skills Skills to sanitize.
	 * @return array Sanitized skills.
	 */
	public function sanitize_skills( $skills ) {
		if ( ! is_array( $skills ) ) {
			return array();
		}

		return array_map(
			function ( $skill ) {
				return array(
					'name'  => sanitize_text_field( $skill['name'] ?? '' ),
					'level' => sanitize_text_field( $skill['level'] ?? 'beginner' ),
					'years' => absint( $skill['years'] ?? 0 ),
				);
			},
			$skills
		);
	}

	/**
	 * Check permissions for getting items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'read_ict_resources' );
	}

	/**
	 * Check permissions for getting a single item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'read_ict_resources' );
	}

	/**
	 * Check permissions for creating items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'create_ict_resources' );
	}

	/**
	 * Check permissions for updating items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'edit_ict_resources' );
	}

	/**
	 * Check permissions for deleting items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'delete_ict_resources' );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'per_page'      => array(
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
			'page'          => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'project_id'    => array(
				'sanitize_callback' => 'absint',
			),
			'resource_type' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'resource_id'   => array(
				'sanitize_callback' => 'absint',
			),
			'status'        => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from'     => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'       => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
