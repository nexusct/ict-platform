<?php
/**
 * QuoteWerks Integration Adapter
 *
 * Handles bidirectional sync between QuoteWerks and the ICT Platform.
 * Syncs quotes, orders, and customer data.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_QuoteWerks_Adapter
 *
 * Manages QuoteWerks integration for quotes and orders.
 */
class ICT_QuoteWerks_Adapter {

	/**
	 * QuoteWerks API base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * QuoteWerks API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * QuoteWerks company ID.
	 *
	 * @var string
	 */
	private $company_id;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_url    = get_option( 'ict_quotewerks_api_url', '' );
		$this->api_key    = get_option( 'ict_quotewerks_api_key', '' );
		$this->company_id = get_option( 'ict_quotewerks_company_id', '' );
	}

	/**
	 * Test QuoteWerks connection.
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->api_url ) || empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_credentials',
				'QuoteWerks API credentials are not configured'
			);
		}

		$response = $this->make_request( 'GET', '/api/v1/ping' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Sync quote from QuoteWerks to WordPress.
	 *
	 * @param string $quote_id QuoteWerks quote ID.
	 * @return int|WP_Error Project ID or error.
	 */
	public function sync_quote_to_project( $quote_id ) {
		// Get quote from QuoteWerks
		$quote = $this->get_quote( $quote_id );

		if ( is_wp_error( $quote ) ) {
			return $quote;
		}

		// Check if project already exists
		global $wpdb;
		$existing_project = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . ICT_PROJECTS_TABLE . ' WHERE quotewerks_id = %s',
				$quote_id
			)
		);

		if ( $existing_project ) {
			// Update existing project
			return $this->update_project_from_quote( $existing_project, $quote );
		} else {
			// Create new project
			return $this->create_project_from_quote( $quote );
		}
	}

	/**
	 * Get quote from QuoteWerks.
	 *
	 * @param string $quote_id Quote ID.
	 * @return array|WP_Error Quote data or error.
	 */
	public function get_quote( $quote_id ) {
		$response = $this->make_request( 'GET', '/api/v1/quotes/' . $quote_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['quote'] ) ) {
			return new WP_Error(
				'invalid_response',
				'Invalid QuoteWerks API response'
			);
		}

		return $response['quote'];
	}

	/**
	 * Get all quotes from QuoteWerks.
	 *
	 * @param array $params Query parameters.
	 * @return array|WP_Error Quotes or error.
	 */
	public function get_quotes( $params = array() ) {
		$default_params = array(
			'status'   => 'approved',
			'per_page' => 50,
			'page'     => 1,
		);

		$params = wp_parse_args( $params, $default_params );

		$response = $this->make_request( 'GET', '/api/v1/quotes', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['quotes'] ?? array();
	}

	/**
	 * Create project from QuoteWerks quote.
	 *
	 * @param array $quote Quote data.
	 * @return int|WP_Error Project ID or error.
	 */
	private function create_project_from_quote( $quote ) {
		global $wpdb;

		// Prepare project data
		$project_data = array(
			'name'               => sanitize_text_field( $quote['quote_name'] ?? $quote['doc_number'] ),
			'description'        => sanitize_textarea_field( $quote['description'] ?? '' ),
			'client_name'        => sanitize_text_field( $quote['customer_name'] ?? '' ),
			'status'             => $this->map_quote_status( $quote['status'] ?? 'pending' ),
			'priority'           => $this->map_priority( $quote['priority'] ?? 'medium' ),
			'budget_amount'      => floatval( $quote['total_amount'] ?? 0 ),
			'start_date'         => $quote['start_date'] ?? null,
			'end_date'           => $quote['due_date'] ?? null,
			'quotewerks_id'      => sanitize_text_field( $quote['doc_number'] ),
			'quotewerks_sync_at' => current_time( 'mysql' ),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		// Insert project
		$result = $wpdb->insert(
			ICT_PROJECTS_TABLE,
			$project_data,
			array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to create project from QuoteWerks quote'
			);
		}

		$project_id = $wpdb->insert_id;

		// Sync line items as inventory requirements
		if ( ! empty( $quote['line_items'] ) ) {
			$this->sync_line_items_to_inventory( $project_id, $quote['line_items'] );
		}

		// Queue for Zoho CRM sync
		ICT_Helper::queue_sync(
			array(
				'entity_type'  => 'project',
				'entity_id'    => $project_id,
				'action'       => 'create',
				'zoho_service' => 'crm',
				'priority'     => 5,
				'payload'      => $project_data,
			)
		);

		return $project_id;
	}

	/**
	 * Update project from QuoteWerks quote.
	 *
	 * @param int   $project_id Project ID.
	 * @param array $quote Quote data.
	 * @return int|WP_Error Project ID or error.
	 */
	private function update_project_from_quote( $project_id, $quote ) {
		global $wpdb;

		$update_data = array(
			'name'               => sanitize_text_field( $quote['quote_name'] ?? $quote['doc_number'] ),
			'description'        => sanitize_textarea_field( $quote['description'] ?? '' ),
			'status'             => $this->map_quote_status( $quote['status'] ?? 'pending' ),
			'budget_amount'      => floatval( $quote['total_amount'] ?? 0 ),
			'quotewerks_sync_at' => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		$result = $wpdb->update(
			ICT_PROJECTS_TABLE,
			$update_data,
			array( 'id' => $project_id ),
			array( '%s', '%s', '%s', '%f', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to update project from QuoteWerks quote'
			);
		}

		// Queue for Zoho CRM sync
		ICT_Helper::queue_sync(
			array(
				'entity_type'  => 'project',
				'entity_id'    => $project_id,
				'action'       => 'update',
				'zoho_service' => 'crm',
				'priority'     => 5,
			)
		);

		return $project_id;
	}

	/**
	 * Sync line items to inventory.
	 *
	 * @param int   $project_id Project ID.
	 * @param array $line_items Line items.
	 */
	private function sync_line_items_to_inventory( $project_id, $line_items ) {
		global $wpdb;

		foreach ( $line_items as $item ) {
			// Check if inventory item exists by SKU
			$inventory_item = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE sku = %s',
					$item['sku'] ?? ''
				)
			);

			if ( ! $inventory_item && ! empty( $item['sku'] ) ) {
				// Create inventory item
				$wpdb->insert(
					ICT_INVENTORY_ITEMS_TABLE,
					array(
						'name'               => sanitize_text_field( $item['description'] ?? '' ),
						'sku'                => sanitize_text_field( $item['sku'] ),
						'category'           => 'Parts',
						'unit_price'         => floatval( $item['unit_price'] ?? 0 ),
						'quantity'           => 0,
						'reorder_level'      => 5,
						'quotewerks_item_id' => sanitize_text_field( $item['line_number'] ?? '' ),
						'created_at'         => current_time( 'mysql' ),
						'updated_at'         => current_time( 'mysql' ),
					)
				);
			}
		}
	}

	/**
	 * Push project to QuoteWerks as a quote.
	 *
	 * @param int $project_id Project ID.
	 * @return string|WP_Error Quote ID or error.
	 */
	public function push_project_to_quotewerks( $project_id ) {
		global $wpdb;

		// Get project
		$project = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d',
				$project_id
			),
			ARRAY_A
		);

		if ( ! $project ) {
			return new WP_Error( 'project_not_found', 'Project not found' );
		}

		// Check if already synced
		if ( ! empty( $project['quotewerks_id'] ) ) {
			return $this->update_quote_in_quotewerks( $project );
		}

		// Prepare quote data
		$quote_data = array(
			'doc_type'      => 'Quote',
			'customer_name' => $project['client_name'],
			'quote_name'    => $project['name'],
			'description'   => $project['description'],
			'total_amount'  => $project['budget_amount'],
			'status'        => $this->map_project_status( $project['status'] ),
			'start_date'    => $project['start_date'],
			'due_date'      => $project['end_date'],
			'line_items'    => $this->get_project_line_items( $project_id ),
		);

		// Create quote in QuoteWerks
		$response = $this->make_request( 'POST', '/api/v1/quotes', $quote_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$quote_id = $response['doc_number'] ?? null;

		if ( ! $quote_id ) {
			return new WP_Error(
				'invalid_response',
				'QuoteWerks did not return a quote ID'
			);
		}

		// Update project with QuoteWerks ID
		$wpdb->update(
			ICT_PROJECTS_TABLE,
			array(
				'quotewerks_id'      => $quote_id,
				'quotewerks_sync_at' => current_time( 'mysql' ),
			),
			array( 'id' => $project_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $quote_id;
	}

	/**
	 * Update quote in QuoteWerks.
	 *
	 * @param array $project Project data.
	 * @return string|WP_Error Quote ID or error.
	 */
	private function update_quote_in_quotewerks( $project ) {
		$quote_data = array(
			'customer_name' => $project['client_name'],
			'quote_name'    => $project['name'],
			'description'   => $project['description'],
			'total_amount'  => $project['budget_amount'],
			'status'        => $this->map_project_status( $project['status'] ),
		);

		$response = $this->make_request(
			'PUT',
			'/api/v1/quotes/' . $project['quotewerks_id'],
			$quote_data
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $project['quotewerks_id'];
	}

	/**
	 * Get project line items.
	 *
	 * @param int $project_id Project ID.
	 * @return array Line items.
	 */
	private function get_project_line_items( $project_id ) {
		// Get inventory items allocated to project
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_INVENTORY_ITEMS_TABLE . '
				WHERE project_id = %d',
				$project_id
			),
			ARRAY_A
		);

		$line_items = array();
		foreach ( $items as $item ) {
			$line_items[] = array(
				'sku'         => $item['sku'],
				'description' => $item['name'],
				'quantity'    => $item['quantity'],
				'unit_price'  => $item['unit_price'],
				'total'       => $item['quantity'] * $item['unit_price'],
			);
		}

		return $line_items;
	}

	/**
	 * Map QuoteWerks status to project status.
	 *
	 * @param string $qw_status QuoteWerks status.
	 * @return string Project status.
	 */
	private function map_quote_status( $qw_status ) {
		$status_map = array(
			'draft'    => 'planning',
			'pending'  => 'planning',
			'approved' => 'in-progress',
			'won'      => 'in-progress',
			'lost'     => 'cancelled',
			'expired'  => 'cancelled',
			'on_hold'  => 'on-hold',
		);

		return $status_map[ strtolower( $qw_status ) ] ?? 'planning';
	}

	/**
	 * Map project status to QuoteWerks status.
	 *
	 * @param string $project_status Project status.
	 * @return string QuoteWerks status.
	 */
	private function map_project_status( $project_status ) {
		$status_map = array(
			'planning'    => 'draft',
			'in-progress' => 'approved',
			'on-hold'     => 'on_hold',
			'completed'   => 'won',
			'cancelled'   => 'lost',
		);

		return $status_map[ $project_status ] ?? 'draft';
	}

	/**
	 * Map priority.
	 *
	 * @param string $priority Priority.
	 * @return string Mapped priority.
	 */
	private function map_priority( $priority ) {
		$priority_map = array(
			'1' => 'urgent',
			'2' => 'high',
			'3' => 'medium',
			'4' => 'low',
		);

		return $priority_map[ $priority ] ?? 'medium';
	}

	/**
	 * Make API request to QuoteWerks.
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @return array|WP_Error Response or error.
	 */
	private function make_request( $method, $endpoint, $data = array() ) {
		$url = rtrim( $this->api_url, '/' ) . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 400 ) {
			return new WP_Error(
				'api_error',
				sprintf( 'QuoteWerks API error: %s', $body ),
				array( 'status' => $code )
			);
		}

		return json_decode( $body, true );
	}

	/**
	 * Authenticate with QuoteWerks.
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return string|WP_Error API key or error.
	 */
	public function authenticate( $username, $password ) {
		$response = wp_remote_post(
			$this->api_url . '/api/v1/auth/login',
			array(
				'body'    => wp_json_encode(
					array(
						'username'   => $username,
						'password'   => $password,
						'company_id' => $this->company_id,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['api_key'] ) ) {
			return new WP_Error(
				'authentication_failed',
				'QuoteWerks authentication failed'
			);
		}

		return $body['api_key'];
	}
}
