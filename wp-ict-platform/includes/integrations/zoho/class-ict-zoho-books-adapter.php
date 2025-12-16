<?php
/**
 * Zoho Books Adapter
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_Books_Adapter
 *
 * Handles synchronization with Zoho Books (Items → Inventory, Vendors → Suppliers).
 */
class ICT_Zoho_Books_Adapter extends ICT_Zoho_API_Client {

	/**
	 * Organization ID (required for Books API).
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $organization_id;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( 'books' );
		$this->organization_id = get_option( 'ict_zoho_books_organization_id' );
	}

	/**
	 * Create entity in Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type (inventory_item, purchase_order).
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function create( $entity_type, $data ) {
		if ( empty( $this->organization_id ) ) {
			throw new Exception( __( 'Zoho Books organization ID not configured', 'ict-platform' ) );
		}

		switch ( $entity_type ) {
			case 'inventory_item':
				return $this->create_item( $data );
			case 'purchase_order':
				return $this->create_purchase_order( $data );
			default:
				throw new Exception( sprintf( __( 'Unknown entity type: %s', 'ict-platform' ), $entity_type ) );
		}
	}

	/**
	 * Update entity in Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function update( $entity_type, $entity_id, $data ) {
		if ( empty( $this->organization_id ) ) {
			throw new Exception( __( 'Zoho Books organization ID not configured', 'ict-platform' ) );
		}

		switch ( $entity_type ) {
			case 'inventory_item':
				return $this->update_item( $entity_id, $data );
			case 'purchase_order':
				return $this->update_purchase_order( $entity_id, $data );
			default:
				throw new Exception( sprintf( __( 'Unknown entity type: %s', 'ict-platform' ), $entity_type ) );
		}
	}

	/**
	 * Delete entity from Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function delete( $entity_type, $entity_id ) {
		// Zoho Books typically doesn't delete, just marks inactive
		return $this->update( $entity_type, $entity_id, array( 'is_active' => false ) );
	}

	/**
	 * Create item in Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  array $data Item data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	protected function create_item( $data ) {
		$payload = array(
			'name'            => $data['item_name'],
			'sku'             => $data['sku'],
			'description'     => $data['description'] ?? '',
			'rate'            => $data['unit_price'] ?? 0,
			'purchase_rate'   => $data['unit_cost'] ?? 0,
			'item_type'       => 'inventory',
			'track_inventory' => true,
			'initial_stock'   => $data['quantity_on_hand'] ?? 0,
			'reorder_level'   => $data['reorder_level'] ?? 0,
		);

		$response = $this->post(
			'/items?organization_id=' . $this->organization_id,
			$payload
		);

		if ( isset( $response['data']['item']['item_id'] ) ) {
			$zoho_id = $response['data']['item']['item_id'];

			// Update local record
			global $wpdb;
			$wpdb->update(
				ICT_INVENTORY_ITEMS_TABLE,
				array(
					'zoho_books_item_id' => $zoho_id,
					'sync_status'        => 'synced',
					'last_synced'        => current_time( 'mysql' ),
				),
				array( 'id' => $data['id'] ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return array(
				'zoho_id' => $zoho_id,
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create item in Zoho Books', 'ict-platform' ) );
	}

	/**
	 * Update item in Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  int   $entity_id Local entity ID.
	 * @param  array $data      Item data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	protected function update_item( $entity_id, $data ) {
		global $wpdb;

		$zoho_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT zoho_books_item_id FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE id = %d',
				$entity_id
			)
		);

		if ( ! $zoho_id ) {
			return $this->create_item( $data );
		}

		$payload = array(
			'name'          => $data['item_name'],
			'description'   => $data['description'] ?? '',
			'rate'          => $data['unit_price'] ?? 0,
			'purchase_rate' => $data['unit_cost'] ?? 0,
			'reorder_level' => $data['reorder_level'] ?? 0,
		);

		$this->put(
			"/items/{$zoho_id}?organization_id={$this->organization_id}",
			$payload
		);

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Create purchase order in Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  array $data PO data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	protected function create_purchase_order( $data ) {
		// Get PO line items
		global $wpdb;
		$line_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_po_line_items WHERE po_id = %d",
				$data['id']
			)
		);

		$items = array();
		foreach ( $line_items as $item ) {
			$items[] = array(
				'item_id'  => $this->get_zoho_item_id( $item->inventory_item_id ),
				'quantity' => $item->quantity,
				'rate'     => $item->unit_price,
			);
		}

		$payload = array(
			'vendor_id'            => $this->get_zoho_vendor_id( $data['supplier_id'] ),
			'purchaseorder_number' => $data['po_number'],
			'date'                 => date( 'Y-m-d', strtotime( $data['po_date'] ) ),
			'delivery_date'        => date( 'Y-m-d', strtotime( $data['delivery_date'] ) ),
			'line_items'           => $items,
			'notes'                => $data['notes'] ?? '',
		);

		$response = $this->post(
			'/purchaseorders?organization_id=' . $this->organization_id,
			$payload
		);

		if ( isset( $response['data']['purchaseorder']['purchaseorder_id'] ) ) {
			$zoho_id = $response['data']['purchaseorder']['purchaseorder_id'];

			$wpdb->update(
				ICT_PURCHASE_ORDERS_TABLE,
				array(
					'zoho_books_po_id' => $zoho_id,
					'sync_status'      => 'synced',
					'last_synced'      => current_time( 'mysql' ),
				),
				array( 'id' => $data['id'] ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return array(
				'zoho_id' => $zoho_id,
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create purchase order in Zoho Books', 'ict-platform' ) );
	}

	/**
	 * Update purchase order in Zoho Books.
	 *
	 * @since  1.0.0
	 * @param  int   $entity_id Local entity ID.
	 * @param  array $data      PO data.
	 * @return array Response data.
	 */
	protected function update_purchase_order( $entity_id, $data ) {
		global $wpdb;

		$zoho_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT zoho_books_po_id FROM ' . ICT_PURCHASE_ORDERS_TABLE . ' WHERE id = %d',
				$entity_id
			)
		);

		if ( ! $zoho_id ) {
			return $this->create_purchase_order( $data );
		}

		// Update PO status or other fields
		$payload = array(
			'notes' => $data['notes'] ?? '',
		);

		$this->put(
			"/purchaseorders/{$zoho_id}?organization_id={$this->organization_id}",
			$payload
		);

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Sync items from Zoho Books.
	 *
	 * @since  1.0.0
	 * @return array Sync results.
	 */
	public function sync_items() {
		$response = $this->get( '/items?organization_id=' . $this->organization_id );

		if ( ! isset( $response['data']['items'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No items found', 'ict-platform' ),
			);
		}

		// Sync items to local inventory
		$synced = 0;
		global $wpdb;

		foreach ( $response['data']['items'] as $item ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE zoho_books_item_id = %s',
					$item['item_id']
				)
			);

			$item_data = array(
				'zoho_books_item_id' => $item['item_id'],
				'sku'                => $item['sku'] ?? '',
				'item_name'          => $item['name'],
				'description'        => $item['description'] ?? '',
				'unit_price'         => $item['rate'] ?? 0,
				'unit_cost'          => $item['purchase_rate'] ?? 0,
				'quantity_on_hand'   => $item['stock_on_hand'] ?? 0,
				'reorder_level'      => $item['reorder_level'] ?? 0,
				'sync_status'        => 'synced',
				'last_synced'        => current_time( 'mysql' ),
			);

			if ( $existing ) {
				$wpdb->update(
					ICT_INVENTORY_ITEMS_TABLE,
					$item_data,
					array( 'id' => $existing->id )
				);
			} else {
				$wpdb->insert( ICT_INVENTORY_ITEMS_TABLE, $item_data );
			}

			++$synced;
		}

		return array(
			'success' => true,
			'synced'  => $synced,
		);
	}

	/**
	 * Get Zoho item ID from local inventory item ID.
	 *
	 * @since  1.0.0
	 * @param  int $inventory_item_id Local inventory item ID.
	 * @return string|null Zoho item ID.
	 */
	protected function get_zoho_item_id( $inventory_item_id ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT zoho_books_item_id FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE id = %d',
				$inventory_item_id
			)
		);
	}

	/**
	 * Get Zoho vendor ID from local supplier ID.
	 *
	 * @since  1.0.0
	 * @param  int $supplier_id Local supplier ID.
	 * @return string|null Zoho vendor ID.
	 */
	protected function get_zoho_vendor_id( $supplier_id ) {
		// Implementation: Get vendor ID from suppliers table
		return get_term_meta( $supplier_id, 'zoho_books_vendor_id', true );
	}
}
