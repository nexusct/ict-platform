<?php
/**
 * Zoho Webhook Receiver
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Webhook_Receiver
 *
 * Handles incoming webhooks from Zoho services with signature verification.
 */
class ICT_Webhook_Receiver {

	/**
	 * Webhook secret keys for each service.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	protected $secrets;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_secrets();
		$this->register_endpoints();
	}

	/**
	 * Load webhook secrets from options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function load_secrets() {
		$services = array( 'crm', 'fsm', 'books', 'people', 'desk' );

		foreach ( $services as $service ) {
			$secret = get_option( "ict_zoho_{$service}_webhook_secret" );
			if ( ! empty( $secret ) ) {
				$this->secrets[ $service ] = ICT_Admin_Settings::decrypt( $secret );
			}
		}
	}

	/**
	 * Register webhook endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_endpoints() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes for webhooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		$services = array( 'crm', 'fsm', 'books', 'people', 'desk' );

		foreach ( $services as $service ) {
			register_rest_route(
				'ict/v1',
				"/webhooks/{$service}",
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true', // Public endpoint, verified by signature
					'args'                => array(
						'service' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return in_array( $param, array( 'crm', 'fsm', 'books', 'people', 'desk' ), true );
							},
						),
					),
				)
			);
		}
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_webhook( $request ) {
		$service = $request->get_param( 'service' );
		$body    = $request->get_body();
		$headers = $request->get_headers();

		// Log webhook receipt
		$this->log_webhook( $service, 'received', $body, $headers );

		// Verify signature
		if ( ! $this->verify_signature( $service, $body, $headers ) ) {
			$this->log_webhook( $service, 'rejected', $body, array( 'reason' => 'Invalid signature' ) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid webhook signature',
				),
				401
			);
		}

		// Parse webhook data
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid JSON payload',
				),
				400
			);
		}

		// Process webhook based on service and event type
		try {
			$result = $this->process_webhook( $service, $data );

			$this->log_webhook( $service, 'processed', $body, array( 'result' => $result ) );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Webhook processed successfully',
					'data'    => $result,
				),
				200
			);

		} catch ( Exception $e ) {
			$this->log_webhook( $service, 'error', $body, array( 'error' => $e->getMessage() ) );

			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @param  string $body    Request body.
	 * @param  array  $headers Request headers.
	 * @return bool True if signature is valid.
	 */
	protected function verify_signature( $service, $body, $headers ) {
		// If no secret is configured, skip verification (development mode)
		if ( ! isset( $this->secrets[ $service ] ) ) {
			return true;
		}

		// Get signature from headers (different header names per service)
		$signature_header = $this->get_signature_header( $service, $headers );

		if ( empty( $signature_header ) ) {
			return false;
		}

		// Calculate expected signature
		$expected_signature = hash_hmac( 'sha256', $body, $this->secrets[ $service ] );

		// Compare signatures (timing-safe comparison)
		return hash_equals( $expected_signature, $signature_header );
	}

	/**
	 * Get signature header for service.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @param  array  $headers Request headers.
	 * @return string|null Signature value.
	 */
	protected function get_signature_header( $service, $headers ) {
		$signature_headers = array(
			'crm'    => 'x-zoho-webhook-signature',
			'fsm'    => 'x-zoho-signature',
			'books'  => 'x-zoho-books-signature',
			'people' => 'x-zoho-signature',
			'desk'   => 'x-zohodesk-signature',
		);

		$header_name = $signature_headers[ $service ] ?? 'x-zoho-signature';

		// Headers might be lowercase
		$header_name_lower = strtolower( $header_name );

		return $headers[ $header_name ] ?? $headers[ $header_name_lower ] ?? null;
	}

	/**
	 * Process webhook based on service and event.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @param  array  $data    Webhook data.
	 * @return array Processing result.
	 * @throws Exception On processing error.
	 */
	protected function process_webhook( $service, $data ) {
		// Determine event type
		$event_type = $this->get_event_type( $service, $data );

		// Route to appropriate handler
		switch ( $service ) {
			case 'crm':
				return $this->process_crm_webhook( $event_type, $data );
			case 'fsm':
				return $this->process_fsm_webhook( $event_type, $data );
			case 'books':
				return $this->process_books_webhook( $event_type, $data );
			case 'people':
				return $this->process_people_webhook( $event_type, $data );
			case 'desk':
				return $this->process_desk_webhook( $event_type, $data );
			default:
				throw new Exception( sprintf( __( 'Unknown service: %s', 'ict-platform' ), $service ) );
		}
	}

	/**
	 * Get event type from webhook data.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @param  array  $data    Webhook data.
	 * @return string Event type.
	 */
	protected function get_event_type( $service, $data ) {
		// Different services use different field names for event type
		$event_fields = array(
			'crm'    => 'operation',
			'fsm'    => 'event',
			'books'  => 'event_type',
			'people' => 'event_type',
			'desk'   => 'event',
		);

		$field = $event_fields[ $service ] ?? 'event';

		return $data[ $field ] ?? 'unknown';
	}

	/**
	 * Process CRM webhook.
	 *
	 * @since  1.0.0
	 * @param  string $event_type Event type.
	 * @param  array  $data       Webhook data.
	 * @return array Result.
	 */
	protected function process_crm_webhook( $event_type, $data ) {
		global $wpdb;

		// Handle Deal updates
		if ( isset( $data['module'] ) && 'Deals' === $data['module'] ) {
			$zoho_deal_id = $data['ids'][0] ?? null;

			if ( ! $zoho_deal_id ) {
				return array( 'skipped' => 'No deal ID provided' );
			}

			// Find local project
			$project = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id FROM ' . ICT_PROJECTS_TABLE . ' WHERE zoho_crm_id = %s',
					$zoho_deal_id
				)
			);

			if ( $project ) {
				// Queue sync to update local project
				ICT_Helper::queue_sync(
					array(
						'entity_type'  => 'project',
						'entity_id'    => $project->id,
						'action'       => 'update',
						'zoho_service' => 'crm',
						'priority'     => 1,
					)
				);

				return array( 'queued' => 'Project sync queued' );
			}

			return array( 'skipped' => 'Project not found locally' );
		}

		return array( 'processed' => 'Event acknowledged' );
	}

	/**
	 * Process FSM webhook.
	 *
	 * @since  1.0.0
	 * @param  string $event_type Event type.
	 * @param  array  $data       Webhook data.
	 * @return array Result.
	 */
	protected function process_fsm_webhook( $event_type, $data ) {
		// Handle work order updates
		if ( 'workorder.updated' === $event_type || 'workorder.created' === $event_type ) {
			$work_order_id = $data['workorder']['id'] ?? null;

			if ( $work_order_id ) {
				// Queue sync for work order
				do_action( 'ict_fsm_workorder_webhook', $work_order_id, $event_type, $data );
			}
		}

		return array( 'processed' => 'FSM event acknowledged' );
	}

	/**
	 * Process Books webhook.
	 *
	 * @since  1.0.0
	 * @param  string $event_type Event type.
	 * @param  array  $data       Webhook data.
	 * @return array Result.
	 */
	protected function process_books_webhook( $event_type, $data ) {
		global $wpdb;

		// Handle item updates
		if ( strpos( $event_type, 'item.' ) === 0 ) {
			$zoho_item_id = $data['item']['item_id'] ?? null;

			if ( $zoho_item_id ) {
				$inventory_item = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT id FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE zoho_books_item_id = %s',
						$zoho_item_id
					)
				);

				if ( $inventory_item ) {
					ICT_Helper::queue_sync(
						array(
							'entity_type'  => 'inventory_item',
							'entity_id'    => $inventory_item->id,
							'action'       => 'update',
							'zoho_service' => 'books',
							'priority'     => 2,
						)
					);
				}
			}
		}

		return array( 'processed' => 'Books event acknowledged' );
	}

	/**
	 * Process People webhook.
	 *
	 * @since  1.0.0
	 * @param  string $event_type Event type.
	 * @param  array  $data       Webhook data.
	 * @return array Result.
	 */
	protected function process_people_webhook( $event_type, $data ) {
		// Handle timesheet approvals
		if ( 'timesheet.approved' === $event_type || 'timesheet.rejected' === $event_type ) {
			$zoho_timesheet_id = $data['recordId'] ?? null;

			if ( $zoho_timesheet_id ) {
				global $wpdb;

				$time_entry = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT id FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE zoho_people_id = %s',
						$zoho_timesheet_id
					)
				);

				if ( $time_entry ) {
					// Update local time entry status
					$status = ( 'timesheet.approved' === $event_type ) ? 'approved' : 'rejected';

					$wpdb->update(
						ICT_TIME_ENTRIES_TABLE,
						array(
							'status'      => $status,
							'approved_at' => current_time( 'mysql' ),
						),
						array( 'id' => $time_entry->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);

					// Trigger notification
					do_action( 'ict_timesheet_status_changed', $time_entry->id, $status );
				}
			}
		}

		return array( 'processed' => 'People event acknowledged' );
	}

	/**
	 * Process Desk webhook.
	 *
	 * @since  1.0.0
	 * @param  string $event_type Event type.
	 * @param  array  $data       Webhook data.
	 * @return array Result.
	 */
	protected function process_desk_webhook( $event_type, $data ) {
		// Handle ticket updates
		if ( strpos( $event_type, 'ticket.' ) === 0 ) {
			$ticket_id = $data['ticket']['id'] ?? null;

			if ( $ticket_id ) {
				do_action( 'ict_desk_ticket_webhook', $ticket_id, $event_type, $data );
			}
		}

		return array( 'processed' => 'Desk event acknowledged' );
	}

	/**
	 * Log webhook activity.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @param  string $status  Status (received, processed, error, rejected).
	 * @param  string $body    Request body.
	 * @param  array  $meta    Additional metadata.
	 * @return void
	 */
	protected function log_webhook( $service, $status, $body, $meta = array() ) {
		global $wpdb;

		$wpdb->insert(
			ICT_SYNC_LOG_TABLE,
			array(
				'entity_type'   => 'webhook',
				'direction'     => 'inbound',
				'zoho_service'  => $service,
				'action'        => $status,
				'status'        => ( 'error' === $status || 'rejected' === $status ) ? 'error' : 'success',
				'request_data'  => wp_json_encode( $meta ),
				'response_data' => substr( $body, 0, 10000 ), // Limit size
				'synced_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
