<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Digital Signature REST API Controller
 *
 * Handles e-signature capture for work orders, contracts, and deliveries.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class SignatureController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'signatures';

	/**
	 * Register routes for signatures.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /signatures - List all signatures
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItems' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
				'args'                => $this->getCollectionParams(),
			)
		);

		// POST /signatures - Create signature
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createItem' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /signatures/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getItem' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// GET /signatures/entity/{type}/{id} - Get signatures for entity
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/entity/(?P<entity_type>[a-z_-]+)/(?P<entity_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getEntitySignatures' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// POST /signatures/verify/{id} - Verify signature
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/verify/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'verifySignature' ),
				'permission_callback' => '__return_true',
			)
		);

		// POST /signatures/request - Request signature from client
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'requestSignature' ),
				'permission_callback' => array( $this, 'canManageProjects' ),
			)
		);
	}

	/**
	 * Get all signatures.
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

		if ( $entity_id = $request->get_param( 'entity_id' ) ) {
			$where_clauses[] = 'entity_id = %d';
			$where_values[]  = (int) $entity_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count
		$count_sql = 'SELECT COUNT(*) FROM ' . ICT_SIGNATURES_TABLE . ' WHERE ' . $where_sql;
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get signatures
		$query        = 'SELECT * FROM ' . ICT_SIGNATURES_TABLE . ' WHERE ' . $where_sql . ' ORDER BY signed_at DESC LIMIT %d OFFSET %d';
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

		// Add image URLs
		foreach ( $items as &$item ) {
			if ( $item->signature_image ) {
				$upload_dir          = wp_upload_dir();
				$item->signature_url = $upload_dir['baseurl'] . $item->signature_image;
			}
		}

		return $this->paginated( $items ?: array(), $total, $page, $per_page );
	}

	/**
	 * Get a single signature.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$signature = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_SIGNATURES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $signature ) {
			return $this->error( 'not_found', 'Signature not found', 404 );
		}

		if ( $signature->signature_image ) {
			$upload_dir               = wp_upload_dir();
			$signature->signature_url = $upload_dir['baseurl'] . $signature->signature_image;
		}

		return $this->success( $signature );
	}

	/**
	 * Create a new signature.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$entity_type    = sanitize_text_field( $request->get_param( 'entity_type' ) );
		$entity_id      = (int) $request->get_param( 'entity_id' );
		$signer_name    = sanitize_text_field( $request->get_param( 'signer_name' ) );
		$signature_data = $request->get_param( 'signature_data' );

		if ( ! $entity_type || ! $entity_id || ! $signer_name || ! $signature_data ) {
			return $this->error( 'validation', 'Entity type, entity ID, signer name, and signature data are required', 400 );
		}

		// Save signature image from base64
		$signature_image = null;
		if ( strpos( $signature_data, 'data:image' ) === 0 ) {
			$signature_image = $this->saveSignatureImage( $signature_data, $entity_type, $entity_id );
		}

		$data = array(
			'entity_type'        => $entity_type,
			'entity_id'          => $entity_id,
			'signer_name'        => $signer_name,
			'signer_email'       => sanitize_email( $request->get_param( 'signer_email' ) ),
			'signer_role'        => sanitize_text_field( $request->get_param( 'signer_role' ) ),
			'signature_data'     => $signature_data,
			'signature_image'    => $signature_image,
			'ip_address'         => $_SERVER['REMOTE_ADDR'] ?? null,
			'user_agent'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
			'location_latitude'  => $request->get_param( 'latitude' ) ? (float) $request->get_param( 'latitude' ) : null,
			'location_longitude' => $request->get_param( 'longitude' ) ? (float) $request->get_param( 'longitude' ) : null,
			'signed_at'          => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( ICT_SIGNATURES_TABLE, $data );

		if ( $result === false ) {
			return $this->error( 'insert_failed', 'Failed to save signature', 500 );
		}

		$signature = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_SIGNATURES_TABLE . ' WHERE id = %d', $wpdb->insert_id )
		);

		// Log activity
		$this->logActivity( 'signature', $entity_type, $entity_id, $signer_name );

		return $this->success( $signature, 201 );
	}

	/**
	 * Get signatures for a specific entity.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getEntitySignatures( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
		$entity_id   = (int) $request->get_param( 'entity_id' );

		$signatures = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_SIGNATURES_TABLE . '
                 WHERE entity_type = %s AND entity_id = %d
                 ORDER BY signed_at ASC',
				$entity_type,
				$entity_id
			)
		);

		// Add image URLs
		foreach ( $signatures as &$sig ) {
			if ( $sig->signature_image ) {
				$upload_dir         = wp_upload_dir();
				$sig->signature_url = $upload_dir['baseurl'] . $sig->signature_image;
			}
		}

		return $this->success( $signatures ?: array() );
	}

	/**
	 * Verify a signature.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verifySignature( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$signature = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_SIGNATURES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $signature ) {
			return $this->error( 'not_found', 'Signature not found', 404 );
		}

		// Verification info
		$verification = array(
			'valid'        => true,
			'signature_id' => $signature->id,
			'signer_name'  => $signature->signer_name,
			'signer_email' => $signature->signer_email,
			'signer_role'  => $signature->signer_role,
			'signed_at'    => $signature->signed_at,
			'entity_type'  => $signature->entity_type,
			'entity_id'    => $signature->entity_id,
			'ip_address'   => $signature->ip_address,
			'has_location' => ! empty( $signature->location_latitude ),
		);

		if ( $signature->location_latitude && $signature->location_longitude ) {
			$verification['location'] = array(
				'latitude'  => $signature->location_latitude,
				'longitude' => $signature->location_longitude,
			);
		}

		return $this->success( $verification );
	}

	/**
	 * Request signature from client.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function requestSignature( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email       = sanitize_email( $request->get_param( 'email' ) );
		$name        = sanitize_text_field( $request->get_param( 'name' ) );
		$entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
		$entity_id   = (int) $request->get_param( 'entity_id' );
		$message     = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( ! $email || ! $entity_type || ! $entity_id ) {
			return $this->error( 'validation', 'Email, entity type, and entity ID are required', 400 );
		}

		// Generate unique signing token
		$token = wp_generate_password( 32, false );

		// Store pending signature request (could use transient or custom table)
		set_transient(
			'ict_sig_request_' . $token,
			array(
				'email'        => $email,
				'name'         => $name,
				'entity_type'  => $entity_type,
				'entity_id'    => $entity_id,
				'requested_by' => get_current_user_id(),
				'requested_at' => current_time( 'mysql' ),
			),
			7 * DAY_IN_SECONDS
		);

		// Generate signing URL
		$signing_url = add_query_arg(
			array( 'ict_sign' => $token ),
			site_url( '/sign/' )
		);

		// Send email
		$subject = 'Signature Requested';
		$body    = sprintf(
			"Hello %s,\n\nA signature has been requested from you.\n\n%s\n\nPlease click the link below to sign:\n%s\n\nThis link expires in 7 days.",
			$name ?: 'Customer',
			$message ?: '',
			$signing_url
		);

		$sent = wp_mail( $email, $subject, $body );

		return $this->success(
			array(
				'sent'        => $sent,
				'signing_url' => $signing_url,
				'token'       => $token,
				'expires_in'  => '7 days',
			)
		);
	}

	/**
	 * Save signature image from base64 data.
	 *
	 * @param string $base64_data Base64 image data.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @return string|null Relative file path.
	 */
	private function saveSignatureImage( string $base64_data, string $entity_type, int $entity_id ): ?string {
		// Extract image data
		if ( preg_match( '/^data:image\/(\w+);base64,/', $base64_data, $type ) ) {
			$base64_data = substr( $base64_data, strpos( $base64_data, ',' ) + 1 );
			$type        = strtolower( $type[1] );

			if ( ! in_array( $type, array( 'png', 'jpg', 'jpeg', 'gif' ) ) ) {
				return null;
			}

			$data = base64_decode( $base64_data );
			if ( $data === false ) {
				return null;
			}

			// Set up upload directory
			$upload_dir = wp_upload_dir();
			$sig_dir    = $upload_dir['basedir'] . '/ict-platform/signatures/' . date( 'Y/m' );

			if ( ! file_exists( $sig_dir ) ) {
				wp_mkdir_p( $sig_dir );
			}

			// Generate unique filename
			$filename  = sprintf( 'sig_%s_%d_%s.%s', $entity_type, $entity_id, uniqid(), $type );
			$file_path = $sig_dir . '/' . $filename;

			// Save file
			if ( file_put_contents( $file_path, $data ) !== false ) {
				return str_replace( $upload_dir['basedir'], '', $file_path );
			}
		}

		return null;
	}

	/**
	 * Log signature activity.
	 *
	 * @param string $action Action.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id Entity ID.
	 * @param string $signer_name Signer name.
	 * @return void
	 */
	private function logActivity( string $action, string $entity_type, int $entity_id, string $signer_name ): void {
		global $wpdb;

		$wpdb->insert(
			ICT_ACTIVITY_LOG_TABLE,
			array(
				'user_id'     => get_current_user_id() ?: 0,
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'entity_name' => $signer_name,
				'description' => sprintf( 'Signature captured for %s #%d by %s', $entity_type, $entity_id, $signer_name ),
				'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
			)
		);
	}
}
