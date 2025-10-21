<?php
/**
 * REST API: Purchase Orders Controller
 *
 * Handles purchase order management endpoints including:
 * - PO CRUD operations
 * - PO approval workflow
 * - PO line items
 * - PO receiving
 * - PO status updates
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Purchase Orders REST Controller
 */
class ICT_REST_Purchase_Orders_Controller extends WP_REST_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'ict/v1';
		$this->rest_base = 'purchase-orders';
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		// List purchase orders
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
				),
			)
		);

		// Single purchase order
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
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		// PO line items
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_line_items' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			)
		);

		// Approve PO
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approve_po' ),
				'permission_callback' => array( $this, 'approve_permissions_check' ),
				'args'                => array(
					'notes' => array(
						'description' => __( 'Approval notes.', 'ict-platform' ),
						'type'        => 'string',
					),
				),
			)
		);

		// Mark as ordered
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mark_ordered' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'order_reference' => array(
						'description' => __( 'Supplier order reference number.', 'ict-platform' ),
						'type'        => 'string',
					),
				),
			)
		);

		// Mark as received
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/receive',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive_po' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'received_items' => array(
						'description' => __( 'Array of received line items with quantities.', 'ict-platform' ),
						'type'        => 'array',
						'required'    => true,
					),
					'notes'          => array(
						'description' => __( 'Receiving notes.', 'ict-platform' ),
						'type'        => 'string',
					),
				),
			)
		);

		// Cancel PO
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_po' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'reason' => array(
						'description' => __( 'Cancellation reason.', 'ict-platform' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);

		// Generate PO number
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate-number',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_po_number' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);
	}

	/**
	 * Get purchase orders
	 */
	public function get_items( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;

		// Pagination
		$page     = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page = absint( $request->get_param( 'per_page' ) ?: 20 );
		$offset   = ( $page - 1 ) * $per_page;

		// Build WHERE clause
		$where_conditions = array( '1=1' );
		$where_values     = array();

		// Status filter
		if ( $status = $request->get_param( 'status' ) ) {
			$where_conditions[] = 'status = %s';
			$where_values[]     = $status;
		}

		// Project filter
		if ( $project_id = $request->get_param( 'project_id' ) ) {
			$where_conditions[] = 'project_id = %d';
			$where_values[]     = absint( $project_id );
		}

		// Supplier filter
		if ( $supplier_id = $request->get_param( 'supplier_id' ) ) {
			$where_conditions[] = 'supplier_id = %d';
			$where_values[]     = absint( $supplier_id );
		}

		// Date range filter
		if ( $date_from = $request->get_param( 'date_from' ) ) {
			$where_conditions[] = 'po_date >= %s';
			$where_values[]     = sanitize_text_field( $date_from );
		}

		if ( $date_to = $request->get_param( 'date_to' ) ) {
			$where_conditions[] = 'po_date <= %s';
			$where_values[]     = sanitize_text_field( $date_to );
		}

		// Search
		if ( $search = $request->get_param( 'search' ) ) {
			$where_conditions[] = '(po_number LIKE %s OR notes LIKE %s)';
			$search_term        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Get total count
		$count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_query );

		// Get items
		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY po_date DESC, id DESC LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$items = $wpdb->get_results(
			$wpdb->prepare( $query, ...$query_values ),
			ARRAY_A
		);

		// Convert numeric fields
		foreach ( $items as &$item ) {
			$item = $this->prepare_item_for_response( $item );
		}

		return rest_ensure_response(
			array(
				'data'        => $items,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Get single purchase order
	 */
	public function get_item( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $item ) ) {
			return new WP_Error(
				'ict_po_not_found',
				__( 'Purchase order not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$item = $this->prepare_item_for_response( $item );

		// Get line items
		$item['line_items'] = $this->get_po_line_items( $id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
			)
		);
	}

	/**
	 * Create purchase order
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;

		// Generate PO number if not provided
		$po_number = $request->get_param( 'po_number' );
		if ( empty( $po_number ) ) {
			$po_number = $this->generate_unique_po_number();
		}

		$data = array(
			'po_number'     => sanitize_text_field( $po_number ),
			'project_id'    => absint( $request->get_param( 'project_id' ) ?? 0 ),
			'supplier_id'   => absint( $request->get_param( 'supplier_id' ) ),
			'po_date'       => sanitize_text_field( $request->get_param( 'po_date' ) ?? current_time( 'mysql' ) ),
			'delivery_date' => sanitize_text_field( $request->get_param( 'delivery_date' ) ?? '' ),
			'status'        => 'draft',
			'subtotal'      => floatval( $request->get_param( 'subtotal' ) ?? 0 ),
			'tax_amount'    => floatval( $request->get_param( 'tax_amount' ) ?? 0 ),
			'shipping_cost' => floatval( $request->get_param( 'shipping_cost' ) ?? 0 ),
			'total_amount'  => floatval( $request->get_param( 'total_amount' ) ?? 0 ),
			'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' ),
			'created_by'    => get_current_user_id(),
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table_name, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ict_po_create_failed',
				__( 'Failed to create purchase order.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$po_id = $wpdb->insert_id;

		// Create line items
		$line_items = $request->get_param( 'line_items' );
		if ( ! empty( $line_items ) && is_array( $line_items ) ) {
			$this->create_line_items( $po_id, $line_items );
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $po_id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );
		$item['line_items'] = $this->get_po_line_items( $po_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Purchase order created successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Update purchase order
	 */
	public function update_item( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );

		// Check if PO exists
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $existing ) ) {
			return new WP_Error(
				'ict_po_not_found',
				__( 'Purchase order not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		// Don't allow editing if status is not draft or pending
		if ( ! in_array( $existing['status'], array( 'draft', 'pending' ), true ) ) {
			return new WP_Error(
				'ict_po_locked',
				__( 'Cannot edit purchase order in current status.', 'ict-platform' ),
				array( 'status' => 400 )
			);
		}

		$data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		// Update allowed fields
		$updateable_fields = array(
			'supplier_id',
			'po_date',
			'delivery_date',
			'subtotal',
			'tax_amount',
			'shipping_cost',
			'total_amount',
			'notes',
		);

		foreach ( $updateable_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$value = $request->get_param( $field );
				if ( in_array( $field, array( 'subtotal', 'tax_amount', 'shipping_cost', 'total_amount' ), true ) ) {
					$data[ $field ] = floatval( $value );
				} elseif ( in_array( $field, array( 'supplier_id' ), true ) ) {
					$data[ $field ] = absint( $value );
				} else {
					$data[ $field ] = sanitize_text_field( $value );
				}
			}
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_po_update_failed',
				__( 'Failed to update purchase order.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		// Update line items if provided
		if ( $request->has_param( 'line_items' ) ) {
			$line_items = $request->get_param( 'line_items' );
			// Delete existing line items
			$wpdb->delete(
				$wpdb->prefix . 'ict_po_line_items',
				array( 'po_id' => $id )
			);
			// Create new line items
			$this->create_line_items( $id, $line_items );
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );
		$item['line_items'] = $this->get_po_line_items( $id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Purchase order updated successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Delete purchase order
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );

		// Check if PO exists and is in draft status
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $existing ) ) {
			return new WP_Error(
				'ict_po_not_found',
				__( 'Purchase order not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		if ( 'draft' !== $existing['status'] ) {
			return new WP_Error(
				'ict_po_cannot_delete',
				__( 'Only draft purchase orders can be deleted.', 'ict-platform' ),
				array( 'status' => 400 )
			);
		}

		// Delete line items first
		$wpdb->delete(
			$wpdb->prefix . 'ict_po_line_items',
			array( 'po_id' => $id )
		);

		// Delete PO
		$result = $wpdb->delete( $table_name, array( 'id' => $id ) );

		if ( false === $result || 0 === $result ) {
			return new WP_Error(
				'ict_po_delete_failed',
				__( 'Failed to delete purchase order.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Purchase order deleted successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Approve purchase order
	 */
	public function approve_po( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );

		$result = $wpdb->update(
			$table_name,
			array(
				'status'       => 'approved',
				'approved_by'  => get_current_user_id(),
				'approved_at'  => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_po_approve_failed',
				__( 'Failed to approve purchase order.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Purchase order approved successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Mark as ordered
	 */
	public function mark_ordered( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );

		$result = $wpdb->update(
			$table_name,
			array(
				'status'     => 'ordered',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_po_order_failed',
				__( 'Failed to mark purchase order as ordered.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Purchase order marked as ordered.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Receive purchase order
	 */
	public function receive_po( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );
		$received_items = $request->get_param( 'received_items' );

		// Update inventory for received items
		foreach ( $received_items as $item ) {
			if ( ! isset( $item['inventory_id'] ) || ! isset( $item['quantity_received'] ) ) {
				continue;
			}

			$inventory_id = absint( $item['inventory_id'] );
			$qty_received = floatval( $item['quantity_received'] );

			// Get current inventory
			$inventory = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM " . ICT_INVENTORY_TABLE . " WHERE id = %d",
					$inventory_id
				),
				ARRAY_A
			);

			if ( ! empty( $inventory ) ) {
				// Update quantity
				$new_qty = floatval( $inventory['quantity_on_hand'] ) + $qty_received;

				$wpdb->update(
					ICT_INVENTORY_TABLE,
					array(
						'quantity_on_hand'   => $new_qty,
						'quantity_available' => $new_qty - floatval( $inventory['quantity_allocated'] ),
						'updated_at'         => current_time( 'mysql' ),
					),
					array( 'id' => $inventory_id )
				);

				// Create stock history
				$wpdb->insert(
					$wpdb->prefix . 'ict_stock_history',
					array(
						'item_id'         => $inventory_id,
						'quantity_change' => $qty_received,
						'adjustment_type' => 'received',
						'reason'          => sprintf( 'Received from PO #%s', $id ),
						'reference'       => 'PO-' . $id,
						'user_id'         => get_current_user_id(),
						'created_at'      => current_time( 'mysql' ),
					)
				);
			}
		}

		// Update PO status
		$result = $wpdb->update(
			$table_name,
			array(
				'status'        => 'received',
				'received_date' => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_po_receive_failed',
				__( 'Failed to mark purchase order as received.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Purchase order received and inventory updated.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Cancel purchase order
	 */
	public function cancel_po( $request ) {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;
		$id         = absint( $request['id'] );

		$result = $wpdb->update(
			$table_name,
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_po_cancel_failed',
				__( 'Failed to cancel purchase order.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Purchase order cancelled.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Get line items
	 */
	public function get_line_items( $request ) {
		$po_id = absint( $request['id'] );
		$items = $this->get_po_line_items( $po_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $items,
			)
		);
	}

	/**
	 * Generate PO number
	 */
	public function generate_po_number( $request ) {
		$po_number = $this->generate_unique_po_number();

		return rest_ensure_response(
			array(
				'success'   => true,
				'po_number' => $po_number,
			)
		);
	}

	/**
	 * Get PO line items
	 */
	protected function get_po_line_items( $po_id ) {
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_po_line_items WHERE po_id = %d ORDER BY line_order ASC",
				$po_id
			),
			ARRAY_A
		);

		foreach ( $items as &$item ) {
			$item['quantity']      = floatval( $item['quantity'] );
			$item['unit_price']    = floatval( $item['unit_price'] );
			$item['line_total']    = floatval( $item['line_total'] );
			$item['inventory_id']  = absint( $item['inventory_id'] );
		}

		return $items;
	}

	/**
	 * Create line items
	 */
	protected function create_line_items( $po_id, $line_items ) {
		global $wpdb;
		$line_order = 0;

		foreach ( $line_items as $item ) {
			$line_order++;

			$wpdb->insert(
				$wpdb->prefix . 'ict_po_line_items',
				array(
					'po_id'        => $po_id,
					'inventory_id' => absint( $item['inventory_id'] ?? 0 ),
					'item_name'    => sanitize_text_field( $item['item_name'] ?? '' ),
					'description'  => sanitize_textarea_field( $item['description'] ?? '' ),
					'quantity'     => floatval( $item['quantity'] ?? 0 ),
					'unit_price'   => floatval( $item['unit_price'] ?? 0 ),
					'line_total'   => floatval( $item['line_total'] ?? 0 ),
					'line_order'   => $line_order,
					'created_at'   => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Generate unique PO number
	 */
	protected function generate_unique_po_number() {
		global $wpdb;
		$table_name = ICT_PURCHASE_ORDERS_TABLE;

		// Format: PO-YYYYMMDD-XXX
		$prefix = 'PO-' . date( 'Ymd' ) . '-';

		// Get last PO number for today
		$last_po = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT po_number FROM $table_name WHERE po_number LIKE %s ORDER BY id DESC LIMIT 1",
				$prefix . '%'
			)
		);

		if ( $last_po ) {
			$sequence = intval( substr( $last_po, -3 ) ) + 1;
		} else {
			$sequence = 1;
		}

		return $prefix . str_pad( $sequence, 3, '0', STR_PAD_LEFT );
	}

	/**
	 * Prepare item for response
	 */
	protected function prepare_item_for_response( $item ) {
		// Convert numeric fields
		$numeric_fields = array( 'subtotal', 'tax_amount', 'shipping_cost', 'total_amount' );

		foreach ( $numeric_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$item[ $field ] = floatval( $item[ $field ] );
			}
		}

		return $item;
	}

	/**
	 * Permissions checks
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_purchase_orders' ) || current_user_can( 'manage_options' );
	}

	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_purchase_orders' ) || current_user_can( 'manage_options' );
	}

	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_purchase_orders' ) || current_user_can( 'manage_options' );
	}

	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_purchase_orders' ) || current_user_can( 'manage_options' );
	}

	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_purchase_orders' ) || current_user_can( 'manage_options' );
	}

	public function approve_permissions_check( $request ) {
		return current_user_can( 'approve_purchase_orders' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get collection params
	 */
	public function get_collection_params() {
		return array(
			'page'        => array(
				'description' => __( 'Current page of the collection.', 'ict-platform' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'    => array(
				'description' => __( 'Maximum number of items to be returned.', 'ict-platform' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'status'      => array(
				'description' => __( 'Filter by status.', 'ict-platform' ),
				'type'        => 'string',
				'enum'        => array( 'draft', 'pending', 'approved', 'ordered', 'received', 'cancelled' ),
			),
			'project_id'  => array(
				'description' => __( 'Filter by project.', 'ict-platform' ),
				'type'        => 'integer',
			),
			'supplier_id' => array(
				'description' => __( 'Filter by supplier.', 'ict-platform' ),
				'type'        => 'integer',
			),
			'date_from'   => array(
				'description' => __( 'Filter by date from.', 'ict-platform' ),
				'type'        => 'string',
				'format'      => 'date',
			),
			'date_to'     => array(
				'description' => __( 'Filter by date to.', 'ict-platform' ),
				'type'        => 'string',
				'format'      => 'date',
			),
			'search'      => array(
				'description' => __( 'Search term.', 'ict-platform' ),
				'type'        => 'string',
			),
		);
	}
}
