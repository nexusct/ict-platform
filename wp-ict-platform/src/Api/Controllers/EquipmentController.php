<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Equipment Tracking REST API Controller
 *
 * Handles equipment/tool management, assignments, and maintenance tracking.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class EquipmentController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'equipment';

	/**
	 * Register routes for equipment.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /equipment - List all equipment
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItems' ),
					'permission_callback' => array( $this, 'canViewProjects' ),
					'args'                => $this->getCollectionParams(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createItem' ),
					'permission_callback' => array( $this, 'canManageInventory' ),
				),
			)
		);

		// GET/PUT/DELETE /equipment/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItem' ),
					'permission_callback' => array( $this, 'canViewProjects' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateItem' ),
					'permission_callback' => array( $this, 'canManageInventory' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'deleteItem' ),
					'permission_callback' => array( $this, 'canManageInventory' ),
				),
			)
		);

		// POST /equipment/{id}/assign - Assign equipment
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/assign',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assignEquipment' ),
				'permission_callback' => array( $this, 'canManageProjects' ),
			)
		);

		// POST /equipment/{id}/return - Return equipment
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/return',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'returnEquipment' ),
				'permission_callback' => array( $this, 'canManageProjects' ),
			)
		);

		// POST /equipment/{id}/maintenance - Log maintenance
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/maintenance',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'logMaintenance' ),
				'permission_callback' => array( $this, 'canManageInventory' ),
			)
		);

		// GET /equipment/due-maintenance - Get equipment due for maintenance
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/due-maintenance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDueMaintenance' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// GET /equipment/types - Get equipment types
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/types',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getTypes' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// GET /equipment/scan/{qr_code} - Scan QR code
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/scan/(?P<qr_code>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'scanQrCode' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);
	}

	/**
	 * Get all equipment with filtering and pagination.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItems( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$page     = (int) $request->get_param( 'page' ) ?: 1;
		$per_page = min( (int) $request->get_param( 'per_page' ) ?: 20, 100 );
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( $type = $request->get_param( 'equipment_type' ) ) {
			$where_clauses[] = 'equipment_type = %s';
			$where_values[]  = sanitize_text_field( $type );
		}

		if ( $status = $request->get_param( 'status' ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = sanitize_text_field( $status );
		}

		if ( $assigned_to = $request->get_param( 'assigned_to' ) ) {
			$where_clauses[] = 'assigned_to = %d';
			$where_values[]  = (int) $assigned_to;
		}

		if ( $search = $request->get_param( 'search' ) ) {
			$where_clauses[] = '(equipment_name LIKE %s OR equipment_number LIKE %s OR serial_number LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count
		$count_sql = 'SELECT COUNT(*) FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE ' . $where_sql;
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get equipment
		$query        = 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE ' . $where_sql . ' ORDER BY equipment_name ASC LIMIT %d OFFSET %d';
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

		return $this->paginated( $items ?: array(), $total, $page, $per_page );
	}

	/**
	 * Get a single equipment item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $equipment ) {
			return $this->error( 'not_found', 'Equipment not found', 404 );
		}

		// Get assigned user info
		if ( $equipment->assigned_to ) {
			$user                     = get_userdata( $equipment->assigned_to );
			$equipment->assigned_user = $user ? array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
			) : null;
		}

		return $this->success( $equipment );
	}

	/**
	 * Create new equipment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$data = array(
			'equipment_name'            => sanitize_text_field( $request->get_param( 'equipment_name' ) ),
			'equipment_number'          => sanitize_text_field( $request->get_param( 'equipment_number' ) ),
			'equipment_type'            => sanitize_text_field( $request->get_param( 'equipment_type' ) ),
			'manufacturer'              => sanitize_text_field( $request->get_param( 'manufacturer' ) ),
			'model'                     => sanitize_text_field( $request->get_param( 'model' ) ),
			'serial_number'             => sanitize_text_field( $request->get_param( 'serial_number' ) ),
			'purchase_date'             => sanitize_text_field( $request->get_param( 'purchase_date' ) ),
			'purchase_price'            => (float) $request->get_param( 'purchase_price' ),
			'current_value'             => (float) $request->get_param( 'current_value' ),
			'status'                    => sanitize_text_field( $request->get_param( 'status' ) ?: 'available' ),
			'condition_rating'          => (int) ( $request->get_param( 'condition_rating' ) ?: 5 ),
			'location'                  => sanitize_text_field( $request->get_param( 'location' ) ),
			'maintenance_interval_days' => (int) ( $request->get_param( 'maintenance_interval_days' ) ?: 365 ),
			'notes'                     => sanitize_textarea_field( $request->get_param( 'notes' ) ),
		);

		// Generate QR code
		$data['qr_code'] = 'EQ-' . strtoupper( substr( md5( uniqid() ), 0, 8 ) );

		$result = $wpdb->insert( ICT_EQUIPMENT_TABLE, $data );

		if ( $result === false ) {
			return $this->error( 'insert_failed', 'Failed to create equipment', 500 );
		}

		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $wpdb->insert_id )
		);

		// Create QR code record
		$this->createQrCode( 'equipment', $equipment->id, $equipment->qr_code, $equipment->equipment_name );

		return $this->success( $equipment, 201 );
	}

	/**
	 * Update equipment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id       = (int) $request->get_param( 'id' );
		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $existing ) {
			return $this->error( 'not_found', 'Equipment not found', 404 );
		}

		$updateable = array(
			'equipment_name',
			'equipment_number',
			'equipment_type',
			'manufacturer',
			'model',
			'serial_number',
			'purchase_date',
			'purchase_price',
			'current_value',
			'status',
			'condition_rating',
			'location',
			'maintenance_interval_days',
			'notes',
		);

		$data = array();
		foreach ( $updateable as $field ) {
			if ( $request->has_param( $field ) ) {
				$value = $request->get_param( $field );
				if ( in_array( $field, array( 'purchase_price', 'current_value' ) ) ) {
					$data[ $field ] = (float) $value;
				} elseif ( in_array( $field, array( 'condition_rating', 'maintenance_interval_days' ) ) ) {
					$data[ $field ] = (int) $value;
				} else {
					$data[ $field ] = sanitize_text_field( $value );
				}
			}
		}

		if ( empty( $data ) ) {
			return $this->error( 'no_data', 'No data to update', 400 );
		}

		$wpdb->update( ICT_EQUIPMENT_TABLE, $data, array( 'id' => $id ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $equipment );
	}

	/**
	 * Delete equipment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deleteItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $equipment ) {
			return $this->error( 'not_found', 'Equipment not found', 404 );
		}

		// Check if assigned
		if ( $equipment->status === 'in-use' ) {
			return $this->error( 'in_use', 'Cannot delete equipment that is currently in use', 400 );
		}

		$wpdb->delete( ICT_EQUIPMENT_TABLE, array( 'id' => $id ) );
		$wpdb->delete(
			ICT_QR_CODES_TABLE,
			array(
				'entity_type' => 'equipment',
				'entity_id'   => $id,
			)
		);

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * Assign equipment to a technician/project.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function assignEquipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $equipment ) {
			return $this->error( 'not_found', 'Equipment not found', 404 );
		}

		if ( $equipment->status !== 'available' ) {
			return $this->error( 'not_available', 'Equipment is not available for assignment', 400 );
		}

		$wpdb->update(
			ICT_EQUIPMENT_TABLE,
			array(
				'assigned_to'      => (int) $request->get_param( 'assigned_to' ),
				'assigned_project' => $request->get_param( 'assigned_project' ) ? (int) $request->get_param( 'assigned_project' ) : null,
				'status'           => 'in-use',
			),
			array( 'id' => $id )
		);

		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $equipment );
	}

	/**
	 * Return equipment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function returnEquipment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $equipment ) {
			return $this->error( 'not_found', 'Equipment not found', 404 );
		}

		$condition = $request->get_param( 'condition_rating' );
		$notes     = $request->get_param( 'notes' );

		$data = array(
			'assigned_to'      => null,
			'assigned_project' => null,
			'status'           => 'available',
		);

		if ( $condition ) {
			$data['condition_rating'] = (int) $condition;
		}

		if ( $notes ) {
			$data['notes'] = $equipment->notes . "\n[" . date( 'Y-m-d H:i' ) . '] Return note: ' . sanitize_textarea_field( $notes );
		}

		$wpdb->update( ICT_EQUIPMENT_TABLE, $data, array( 'id' => $id ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $equipment );
	}

	/**
	 * Log maintenance for equipment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function logMaintenance( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $equipment ) {
			return $this->error( 'not_found', 'Equipment not found', 404 );
		}

		$maintenance_date = sanitize_text_field( $request->get_param( 'maintenance_date' ) ?: date( 'Y-m-d' ) );
		$interval_days    = $equipment->maintenance_interval_days ?: 365;
		$next_maintenance = date( 'Y-m-d', strtotime( $maintenance_date . ' + ' . $interval_days . ' days' ) );

		$wpdb->update(
			ICT_EQUIPMENT_TABLE,
			array(
				'last_maintenance' => $maintenance_date,
				'next_maintenance' => $next_maintenance,
				'notes'            => $equipment->notes . "\n[" . $maintenance_date . '] Maintenance: ' . sanitize_textarea_field( $request->get_param( 'notes' ) ),
			),
			array( 'id' => $id )
		);

		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $equipment );
	}

	/**
	 * Get equipment due for maintenance.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getDueMaintenance( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$days_ahead  = (int) ( $request->get_param( 'days' ) ?: 30 );
		$target_date = date( 'Y-m-d', strtotime( '+' . $days_ahead . ' days' ) );

		$items = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . '
                 WHERE next_maintenance IS NOT NULL
                 AND next_maintenance <= %s
                 ORDER BY next_maintenance ASC',
				$target_date
			)
		);

		return $this->success( $items ?: array() );
	}

	/**
	 * Get equipment types.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getTypes( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$types = array(
			'power-tool'    => 'Power Tools',
			'hand-tool'     => 'Hand Tools',
			'testing'       => 'Testing Equipment',
			'safety'        => 'Safety Equipment',
			'lifting'       => 'Lifting Equipment',
			'cable'         => 'Cable & Wire Equipment',
			'diagnostic'    => 'Diagnostic Equipment',
			'measurement'   => 'Measurement Tools',
			'communication' => 'Communication Equipment',
			'other'         => 'Other',
		);

		return $this->success( $types );
	}

	/**
	 * Scan QR code to get equipment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function scanQrCode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$qr_code = sanitize_text_field( $request->get_param( 'qr_code' ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EQUIPMENT_TABLE . ' WHERE qr_code = %s', $qr_code )
		);

		if ( ! $equipment ) {
			return $this->error( 'not_found', 'Equipment not found for QR code', 404 );
		}

		// Update scan tracking
		$wpdb->update(
			ICT_QR_CODES_TABLE,
			array(
				'scan_count'      => $wpdb->prepare( 'scan_count + 1', array() ),
				'last_scanned_at' => current_time( 'mysql' ),
				'last_scanned_by' => get_current_user_id(),
			),
			array(
				'entity_type' => 'equipment',
				'entity_id'   => $equipment->id,
			)
		);

		return $this->success( $equipment );
	}

	/**
	 * Check if user can manage inventory.
	 *
	 * @return bool
	 */
	protected function canManageInventory(): bool {
		return current_user_can( 'manage_ict_inventory' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Create QR code record.
	 *
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @param string $code QR code.
	 * @param string $label Label.
	 * @return void
	 */
	private function createQrCode( string $entity_type, int $entity_id, string $code, string $label ): void {
		global $wpdb;

		$wpdb->insert(
			ICT_QR_CODES_TABLE,
			array(
				'code'        => $code,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'label'       => $label,
				'is_active'   => 1,
				'created_by'  => get_current_user_id(),
			)
		);
	}
}
