<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * QR Code System REST API Controller
 *
 * Handles QR code generation, scanning, and tracking for inventory/equipment.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class QrCodeController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'qr-codes';

	/**
	 * Register routes for QR codes.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /qr-codes - List all QR codes
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

		// GET/PUT/DELETE /qr-codes/{id}
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

		// GET /qr-codes/scan/{code} - Scan a QR code
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/scan/(?P<code>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'scanCode' ),
				'permission_callback' => '__return_true',
			)
		);

		// POST /qr-codes/generate - Generate QR code image
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generateQrImage' ),
				'permission_callback' => array( $this, 'canManageInventory' ),
			)
		);

		// POST /qr-codes/bulk-generate - Bulk generate QR codes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulkGenerate' ),
				'permission_callback' => array( $this, 'canManageInventory' ),
			)
		);

		// GET /qr-codes/entity/{type}/{id} - Get QR code for entity
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/entity/(?P<entity_type>[a-z_-]+)/(?P<entity_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getEntityQrCode' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// GET /qr-codes/stats - Get QR code statistics
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getStats' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// POST /qr-codes/print-batch - Get printable QR codes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/print-batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'getPrintBatch' ),
				'permission_callback' => array( $this, 'canManageInventory' ),
			)
		);
	}

	/**
	 * Get all QR codes.
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

		if ( $entity_type = $request->get_param( 'entity_type' ) ) {
			$where_clauses[] = 'entity_type = %s';
			$where_values[]  = sanitize_text_field( $entity_type );
		}

		if ( $request->has_param( 'is_active' ) ) {
			$where_clauses[] = 'is_active = %d';
			$where_values[]  = (int) $request->get_param( 'is_active' );
		}

		if ( $search = $request->get_param( 'search' ) ) {
			$where_clauses[] = '(code LIKE %s OR label LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count
		$count_sql = 'SELECT COUNT(*) FROM ' . ICT_QR_CODES_TABLE . ' WHERE ' . $where_sql;
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get QR codes
		$query        = 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE ' . $where_sql . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

		// Add image URLs
		$upload_dir = wp_upload_dir();
		foreach ( $items as &$item ) {
			if ( $item->qr_image_path ) {
				$item->qr_image_url = $upload_dir['baseurl'] . $item->qr_image_path;
			}
			$item->scan_url = site_url( '/scan/' . $item->code );
		}

		return $this->paginated( $items ?: array(), $total, $page, $per_page );
	}

	/**
	 * Get a single QR code.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$qr_code = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $qr_code ) {
			return $this->error( 'not_found', 'QR code not found', 404 );
		}

		// Add URLs
		$upload_dir = wp_upload_dir();
		if ( $qr_code->qr_image_path ) {
			$qr_code->qr_image_url = $upload_dir['baseurl'] . $qr_code->qr_image_path;
		}
		$qr_code->scan_url = site_url( '/scan/' . $qr_code->code );

		return $this->success( $qr_code );
	}

	/**
	 * Create a new QR code.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
		$entity_id   = (int) $request->get_param( 'entity_id' );

		if ( ! $entity_type || ! $entity_id ) {
			return $this->error( 'validation', 'Entity type and entity ID are required', 400 );
		}

		// Check if QR code already exists for this entity
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . ICT_QR_CODES_TABLE . ' WHERE entity_type = %s AND entity_id = %d',
				$entity_type,
				$entity_id
			)
		);

		if ( $existing ) {
			return $this->error( 'exists', 'QR code already exists for this entity', 400 );
		}

		// Generate unique code
		$prefix = strtoupper( substr( $entity_type, 0, 2 ) );
		$code   = $prefix . '-' . strtoupper( substr( md5( uniqid() . $entity_type . $entity_id ), 0, 8 ) );

		$data = array(
			'code'        => $code,
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
			'label'       => sanitize_text_field( $request->get_param( 'label' ) ),
			'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'is_active'   => 1,
			'created_by'  => get_current_user_id(),
		);

		// Generate QR image
		$qr_image_path = $this->generateQrCodeImage( $code, $data['label'] );
		if ( $qr_image_path ) {
			$data['qr_image_path'] = $qr_image_path;
		}

		$result = $wpdb->insert( ICT_QR_CODES_TABLE, $data );

		if ( $result === false ) {
			return $this->error( 'insert_failed', 'Failed to create QR code', 500 );
		}

		$qr_code = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE id = %d', $wpdb->insert_id )
		);

		// Add URLs
		$upload_dir = wp_upload_dir();
		if ( $qr_code->qr_image_path ) {
			$qr_code->qr_image_url = $upload_dir['baseurl'] . $qr_code->qr_image_path;
		}
		$qr_code->scan_url = site_url( '/scan/' . $qr_code->code );

		return $this->success( $qr_code, 201 );
	}

	/**
	 * Update a QR code.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id       = (int) $request->get_param( 'id' );
		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $existing ) {
			return $this->error( 'not_found', 'QR code not found', 404 );
		}

		$data = array();

		if ( $request->has_param( 'label' ) ) {
			$data['label'] = sanitize_text_field( $request->get_param( 'label' ) );
		}

		if ( $request->has_param( 'description' ) ) {
			$data['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
		}

		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = (int) (bool) $request->get_param( 'is_active' );
		}

		if ( empty( $data ) ) {
			return $this->error( 'no_data', 'No data to update', 400 );
		}

		$wpdb->update( ICT_QR_CODES_TABLE, $data, array( 'id' => $id ) );

		$qr_code = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $qr_code );
	}

	/**
	 * Delete a QR code.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deleteItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$qr_code = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $qr_code ) {
			return $this->error( 'not_found', 'QR code not found', 404 );
		}

		// Delete image
		if ( $qr_code->qr_image_path ) {
			$upload_dir = wp_upload_dir();
			$file_path  = $upload_dir['basedir'] . $qr_code->qr_image_path;
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		$wpdb->delete( ICT_QR_CODES_TABLE, array( 'id' => $id ) );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * Scan a QR code.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function scanCode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$code = sanitize_text_field( $request->get_param( 'code' ) );

		$qr_code = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE code = %s', $code )
		);

		if ( ! $qr_code ) {
			return $this->error( 'not_found', 'QR code not found', 404 );
		}

		if ( ! $qr_code->is_active ) {
			return $this->error( 'inactive', 'QR code is inactive', 400 );
		}

		// Update scan statistics
		$wpdb->update(
			ICT_QR_CODES_TABLE,
			array(
				'scan_count'      => $qr_code->scan_count + 1,
				'last_scanned_at' => current_time( 'mysql' ),
				'last_scanned_by' => get_current_user_id() ?: null,
			),
			array( 'id' => $qr_code->id )
		);

		// Get the linked entity data
		$entity_data = $this->getEntityData( $qr_code->entity_type, $qr_code->entity_id );

		return $this->success(
			array(
				'qr_code'     => $qr_code,
				'entity_type' => $qr_code->entity_type,
				'entity_id'   => $qr_code->entity_id,
				'entity_data' => $entity_data,
			)
		);
	}

	/**
	 * Generate QR code image.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generateQrImage( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$content = sanitize_text_field( $request->get_param( 'content' ) );
		$label   = sanitize_text_field( $request->get_param( 'label' ) );
		$size    = min( (int) ( $request->get_param( 'size' ) ?: 300 ), 1000 );

		if ( ! $content ) {
			return $this->error( 'validation', 'Content is required', 400 );
		}

		// Generate QR code using Google Charts API as fallback
		// In production, you'd use a PHP library like endroid/qr-code
		$qr_url = sprintf(
			'https://chart.googleapis.com/chart?chs=%dx%d&cht=qr&chl=%s&choe=UTF-8',
			$size,
			$size,
			urlencode( $content )
		);

		return $this->success(
			array(
				'qr_url'  => $qr_url,
				'content' => $content,
				'size'    => $size,
			)
		);
	}

	/**
	 * Bulk generate QR codes.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulkGenerate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
		$entity_ids  = $request->get_param( 'entity_ids' );

		if ( ! $entity_type || ! is_array( $entity_ids ) ) {
			return $this->error( 'validation', 'Entity type and entity IDs array are required', 400 );
		}

		$created = array();
		$skipped = array();

		foreach ( $entity_ids as $entity_id ) {
			$entity_id = (int) $entity_id;

			// Check if exists
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . ICT_QR_CODES_TABLE . ' WHERE entity_type = %s AND entity_id = %d',
					$entity_type,
					$entity_id
				)
			);

			if ( $existing ) {
				$skipped[] = $entity_id;
				continue;
			}

			// Generate code
			$prefix = strtoupper( substr( $entity_type, 0, 2 ) );
			$code   = $prefix . '-' . strtoupper( substr( md5( uniqid() . $entity_type . $entity_id ), 0, 8 ) );

			$result = $wpdb->insert(
				ICT_QR_CODES_TABLE,
				array(
					'code'        => $code,
					'entity_type' => $entity_type,
					'entity_id'   => $entity_id,
					'is_active'   => 1,
					'created_by'  => get_current_user_id(),
				)
			);

			if ( $result ) {
				$created[] = array(
					'entity_id' => $entity_id,
					'code'      => $code,
				);
			}
		}

		return $this->success(
			array(
				'created'       => $created,
				'created_count' => count( $created ),
				'skipped'       => $skipped,
				'skipped_count' => count( $skipped ),
			)
		);
	}

	/**
	 * Get QR code for entity.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getEntityQrCode( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
		$entity_id   = (int) $request->get_param( 'entity_id' );

		$qr_code = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_QR_CODES_TABLE . ' WHERE entity_type = %s AND entity_id = %d',
				$entity_type,
				$entity_id
			)
		);

		if ( ! $qr_code ) {
			return $this->error( 'not_found', 'QR code not found for this entity', 404 );
		}

		// Add URLs
		$upload_dir = wp_upload_dir();
		if ( $qr_code->qr_image_path ) {
			$qr_code->qr_image_url = $upload_dir['baseurl'] . $qr_code->qr_image_path;
		}
		$qr_code->scan_url = site_url( '/scan/' . $qr_code->code );

		return $this->success( $qr_code );
	}

	/**
	 * Get QR code statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getStats( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		// Total QR codes
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ICT_QR_CODES_TABLE );

		// Active QR codes
		$active = (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . ICT_QR_CODES_TABLE . ' WHERE is_active = 1'
		);

		// Total scans
		$total_scans = (int) $wpdb->get_var(
			'SELECT SUM(scan_count) FROM ' . ICT_QR_CODES_TABLE
		);

		// By entity type
		$by_type = $wpdb->get_results(
			'SELECT entity_type, COUNT(*) as count, SUM(scan_count) as scans
             FROM ' . ICT_QR_CODES_TABLE . '
             GROUP BY entity_type
             ORDER BY count DESC'
		);

		// Most scanned
		$most_scanned = $wpdb->get_results(
			'SELECT * FROM ' . ICT_QR_CODES_TABLE . '
             WHERE scan_count > 0
             ORDER BY scan_count DESC
             LIMIT 10'
		);

		// Recent scans
		$recent_scans = $wpdb->get_results(
			'SELECT * FROM ' . ICT_QR_CODES_TABLE . '
             WHERE last_scanned_at IS NOT NULL
             ORDER BY last_scanned_at DESC
             LIMIT 10'
		);

		return $this->success(
			array(
				'total'        => $total,
				'active'       => $active,
				'inactive'     => $total - $active,
				'total_scans'  => $total_scans,
				'by_type'      => $by_type,
				'most_scanned' => $most_scanned,
				'recent_scans' => $recent_scans,
			)
		);
	}

	/**
	 * Get printable QR codes batch.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getPrintBatch( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$ids  = $request->get_param( 'ids' );
		$size = (int) ( $request->get_param( 'size' ) ?: 200 );

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return $this->error( 'validation', 'QR code IDs array is required', 400 );
		}

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$qr_codes = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_QR_CODES_TABLE . " WHERE id IN ($placeholders)",
				...$ids
			)
		);

		$print_data = array();
		foreach ( $qr_codes as $qr ) {
			$print_data[] = array(
				'id'     => $qr->id,
				'code'   => $qr->code,
				'label'  => $qr->label ?: $qr->code,
				'entity' => $qr->entity_type . ' #' . $qr->entity_id,
				'qr_url' => sprintf(
					'https://chart.googleapis.com/chart?chs=%dx%d&cht=qr&chl=%s&choe=UTF-8',
					$size,
					$size,
					urlencode( site_url( '/scan/' . $qr->code ) )
				),
			);
		}

		return $this->success(
			array(
				'qr_codes' => $print_data,
				'count'    => count( $print_data ),
				'size'     => $size,
			)
		);
	}

	/**
	 * Get entity data based on type.
	 *
	 * @param string $type Entity type.
	 * @param int    $id Entity ID.
	 * @return object|null
	 */
	private function getEntityData( string $type, int $id ): ?object {
		global $wpdb;

		$table_map = array(
			'inventory' => ICT_INVENTORY_ITEMS_TABLE,
			'equipment' => ICT_EQUIPMENT_TABLE,
			'vehicle'   => ICT_FLEET_TABLE,
			'project'   => ICT_PROJECTS_TABLE,
		);

		if ( ! isset( $table_map[ $type ] ) ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $table_map[ $type ] . ' WHERE id = %d', $id )
		);
	}

	/**
	 * Generate and save QR code image.
	 *
	 * @param string      $code QR code.
	 * @param string|null $label Label.
	 * @return string|null Relative file path.
	 */
	private function generateQrCodeImage( string $code, ?string $label = null ): ?string {
		// This is a placeholder - in production use a PHP QR library
		// like endroid/qr-code or bacon/bacon-qr-code
		return null;
	}

	/**
	 * Check if user can manage inventory.
	 *
	 * @return bool
	 */
	protected function canManageInventory(): bool {
		return current_user_can( 'manage_ict_inventory' ) || current_user_can( 'manage_options' );
	}
}
