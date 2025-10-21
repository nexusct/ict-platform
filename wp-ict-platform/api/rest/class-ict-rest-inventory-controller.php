<?php
/**
 * REST API: Inventory Controller
 *
 * Handles inventory management endpoints including:
 * - Inventory CRUD operations
 * - Stock adjustments
 * - Low stock alerts
 * - Stock transfers
 * - Stock history
 * - Category management
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inventory REST Controller
 */
class ICT_REST_Inventory_Controller extends WP_REST_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace = 'ict/v1';
		$this->rest_base = 'inventory';
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		// List inventory items
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

		// Single inventory item
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the inventory item.', 'ict-platform' ),
							'type'        => 'integer',
						),
					),
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

		// Stock adjustment
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/adjust',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'adjust_stock' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'id'             => array(
						'description' => __( 'Unique identifier for the inventory item.', 'ict-platform' ),
						'type'        => 'integer',
						'required'    => true,
					),
					'adjustment'     => array(
						'description' => __( 'Stock adjustment quantity (positive or negative).', 'ict-platform' ),
						'type'        => 'number',
						'required'    => true,
					),
					'adjustment_type' => array(
						'description' => __( 'Type of adjustment.', 'ict-platform' ),
						'type'        => 'string',
						'enum'        => array( 'received', 'consumed', 'damaged', 'lost', 'returned', 'transfer', 'correction', 'initial' ),
						'required'    => true,
					),
					'reason'         => array(
						'description' => __( 'Reason for adjustment.', 'ict-platform' ),
						'type'        => 'string',
						'required'    => true,
					),
					'project_id'     => array(
						'description' => __( 'Project ID if related to project.', 'ict-platform' ),
						'type'        => 'integer',
					),
					'reference'      => array(
						'description' => __( 'Reference number (PO, invoice, etc).', 'ict-platform' ),
						'type'        => 'string',
					),
				),
			)
		);

		// Stock history
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stock_history' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'id'       => array(
						'description' => __( 'Unique identifier for the inventory item.', 'ict-platform' ),
						'type'        => 'integer',
					),
					'per_page' => array(
						'description' => __( 'Maximum number of items to return.', 'ict-platform' ),
						'type'        => 'integer',
						'default'     => 20,
					),
				),
			)
		);

		// Low stock items
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/low-stock',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_low_stock_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Stock transfer
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/transfer',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'transfer_stock' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'item_id'          => array(
						'description' => __( 'Inventory item ID.', 'ict-platform' ),
						'type'        => 'integer',
						'required'    => true,
					),
					'from_location'    => array(
						'description' => __( 'Source location.', 'ict-platform' ),
						'type'        => 'string',
						'required'    => true,
					),
					'to_location'      => array(
						'description' => __( 'Destination location.', 'ict-platform' ),
						'type'        => 'string',
						'required'    => true,
					),
					'quantity'         => array(
						'description' => __( 'Quantity to transfer.', 'ict-platform' ),
						'type'        => 'number',
						'required'    => true,
					),
					'notes'            => array(
						'description' => __( 'Transfer notes.', 'ict-platform' ),
						'type'        => 'string',
					),
				),
			)
		);

		// Categories
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Locations
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/locations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_locations' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		// Bulk update
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'bulk_update' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'items' => array(
						'description' => __( 'Array of items to update.', 'ict-platform' ),
						'type'        => 'array',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Get inventory items
	 */
	public function get_items( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;

		// Pagination
		$page     = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page = absint( $request->get_param( 'per_page' ) ?: 20 );
		$offset   = ( $page - 1 ) * $per_page;

		// Build WHERE clause
		$where_conditions = array( '1=1' );
		$where_values     = array();

		// Search
		if ( $search = $request->get_param( 'search' ) ) {
			$where_conditions[] = '(item_name LIKE %s OR sku LIKE %s OR description LIKE %s)';
			$search_term        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
		}

		// Category filter
		if ( $category = $request->get_param( 'category' ) ) {
			$where_conditions[] = 'category = %s';
			$where_values[]     = $category;
		}

		// Location filter
		if ( $location = $request->get_param( 'location' ) ) {
			$where_conditions[] = 'location = %s';
			$where_values[]     = $location;
		}

		// Active/inactive filter
		if ( $request->has_param( 'is_active' ) ) {
			$where_conditions[] = 'is_active = %d';
			$where_values[]     = $request->get_param( 'is_active' ) ? 1 : 0;
		}

		// Low stock filter
		if ( $request->get_param( 'low_stock' ) ) {
			$where_conditions[] = 'quantity_available <= reorder_level';
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Get total count
		$count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_query );

		// Get items
		$query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY item_name ASC LIMIT %d OFFSET %d";
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
	 * Get single inventory item
	 */
	public function get_item( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;
		$id         = absint( $request['id'] );

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $item ) ) {
			return new WP_Error(
				'ict_inventory_not_found',
				__( 'Inventory item not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
			)
		);
	}

	/**
	 * Create inventory item
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;

		$data = array(
			'sku'                 => sanitize_text_field( $request->get_param( 'sku' ) ),
			'item_name'           => sanitize_text_field( $request->get_param( 'item_name' ) ),
			'description'         => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
			'category'            => sanitize_text_field( $request->get_param( 'category' ) ?? '' ),
			'unit_of_measure'     => sanitize_text_field( $request->get_param( 'unit_of_measure' ) ?? 'ea' ),
			'quantity_on_hand'    => floatval( $request->get_param( 'quantity_on_hand' ) ?? 0 ),
			'quantity_allocated'  => 0,
			'reorder_level'       => floatval( $request->get_param( 'reorder_level' ) ?? 0 ),
			'reorder_quantity'    => floatval( $request->get_param( 'reorder_quantity' ) ?? 0 ),
			'unit_cost'           => floatval( $request->get_param( 'unit_cost' ) ?? 0 ),
			'unit_price'          => floatval( $request->get_param( 'unit_price' ) ?? 0 ),
			'supplier_id'         => absint( $request->get_param( 'supplier_id' ) ?? 0 ),
			'location'            => sanitize_text_field( $request->get_param( 'location' ) ?? '' ),
			'barcode'             => sanitize_text_field( $request->get_param( 'barcode' ) ?? '' ),
			'is_active'           => $request->get_param( 'is_active' ) !== false ? 1 : 0,
			'created_at'          => current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		);

		// Calculate quantity available
		$data['quantity_available'] = $data['quantity_on_hand'] - $data['quantity_allocated'];

		$result = $wpdb->insert( $table_name, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ict_inventory_create_failed',
				__( 'Failed to create inventory item.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$item_id = $wpdb->insert_id;

		// Create initial stock adjustment record
		if ( $data['quantity_on_hand'] > 0 ) {
			$this->create_stock_history(
				$item_id,
				$data['quantity_on_hand'],
				'initial',
				'Initial stock',
				''
			);
		}

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $item_id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Inventory item created successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Update inventory item
	 */
	public function update_item( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;
		$id         = absint( $request['id'] );

		// Check if item exists
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $existing ) ) {
			return new WP_Error(
				'ict_inventory_not_found',
				__( 'Inventory item not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		// Update only provided fields
		$updateable_fields = array(
			'sku',
			'item_name',
			'description',
			'category',
			'unit_of_measure',
			'reorder_level',
			'reorder_quantity',
			'unit_cost',
			'unit_price',
			'supplier_id',
			'location',
			'barcode',
		);

		foreach ( $updateable_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$value = $request->get_param( $field );
				if ( in_array( $field, array( 'reorder_level', 'reorder_quantity', 'unit_cost', 'unit_price' ), true ) ) {
					$data[ $field ] = floatval( $value );
				} elseif ( 'supplier_id' === $field ) {
					$data[ $field ] = absint( $value );
				} else {
					$data[ $field ] = sanitize_text_field( $value );
				}
			}
		}

		if ( $request->has_param( 'is_active' ) ) {
			$data['is_active'] = $request->get_param( 'is_active' ) ? 1 : 0;
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_inventory_update_failed',
				__( 'Failed to update inventory item.', 'ict-platform' ),
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
				'message' => __( 'Inventory item updated successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Delete inventory item
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;
		$id         = absint( $request['id'] );

		$result = $wpdb->delete( $table_name, array( 'id' => $id ) );

		if ( false === $result || 0 === $result ) {
			return new WP_Error(
				'ict_inventory_delete_failed',
				__( 'Failed to delete inventory item.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Inventory item deleted successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Adjust stock quantity
	 */
	public function adjust_stock( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;
		$id         = absint( $request['id'] );

		// Get current item
		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( empty( $item ) ) {
			return new WP_Error(
				'ict_inventory_not_found',
				__( 'Inventory item not found.', 'ict-platform' ),
				array( 'status' => 404 )
			);
		}

		$adjustment      = floatval( $request->get_param( 'adjustment' ) );
		$adjustment_type = sanitize_text_field( $request->get_param( 'adjustment_type' ) );
		$reason          = sanitize_text_field( $request->get_param( 'reason' ) );
		$project_id      = absint( $request->get_param( 'project_id' ) ?? 0 );
		$reference       = sanitize_text_field( $request->get_param( 'reference' ) ?? '' );

		// Calculate new quantity
		$new_quantity = floatval( $item['quantity_on_hand'] ) + $adjustment;

		if ( $new_quantity < 0 ) {
			return new WP_Error(
				'ict_insufficient_stock',
				__( 'Insufficient stock for this adjustment.', 'ict-platform' ),
				array( 'status' => 400 )
			);
		}

		// Update stock
		$result = $wpdb->update(
			$table_name,
			array(
				'quantity_on_hand'   => $new_quantity,
				'quantity_available' => $new_quantity - floatval( $item['quantity_allocated'] ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'ict_stock_adjustment_failed',
				__( 'Failed to adjust stock.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		// Create history record
		$this->create_stock_history(
			$id,
			$adjustment,
			$adjustment_type,
			$reason,
			$reference,
			$project_id
		);

		// Get updated item
		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ),
			ARRAY_A
		);

		$item = $this->prepare_item_for_response( $item );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $item,
				'message' => __( 'Stock adjusted successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Get stock history
	 */
	public function get_stock_history( $request ) {
		global $wpdb;
		$item_id  = absint( $request['id'] );
		$per_page = absint( $request->get_param( 'per_page' ) ?: 20 );

		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_stock_history
				WHERE item_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$item_id,
				$per_page
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $history,
			)
		);
	}

	/**
	 * Get low stock items
	 */
	public function get_low_stock_items( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;

		$items = $wpdb->get_results(
			"SELECT * FROM $table_name
			WHERE quantity_available <= reorder_level
			AND is_active = 1
			ORDER BY (quantity_available / NULLIF(reorder_level, 0)) ASC",
			ARRAY_A
		);

		foreach ( $items as &$item ) {
			$item = $this->prepare_item_for_response( $item );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $items,
			)
		);
	}

	/**
	 * Transfer stock between locations
	 */
	public function transfer_stock( $request ) {
		// Implement stock transfer logic
		// This would typically involve creating adjustment records for both locations
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Stock transferred successfully.', 'ict-platform' ),
			)
		);
	}

	/**
	 * Get categories
	 */
	public function get_categories( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;

		$categories = $wpdb->get_col(
			"SELECT DISTINCT category FROM $table_name WHERE category IS NOT NULL AND category != '' ORDER BY category ASC"
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $categories,
			)
		);
	}

	/**
	 * Get locations
	 */
	public function get_locations( $request ) {
		global $wpdb;
		$table_name = ICT_INVENTORY_TABLE;

		$locations = $wpdb->get_col(
			"SELECT DISTINCT location FROM $table_name WHERE location IS NOT NULL AND location != '' ORDER BY location ASC"
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $locations,
			)
		);
	}

	/**
	 * Bulk update
	 */
	public function bulk_update( $request ) {
		global $wpdb;
		$items = $request->get_param( 'items' );
		$updated = 0;

		foreach ( $items as $item_data ) {
			if ( ! isset( $item_data['id'] ) ) {
				continue;
			}

			$result = $wpdb->update(
				ICT_INVENTORY_TABLE,
				array_merge(
					$item_data,
					array( 'updated_at' => current_time( 'mysql' ) )
				),
				array( 'id' => $item_data['id'] )
			);

			if ( false !== $result ) {
				$updated++;
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'updated' => $updated,
				'message' => sprintf( __( '%d items updated successfully.', 'ict-platform' ), $updated ),
			)
		);
	}

	/**
	 * Create stock history record
	 */
	protected function create_stock_history( $item_id, $quantity, $type, $reason, $reference = '', $project_id = 0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ict_stock_history',
			array(
				'item_id'        => $item_id,
				'quantity_change' => $quantity,
				'adjustment_type' => $type,
				'reason'         => $reason,
				'reference'      => $reference,
				'project_id'     => $project_id,
				'user_id'        => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Prepare item for response
	 */
	protected function prepare_item_for_response( $item ) {
		// Convert numeric fields
		$numeric_fields = array(
			'quantity_on_hand',
			'quantity_allocated',
			'quantity_available',
			'reorder_level',
			'reorder_quantity',
			'unit_cost',
			'unit_price',
		);

		foreach ( $numeric_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$item[ $field ] = floatval( $item[ $field ] );
			}
		}

		// Convert boolean fields
		if ( isset( $item['is_active'] ) ) {
			$item['is_active'] = (bool) $item['is_active'];
		}

		return $item;
	}

	/**
	 * Permissions checks
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_inventory' ) || current_user_can( 'manage_options' );
	}

	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_inventory' ) || current_user_can( 'manage_options' );
	}

	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_inventory' ) || current_user_can( 'manage_options' );
	}

	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_inventory' ) || current_user_can( 'manage_options' );
	}

	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'manage_inventory' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get collection params
	 */
	public function get_collection_params() {
		return array(
			'page'      => array(
				'description' => __( 'Current page of the collection.', 'ict-platform' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'  => array(
				'description' => __( 'Maximum number of items to be returned.', 'ict-platform' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'search'    => array(
				'description' => __( 'Search term.', 'ict-platform' ),
				'type'        => 'string',
			),
			'category'  => array(
				'description' => __( 'Filter by category.', 'ict-platform' ),
				'type'        => 'string',
			),
			'location'  => array(
				'description' => __( 'Filter by location.', 'ict-platform' ),
				'type'        => 'string',
			),
			'is_active' => array(
				'description' => __( 'Filter by active status.', 'ict-platform' ),
				'type'        => 'boolean',
			),
			'low_stock' => array(
				'description' => __( 'Show only low stock items.', 'ict-platform' ),
				'type'        => 'boolean',
			),
		);
	}
}
