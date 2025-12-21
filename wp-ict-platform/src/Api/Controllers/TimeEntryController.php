<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Time Entry REST API Controller
 *
 * Handles all time entry-related API endpoints.
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class TimeEntryController extends AbstractController {

	/**
	 * Route base
	 */
	protected string $rest_base = 'time-entries';

	/**
	 * Register routes
	 */
	public function registerRoutes(): void {
		// GET /time-entries
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItems' ),
				'permission_callback' => array( $this, 'getItemsPermissionsCheck' ),
				'args'                => $this->getCollectionParams(),
			)
		);

		// POST /time-entries
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createItem' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// GET /time-entries/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItem' ),
				'permission_callback' => array( $this, 'getItemsPermissionsCheck' ),
			)
		);

		// POST /time/clock-in
		register_rest_route(
			$this->namespace,
			'/time/clock-in',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clockIn' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// POST /time/clock-out
		register_rest_route(
			$this->namespace,
			'/time/clock-out',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clockOut' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// POST /time-entries/{id}/approve
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approveEntry' ),
				'permission_callback' => array( $this, 'approvePermissionsCheck' ),
			)
		);

		// GET /time/current
		register_rest_route(
			$this->namespace,
			'/time/current',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getCurrentEntry' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Get collection of time entries
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItems( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$pagination   = $this->getPaginationParams( $request );
		$technicianId = (int) $request->get_param( 'technician_id' );
		$projectId    = (int) $request->get_param( 'project_id' );
		$status       = $request->get_param( 'status' );
		$dateFrom     = $request->get_param( 'date_from' );
		$dateTo       = $request->get_param( 'date_to' );

		$where  = array( '1=1' );
		$values = array();

		// Non-managers can only see their own entries
		if ( ! $this->canApproveTime() ) {
			$technicianId = $this->getCurrentUserId();
		}

		if ( $technicianId ) {
			$where[]  = 'technician_id = %d';
			$values[] = $technicianId;
		}

		if ( $projectId ) {
			$where[]  = 'project_id = %d';
			$values[] = $projectId;
		}

		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		if ( $dateFrom ) {
			$where[]  = 'DATE(clock_in) >= %s';
			$values[] = $dateFrom;
		}

		if ( $dateTo ) {
			$where[]  = 'DATE(clock_in) <= %s';
			$values[] = $dateTo;
		}

		// Get total count
		$countSql = 'SELECT COUNT(*) FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE ' . implode( ' AND ', $where );
		$total    = (int) $wpdb->get_var( $wpdb->prepare( $countSql, $values ) );

		// Get items
		$sql = 'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . '
                WHERE ' . implode( ' AND ', $where ) . '
                ORDER BY clock_in DESC
                LIMIT %d OFFSET %d';

		$values[] = $pagination['per_page'];
		$values[] = $pagination['offset'];

		$entries = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		// Transform data
		$items = array_map( array( $this, 'prepareItem' ), $entries );

		return $this->paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	/**
	 * Get a single time entry
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$id
			)
		);

		if ( ! $entry ) {
			return $this->error( 'not_found', __( 'Time entry not found.', 'ict-platform' ), 404 );
		}

		// Check if user can view this entry
		if ( ! $this->canApproveTime() && (int) $entry->technician_id !== $this->getCurrentUserId() ) {
			return $this->error( 'forbidden', __( 'You cannot view this time entry.', 'ict-platform' ), 403 );
		}

		return $this->success( $this->prepareItem( $entry ) );
	}

	/**
	 * Create a new time entry
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$data = array(
			'technician_id' => $this->getCurrentUserId(),
			'project_id'    => (int) $request->get_param( 'project_id' ),
			'clock_in'      => $request->get_param( 'clock_in' ) ?? current_time( 'mysql' ),
			'clock_out'     => $request->get_param( 'clock_out' ),
			'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'status'        => 'pending',
		);

		// Calculate hours if both times provided
		if ( $data['clock_out'] ) {
			$data['total_hours'] = $this->helper->calculateHours( $data['clock_in'], $data['clock_out'] );
		}

		$result = $wpdb->insert( ICT_TIME_ENTRIES_TABLE, $data );

		if ( ! $result ) {
			return $this->error( 'create_failed', __( 'Failed to create time entry.', 'ict-platform' ), 500 );
		}

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$wpdb->insert_id
			)
		);

		return $this->success( $this->prepareItem( $entry ), 201 );
	}

	/**
	 * Clock in
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function clockIn( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$userId = $this->getCurrentUserId();

		// Check if already clocked in
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . '
                WHERE technician_id = %d AND clock_out IS NULL
                ORDER BY clock_in DESC LIMIT 1',
				$userId
			)
		);

		if ( $existing ) {
			return $this->error( 'already_clocked_in', __( 'You are already clocked in.', 'ict-platform' ), 400 );
		}

		$data = array(
			'technician_id'   => $userId,
			'project_id'      => (int) $request->get_param( 'project_id' ),
			'clock_in'        => current_time( 'mysql' ),
			'notes'           => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'clock_in_coords' => $this->helper->sanitizeCoordinates( $request->get_param( 'coordinates' ) ?? '' ),
			'status'          => 'pending',
		);

		$result = $wpdb->insert( ICT_TIME_ENTRIES_TABLE, $data );

		if ( ! $result ) {
			return $this->error( 'clock_in_failed', __( 'Failed to clock in.', 'ict-platform' ), 500 );
		}

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$wpdb->insert_id
			)
		);

		return $this->success( $this->prepareItem( $entry ), 201 );
	}

	/**
	 * Clock out
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function clockOut( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$userId = $this->getCurrentUserId();

		// Find active clock-in
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . '
                WHERE technician_id = %d AND clock_out IS NULL
                ORDER BY clock_in DESC LIMIT 1',
				$userId
			)
		);

		if ( ! $entry ) {
			return $this->error( 'not_clocked_in', __( 'You are not clocked in.', 'ict-platform' ), 400 );
		}

		$clockOut   = current_time( 'mysql' );
		$totalHours = $this->helper->calculateHours( $entry->clock_in, $clockOut );

		$result = $wpdb->update(
			ICT_TIME_ENTRIES_TABLE,
			array(
				'clock_out'        => $clockOut,
				'clock_out_coords' => $this->helper->sanitizeCoordinates( $request->get_param( 'coordinates' ) ?? '' ),
				'total_hours'      => $totalHours,
				'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ?? $entry->notes ),
			),
			array( 'id' => $entry->id )
		);

		if ( $result === false ) {
			return $this->error( 'clock_out_failed', __( 'Failed to clock out.', 'ict-platform' ), 500 );
		}

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$entry->id
			)
		);

		return $this->success( $this->prepareItem( $entry ) );
	}

	/**
	 * Approve time entry
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function approveEntry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$result = $wpdb->update(
			ICT_TIME_ENTRIES_TABLE,
			array(
				'status'      => 'approved',
				'approved_by' => $this->getCurrentUserId(),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( $result === false ) {
			return $this->error( 'approve_failed', __( 'Failed to approve time entry.', 'ict-platform' ), 500 );
		}

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$id
			)
		);

		return $this->success( $this->prepareItem( $entry ) );
	}

	/**
	 * Get current active time entry
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public function getCurrentEntry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . '
                WHERE technician_id = %d AND clock_out IS NULL
                ORDER BY clock_in DESC LIMIT 1',
				$this->getCurrentUserId()
			)
		);

		if ( ! $entry ) {
			return $this->success( null );
		}

		return $this->success( $this->prepareItem( $entry ) );
	}

	/**
	 * Permission check for getting items
	 *
	 * @return bool
	 */
	public function getItemsPermissionsCheck(): bool {
		return is_user_logged_in();
	}

	/**
	 * Permission check for approving
	 *
	 * @return bool
	 */
	public function approvePermissionsCheck(): bool {
		return $this->canApproveTime();
	}

	/**
	 * Prepare a time entry item for response
	 *
	 * @param object $entry Raw entry object
	 * @return array<string, mixed>
	 */
	private function prepareItem( object $entry ): array {
		return array(
			'id'            => (int) $entry->id,
			'technician_id' => (int) $entry->technician_id,
			'technician'    => $this->helper->getUserDisplayName( (int) $entry->technician_id ),
			'project_id'    => (int) ( $entry->project_id ?? 0 ),
			'clock_in'      => $entry->clock_in,
			'clock_out'     => $entry->clock_out,
			'total_hours'   => (float) ( $entry->total_hours ?? 0 ),
			'notes'         => $entry->notes ?? '',
			'status'        => $entry->status,
			'approved_by'   => $entry->approved_by ? (int) $entry->approved_by : null,
			'approved_at'   => $entry->approved_at,
			'is_clocked_in' => empty( $entry->clock_out ),
		);
	}

	/**
	 * Get collection parameters
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function getCollectionParams(): array {
		return array(
			'page'          => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page'      => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			),
			'technician_id' => array( 'type' => 'integer' ),
			'project_id'    => array( 'type' => 'integer' ),
			'status'        => array(
				'type' => 'string',
				'enum' => array( 'pending', 'approved', 'rejected' ),
			),
			'date_from'     => array(
				'type'   => 'string',
				'format' => 'date',
			),
			'date_to'       => array(
				'type'   => 'string',
				'format' => 'date',
			),
		);
	}
}
