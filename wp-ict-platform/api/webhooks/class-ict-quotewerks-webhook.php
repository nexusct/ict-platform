<?php
/**
 * QuoteWerks Webhook Handler
 *
 * Handles incoming webhooks from QuoteWerks for real-time sync.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_QuoteWerks_Webhook
 *
 * Processes QuoteWerks webhook events.
 */
class ICT_QuoteWerks_Webhook {

	/**
	 * Webhook secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->secret_key = get_option( 'ict_quotewerks_webhook_secret', '' );
	}

	/**
	 * Register webhook routes.
	 */
	public function register_routes() {
		// POST /webhooks/quotewerks
		register_rest_route(
			'ict/v1',
			'/webhooks/quotewerks',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_webhook' ),
			)
		);
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$event_type = $request->get_header( 'X-QuoteWerks-Event' );
		$payload    = $request->get_json_params();

		// Log webhook
		$this->log_webhook( $event_type, $payload );

		// Process based on event type
		switch ( $event_type ) {
			case 'quote.created':
				return $this->handle_quote_created( $payload );

			case 'quote.updated':
				return $this->handle_quote_updated( $payload );

			case 'quote.approved':
				return $this->handle_quote_approved( $payload );

			case 'quote.converted':
				return $this->handle_quote_converted( $payload );

			case 'order.created':
				return $this->handle_order_created( $payload );

			case 'customer.updated':
				return $this->handle_customer_updated( $payload );

			default:
				return new WP_REST_Response(
					array(
						'status'  => 'ignored',
						'message' => 'Event type not handled: ' . $event_type,
					),
					200
				);
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_webhook( $request ) {
		if ( empty( $this->secret_key ) ) {
			return true; // Allow if no secret configured (development)
		}

		$signature = $request->get_header( 'X-QuoteWerks-Signature' );
		if ( empty( $signature ) ) {
			return false;
		}

		$payload = $request->get_body();
		$expected_signature = hash_hmac( 'sha256', $payload, $this->secret_key );

		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Handle quote created event.
	 *
	 * @param array $payload Webhook payload.
	 * @return WP_REST_Response
	 */
	private function handle_quote_created( $payload ) {
		if ( empty( $payload['quote'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Missing quote data' ),
				400
			);
		}

		$adapter = new ICT_QuoteWerks_Adapter();
		$result  = $adapter->sync_quote_to_project( $payload['quote']['doc_number'] );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'status'     => 'success',
				'project_id' => $result,
			),
			200
		);
	}

	/**
	 * Handle quote updated event.
	 *
	 * @param array $payload Webhook payload.
	 * @return WP_REST_Response
	 */
	private function handle_quote_updated( $payload ) {
		return $this->handle_quote_created( $payload ); // Same logic
	}

	/**
	 * Handle quote approved event.
	 *
	 * @param array $payload Webhook payload.
	 * @return WP_REST_Response
	 */
	private function handle_quote_approved( $payload ) {
		global $wpdb;

		$quote_id = $payload['quote']['doc_number'] ?? null;
		if ( ! $quote_id ) {
			return new WP_REST_Response( array( 'error' => 'Missing quote ID' ), 400 );
		}

		// Find project
		$project_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . ICT_PROJECTS_TABLE . " WHERE quotewerks_id = %s",
				$quote_id
			)
		);

		if ( ! $project_id ) {
			return new WP_REST_Response( array( 'error' => 'Project not found' ), 404 );
		}

		// Update project status to in-progress
		$wpdb->update(
			ICT_PROJECTS_TABLE,
			array(
				'status'     => 'in-progress',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $project_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Queue Zoho sync
		ICT_Helper::queue_sync(
			array(
				'entity_type'  => 'project',
				'entity_id'    => $project_id,
				'action'       => 'update',
				'zoho_service' => 'crm',
				'priority'     => 3,
			)
		);

		return new WP_REST_Response(
			array(
				'status'     => 'success',
				'project_id' => $project_id,
			),
			200
		);
	}

	/**
	 * Handle quote converted to order.
	 *
	 * @param array $payload Webhook payload.
	 * @return WP_REST_Response
	 */
	private function handle_quote_converted( $payload ) {
		global $wpdb;

		$quote_id = $payload['quote']['doc_number'] ?? null;
		$order_id = $payload['order']['doc_number'] ?? null;

		if ( ! $quote_id || ! $order_id ) {
			return new WP_REST_Response( array( 'error' => 'Missing data' ), 400 );
		}

		// Find project
		$project_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . ICT_PROJECTS_TABLE . " WHERE quotewerks_id = %s",
				$quote_id
			)
		);

		if ( ! $project_id ) {
			return new WP_REST_Response( array( 'error' => 'Project not found' ), 404 );
		}

		// Update with order ID
		$wpdb->update(
			ICT_PROJECTS_TABLE,
			array(
				'quotewerks_order_id' => $order_id,
				'status'              => 'in-progress',
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => $project_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return new WP_REST_Response( array( 'status' => 'success' ), 200 );
	}

	/**
	 * Handle order created event.
	 *
	 * @param array $payload Webhook payload.
	 * @return WP_REST_Response
	 */
	private function handle_order_created( $payload ) {
		// Similar to quote created, but creates purchase order
		return new WP_REST_Response( array( 'status' => 'success' ), 200 );
	}

	/**
	 * Handle customer updated event.
	 *
	 * @param array $payload Webhook payload.
	 * @return WP_REST_Response
	 */
	private function handle_customer_updated( $payload ) {
		// Sync customer to Zoho CRM
		ICT_Helper::queue_sync(
			array(
				'entity_type'  => 'customer',
				'entity_id'    => $payload['customer']['id'] ?? 0,
				'action'       => 'update',
				'zoho_service' => 'crm',
				'priority'     => 7,
				'payload'      => $payload['customer'] ?? array(),
			)
		);

		return new WP_REST_Response( array( 'status' => 'success' ), 200 );
	}

	/**
	 * Log webhook event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $payload Payload.
	 */
	private function log_webhook( $event_type, $payload ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ict_webhook_log',
			array(
				'source'      => 'quotewerks',
				'event_type'  => $event_type,
				'payload'     => wp_json_encode( $payload ),
				'received_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}
