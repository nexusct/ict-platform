<?php
/**
 * Inventory Alerts & Barcode Scanner
 *
 * Low stock alerts, barcode scanning, and stock management.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Inventory_Alerts {

	private static $instance = null;
	private $alerts_table;
	private $barcodes_table;
	private $movements_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->alerts_table    = $wpdb->prefix . 'ict_inventory_alerts';
		$this->barcodes_table  = $wpdb->prefix . 'ict_item_barcodes';
		$this->movements_table = $wpdb->prefix . 'ict_stock_movements';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'ict_inventory_updated', array( $this, 'check_stock_levels' ), 10, 2 );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/inventory/alerts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_alerts' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/alerts/(?P<id>\d+)/acknowledge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'acknowledge_alert' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/low-stock',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_low_stock' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scan_barcode' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/items/(?P<item_id>\d+)/barcode',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_barcode' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_barcode' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/items/(?P<item_id>\d+)/adjust',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'adjust_stock' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/items/(?P<item_id>\d+)/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/inventory/reorder-suggestions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reorder_suggestions' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'edit_ict_inventory' ) || current_user_can( 'manage_options' );
	}

	public function check_edit_permission() {
		return current_user_can( 'manage_ict_inventory' ) || current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->alerts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            alert_type enum('low_stock','out_of_stock','overstock','expiring') NOT NULL,
            severity enum('info','warning','critical') DEFAULT 'warning',
            message text NOT NULL,
            current_qty decimal(15,2),
            threshold_qty decimal(15,2),
            is_acknowledged tinyint(1) DEFAULT 0,
            acknowledged_by bigint(20) unsigned,
            acknowledged_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_id (item_id),
            KEY alert_type (alert_type),
            KEY is_acknowledged (is_acknowledged)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->barcodes_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            barcode varchar(100) NOT NULL,
            barcode_type enum('EAN13','UPC','CODE128','QR','INTERNAL') DEFAULT 'CODE128',
            is_primary tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY barcode (barcode),
            KEY item_id (item_id)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->movements_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            movement_type enum('in','out','adjustment','transfer') NOT NULL,
            quantity decimal(15,2) NOT NULL,
            quantity_before decimal(15,2),
            quantity_after decimal(15,2),
            reference_type varchar(50),
            reference_id bigint(20) unsigned,
            reason text,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_id (item_id),
            KEY movement_type (movement_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	public function get_alerts( $request ) {
		global $wpdb;
		$status = $request->get_param( 'status' ) ?: 'active';

		$where = $status === 'active' ? 'WHERE a.is_acknowledged = 0' : '';

		$alerts = $wpdb->get_results(
			"SELECT a.*, i.name as item_name, i.sku
             FROM {$this->alerts_table} a
             LEFT JOIN {$wpdb->prefix}ict_inventory_items i ON a.item_id = i.id
             {$where}
             ORDER BY FIELD(a.severity, 'critical', 'warning', 'info'), a.created_at DESC"
		);

		return rest_ensure_response( $alerts );
	}

	public function acknowledge_alert( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->update(
			$this->alerts_table,
			array(
				'is_acknowledged' => 1,
				'acknowledged_by' => get_current_user_id(),
				'acknowledged_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_low_stock( $request ) {
		global $wpdb;

		$items = $wpdb->get_results(
			"SELECT *, (reorder_point - quantity) as shortage
             FROM {$wpdb->prefix}ict_inventory_items
             WHERE quantity <= reorder_point AND reorder_point > 0
             ORDER BY CASE WHEN quantity = 0 THEN 0 ELSE 1 END, (quantity / reorder_point)"
		);

		return rest_ensure_response( $items );
	}

	public function scan_barcode( $request ) {
		global $wpdb;
		$barcode = sanitize_text_field( $request->get_param( 'barcode' ) );
		$action  = $request->get_param( 'action' ) ?: 'lookup';

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->barcodes_table} WHERE barcode = %s",
				$barcode
			)
		);

		if ( ! $record ) {
			$item = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ict_inventory_items WHERE sku = %s",
					$barcode
				)
			);
			if ( ! $item ) {
				return rest_ensure_response(
					array(
						'found'   => false,
						'barcode' => $barcode,
					)
				);
			}
		} else {
			$item = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ict_inventory_items WHERE id = %d",
					$record->item_id
				)
			);
		}

		$result = array(
			'found' => true,
			'item'  => $item,
		);

		if ( $action === 'checkout' ) {
			$qty = (float) $request->get_param( 'quantity' ) ?: 1;
			$this->record_movement( $item->id, 'out', -$qty, 'Barcode checkout' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}ict_inventory_items SET quantity = quantity - %f WHERE id = %d",
					$qty,
					$item->id
				)
			);
			$result['action']   = 'checkout';
			$result['quantity'] = $qty;
		} elseif ( $action === 'checkin' ) {
			$qty = (float) $request->get_param( 'quantity' ) ?: 1;
			$this->record_movement( $item->id, 'in', $qty, 'Barcode checkin' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}ict_inventory_items SET quantity = quantity + %f WHERE id = %d",
					$qty,
					$item->id
				)
			);
			$result['action']   = 'checkin';
			$result['quantity'] = $qty;
		}

		return rest_ensure_response( $result );
	}

	public function get_barcode( $request ) {
		global $wpdb;
		$item_id = (int) $request->get_param( 'item_id' );

		$barcodes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->barcodes_table} WHERE item_id = %d",
				$item_id
			)
		);

		return rest_ensure_response( $barcodes );
	}

	public function set_barcode( $request ) {
		global $wpdb;
		$item_id = (int) $request->get_param( 'item_id' );
		$barcode = sanitize_text_field( $request->get_param( 'barcode' ) );
		$type    = sanitize_text_field( $request->get_param( 'barcode_type' ) ?: 'CODE128' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->barcodes_table} WHERE barcode = %s AND item_id != %d",
				$barcode,
				$item_id
			)
		);

		if ( $existing ) {
			return new WP_Error( 'duplicate', 'Barcode already exists', array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$this->barcodes_table,
			array(
				'item_id'      => $item_id,
				'barcode'      => $barcode,
				'barcode_type' => $type,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function adjust_stock( $request ) {
		global $wpdb;
		$item_id    = (int) $request->get_param( 'item_id' );
		$adjustment = (float) $request->get_param( 'adjustment' );
		$reason     = sanitize_text_field( $request->get_param( 'reason' ) );

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_inventory_items WHERE id = %d",
				$item_id
			)
		);

		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Item not found', array( 'status' => 404 ) );
		}

		$new_qty = $item->quantity + $adjustment;
		if ( $new_qty < 0 ) {
			return new WP_Error( 'insufficient', 'Insufficient stock', array( 'status' => 400 ) );
		}

		$this->record_movement( $item_id, 'adjustment', $adjustment, $reason );
		$wpdb->update( $wpdb->prefix . 'ict_inventory_items', array( 'quantity' => $new_qty ), array( 'id' => $item_id ) );
		$this->check_stock_levels( $item_id );

		return rest_ensure_response(
			array(
				'success'      => true,
				'old_quantity' => $item->quantity,
				'new_quantity' => $new_qty,
			)
		);
	}

	public function get_history( $request ) {
		global $wpdb;
		$item_id = (int) $request->get_param( 'item_id' );
		$limit   = (int) $request->get_param( 'limit' ) ?: 50;

		$movements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name as user_name
             FROM {$this->movements_table} m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE m.item_id = %d ORDER BY m.created_at DESC LIMIT %d",
				$item_id,
				$limit
			)
		);

		return rest_ensure_response( $movements );
	}

	public function get_reorder_suggestions( $request ) {
		global $wpdb;

		$items = $wpdb->get_results(
			"SELECT i.*, s.name as supplier_name
             FROM {$wpdb->prefix}ict_inventory_items i
             LEFT JOIN {$wpdb->prefix}ict_suppliers s ON i.supplier_id = s.id
             WHERE i.quantity <= i.reorder_point AND i.reorder_point > 0
             ORDER BY i.quantity / i.reorder_point"
		);

		$total = 0;
		foreach ( $items as $item ) {
			$item->suggested_qty  = $item->reorder_quantity ?: ( $item->reorder_point * 2 );
			$item->estimated_cost = $item->suggested_qty * $item->unit_cost;
			$total               += $item->estimated_cost;
		}

		return rest_ensure_response(
			array(
				'items'                => $items,
				'total_estimated_cost' => $total,
			)
		);
	}

	public function check_stock_levels( $item_id, $data = null ) {
		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_inventory_items WHERE id = %d",
				$item_id
			)
		);

		if ( ! $item ) {
			return;
		}

		$wpdb->delete(
			$this->alerts_table,
			array(
				'item_id'         => $item_id,
				'is_acknowledged' => 0,
			)
		);

		if ( $item->quantity <= 0 ) {
			$this->create_alert(
				$item_id,
				'out_of_stock',
				'critical',
				"{$item->name} is out of stock",
				$item->quantity,
				0
			);
		} elseif ( $item->reorder_point > 0 && $item->quantity <= $item->reorder_point ) {
			$severity = $item->quantity <= ( $item->reorder_point * 0.5 ) ? 'critical' : 'warning';
			$this->create_alert(
				$item_id,
				'low_stock',
				$severity,
				"{$item->name} is low ({$item->quantity} remaining)",
				$item->quantity,
				$item->reorder_point
			);
		}
	}

	private function create_alert( $item_id, $type, $severity, $message, $current, $threshold ) {
		global $wpdb;
		$wpdb->insert(
			$this->alerts_table,
			array(
				'item_id'       => $item_id,
				'alert_type'    => $type,
				'severity'      => $severity,
				'message'       => $message,
				'current_qty'   => $current,
				'threshold_qty' => $threshold,
			)
		);
		do_action( 'ict_inventory_alert', $item_id, $type, $severity, $message );
	}

	private function record_movement( $item_id, $type, $qty, $reason ) {
		global $wpdb;
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT quantity FROM {$wpdb->prefix}ict_inventory_items WHERE id = %d",
				$item_id
			)
		);

		$wpdb->insert(
			$this->movements_table,
			array(
				'item_id'         => $item_id,
				'movement_type'   => $type,
				'quantity'        => $qty,
				'quantity_before' => $item ? $item->quantity : 0,
				'quantity_after'  => $item ? $item->quantity + $qty : $qty,
				'reason'          => $reason,
				'user_id'         => get_current_user_id(),
			)
		);
	}
}
