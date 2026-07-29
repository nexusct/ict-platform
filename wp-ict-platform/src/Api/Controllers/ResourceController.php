<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Resource REST API Controller
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class ResourceController extends AbstractController {

	protected string $rest_base = 'resources';

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItems' ),
				'permission_callback' => array( $this, 'getItemsPermissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/allocations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAllocations' ),
				'permission_callback' => array( $this, 'getItemsPermissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/allocations',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createAllocation' ),
				'permission_callback' => array( $this, 'createItemPermissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/allocations/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateAllocation' ),
				'permission_callback' => array( $this, 'updateItemPermissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/allocations/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'deleteAllocation' ),
				'permission_callback' => array( $this, 'deleteItemPermissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getCalendarEvents' ),
				'permission_callback' => array( $this, 'getItemsPermissionsCheck' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conflicts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'checkConflicts' ),
				'permission_callback' => array( $this, 'getItemsPermissionsCheck' ),
			)
		);
	}

	public function getItems( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$type = $request->get_param( 'type' );

		$resources = array();

		// Get technicians
		if ( ! $type || $type === 'technician' ) {
			$users = get_users( array( 'role__in' => array( 'ict_technician', 'ict_project_manager' ) ) );
			foreach ( $users as $user ) {
				$resources[] = array(
					'id'    => $user->ID,
					'type'  => 'technician',
					'name'  => $this->helper->getUserDisplayName( $user->ID ),
					'email' => $user->user_email,
				);
			}
		}

		return $this->success( $resources );
	}

	public function getAllocations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$pagination   = $this->getPaginationParams( $request );
		$projectId    = (int) $request->get_param( 'project_id' );
		$resourceType = $request->get_param( 'resource_type' );

		$where  = array( '1=1' );
		$values = array();

		if ( $projectId ) {
			$where[]  = 'project_id = %d';
			$values[] = $projectId;
		}

		if ( $resourceType ) {
			$where[]  = 'resource_type = %s';
			$values[] = $resourceType;
		}

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE ' . implode( ' AND ', $where ),
				$values
			)
		);

		$values[] = $pagination['per_page'];
		$values[] = $pagination['offset'];

		$allocations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . '
            WHERE ' . implode( ' AND ', $where ) . '
            ORDER BY allocation_start ASC LIMIT %d OFFSET %d',
				$values
			)
		);

		return $this->paginated( array_map( array( $this, 'prepareAllocation' ), $allocations ), $total, $pagination['page'], $pagination['per_page'] );
	}

	public function createAllocation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$data = array(
			'project_id'       => (int) $request->get_param( 'project_id' ),
			'resource_type'    => sanitize_text_field( $request->get_param( 'resource_type' ) ),
			'resource_id'      => (int) $request->get_param( 'resource_id' ),
			'allocation_start' => sanitize_text_field( $request->get_param( 'allocation_start' ) ),
			'allocation_end'   => sanitize_text_field( $request->get_param( 'allocation_end' ) ),
			'hours_allocated'  => (float) ( $request->get_param( 'hours_allocated' ) ?? 0 ),
			'status'           => 'scheduled',
		);

		$result = $wpdb->insert( ICT_PROJECT_RESOURCES_TABLE, $data );

		if ( ! $result ) {
			return $this->error( 'create_failed', __( 'Failed to create allocation.', 'ict-platform' ), 500 );
		}

		$allocation = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE id = %d',
				$wpdb->insert_id
			)
		);

		return $this->success( $this->prepareAllocation( $allocation ), 201 );
	}

	public function updateAllocation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id   = (int) $request->get_param( 'id' );
		$data = array();

		foreach ( array( 'allocation_start', 'allocation_end', 'hours_allocated', 'status' ) as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $field === 'hours_allocated' ? (float) $value : sanitize_text_field( $value );
			}
		}

		if ( empty( $data ) ) {
			return $this->error( 'no_data', __( 'No data to update.', 'ict-platform' ), 400 );
		}

		$wpdb->update( ICT_PROJECT_RESOURCES_TABLE, $data, array( 'id' => $id ) );

		$allocation = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE id = %d', $id ) );

		return $this->success( $this->prepareAllocation( $allocation ) );
	}

	public function deleteAllocation( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$result = $wpdb->delete( ICT_PROJECT_RESOURCES_TABLE, array( 'id' => (int) $request->get_param( 'id' ) ) );

		if ( ! $result ) {
			return $this->error( 'delete_failed', __( 'Failed to delete allocation.', 'ict-platform' ), 500 );
		}

		return $this->success( array( 'deleted' => true ) );
	}

	public function getCalendarEvents( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$start = $request->get_param( 'start' );
		$end   = $request->get_param( 'end' );

		$allocations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*, p.project_name FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' r
            LEFT JOIN ' . ICT_PROJECTS_TABLE . ' p ON r.project_id = p.id
            WHERE r.allocation_start >= %s AND r.allocation_end <= %s',
				$start,
				$end
			)
		);

		$events = array_map(
			function ( $allocation ) {
				return array(
					'id'            => (int) $allocation->id,
					'title'         => $allocation->project_name ?? 'Allocation',
					'start'         => $allocation->allocation_start,
					'end'           => $allocation->allocation_end,
					'resourceId'    => (int) $allocation->resource_id,
					'extendedProps' => array(
						'resourceType' => $allocation->resource_type,
						'projectId'    => (int) $allocation->project_id,
						'status'       => $allocation->status,
					),
				);
			},
			$allocations
		);

		return $this->success( $events );
	}

	public function checkConflicts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$resourceType = $request->get_param( 'resource_type' );
		$resourceId   = (int) $request->get_param( 'resource_id' );
		$start        = $request->get_param( 'allocation_start' );
		$end          = $request->get_param( 'allocation_end' );
		$excludeId    = (int) ( $request->get_param( 'exclude_id' ) ?? 0 );

		$where  = array(
			'resource_type = %s',
			'resource_id = %d',
			'((allocation_start < %s AND allocation_end > %s) OR (allocation_start < %s AND allocation_end > %s) OR (allocation_start >= %s AND allocation_end <= %s))',
		);
		$values = array( $resourceType, $resourceId, $end, $start, $end, $start, $start, $end );

		if ( $excludeId ) {
			$where[]  = 'id != %d';
			$values[] = $excludeId;
		}

		$conflicts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECT_RESOURCES_TABLE . ' WHERE ' . implode( ' AND ', $where ),
				$values
			)
		);

		return $this->success(
			array(
				'has_conflicts' => ! empty( $conflicts ),
				'conflicts'     => array_map( array( $this, 'prepareAllocation' ), $conflicts ),
			)
		);
	}

	public function getItemsPermissionsCheck(): bool {
		return $this->canViewProjects(); }
	public function createItemPermissionsCheck(): bool {
		return $this->canManageProjects(); }
	public function updateItemPermissionsCheck(): bool {
		return $this->canManageProjects(); }
	public function deleteItemPermissionsCheck(): bool {
		return $this->canManageProjects(); }

	private function prepareAllocation( object $allocation ): array {
		return array(
			'id'               => (int) $allocation->id,
			'project_id'       => (int) $allocation->project_id,
			'resource_type'    => $allocation->resource_type,
			'resource_id'      => (int) $allocation->resource_id,
			'allocation_start' => $allocation->allocation_start,
			'allocation_end'   => $allocation->allocation_end,
			'hours_allocated'  => (float) ( $allocation->hours_allocated ?? 0 ),
			'status'           => $allocation->status,
		);
	}
}
