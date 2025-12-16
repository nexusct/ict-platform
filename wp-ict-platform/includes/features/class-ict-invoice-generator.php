<?php
/**
 * Invoice Generation
 *
 * Comprehensive invoicing system including:
 * - Invoice creation from projects/quotes/time entries
 * - Line item management with taxes
 * - Payment tracking and reminders
 * - PDF generation and email delivery
 * - Recurring invoices
 * - Credit notes and refunds
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Invoice_Generator
 */
class ICT_Invoice_Generator {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Invoice_Generator
	 */
	private static $instance = null;

	/**
	 * Table names.
	 *
	 * @var array
	 */
	private $tables = array();

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Invoice_Generator
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;

		$this->tables = array(
			'invoices'   => $wpdb->prefix . 'ict_invoices',
			'line_items' => $wpdb->prefix . 'ict_invoice_line_items',
			'payments'   => $wpdb->prefix . 'ict_invoice_payments',
			'recurring'  => $wpdb->prefix . 'ict_recurring_invoices',
		);

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		register_activation_hook( ICT_PLUGIN_FILE, array( $this, 'maybe_create_tables' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_create_tables' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'ict_process_recurring_invoices', array( $this, 'process_recurring' ) );
		add_action( 'ict_send_payment_reminders', array( $this, 'send_reminders' ) );

		if ( ! wp_next_scheduled( 'ict_process_recurring_invoices' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_process_recurring_invoices' );
		}
		if ( ! wp_next_scheduled( 'ict_send_payment_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_send_payment_reminders' );
		}
	}

	/**
	 * Create database tables.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Invoices table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['invoices']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            invoice_type enum('invoice','credit_note','proforma') DEFAULT 'invoice',
            client_id bigint(20) unsigned DEFAULT NULL,
            client_name varchar(200) NOT NULL,
            client_email varchar(200) DEFAULT NULL,
            client_phone varchar(50) DEFAULT NULL,
            client_address text,
            client_tax_id varchar(100) DEFAULT NULL,
            project_id bigint(20) unsigned DEFAULT NULL,
            quote_id bigint(20) unsigned DEFAULT NULL,
            status enum('draft','sent','viewed','paid','partial','overdue','cancelled','refunded') DEFAULT 'draft',
            currency varchar(3) DEFAULT 'USD',
            subtotal decimal(12,2) DEFAULT 0,
            discount_type enum('percentage','fixed') DEFAULT 'percentage',
            discount_value decimal(12,2) DEFAULT 0,
            discount_amount decimal(12,2) DEFAULT 0,
            tax_rate decimal(5,2) DEFAULT 0,
            tax_amount decimal(12,2) DEFAULT 0,
            total decimal(12,2) DEFAULT 0,
            amount_paid decimal(12,2) DEFAULT 0,
            amount_due decimal(12,2) DEFAULT 0,
            issue_date date NOT NULL,
            due_date date NOT NULL,
            payment_terms varchar(100) DEFAULT 'Net 30',
            notes text,
            internal_notes text,
            terms_conditions longtext,
            sent_at datetime DEFAULT NULL,
            viewed_at datetime DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            reminder_sent_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY client_id (client_id),
            KEY project_id (project_id),
            KEY status (status),
            KEY due_date (due_date)
        ) $charset_collate;";

		// Line items.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['line_items']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            item_type enum('service','product','time','expense','other') DEFAULT 'service',
            description text NOT NULL,
            quantity decimal(12,4) DEFAULT 1,
            unit varchar(50) DEFAULT 'each',
            unit_price decimal(12,4) DEFAULT 0,
            total decimal(12,2) DEFAULT 0,
            is_taxable tinyint(1) DEFAULT 1,
            time_entry_ids longtext,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id)
        ) $charset_collate;";

		// Payments.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['payments']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            payment_date date NOT NULL,
            amount decimal(12,2) NOT NULL,
            payment_method enum('cash','check','bank_transfer','credit_card','paypal','stripe','other') DEFAULT 'bank_transfer',
            reference_number varchar(100) DEFAULT NULL,
            notes text,
            recorded_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY payment_date (payment_date)
        ) $charset_collate;";

		// Recurring invoices.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['recurring']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            client_id bigint(20) unsigned NOT NULL,
            client_name varchar(200) NOT NULL,
            client_email varchar(200) DEFAULT NULL,
            frequency enum('weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
            day_of_month int(11) DEFAULT 1,
            line_items longtext,
            subtotal decimal(12,2) DEFAULT 0,
            tax_rate decimal(5,2) DEFAULT 0,
            total decimal(12,2) DEFAULT 0,
            payment_terms varchar(100) DEFAULT 'Net 30',
            notes text,
            auto_send tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            next_invoice_date date DEFAULT NULL,
            last_invoice_date date DEFAULT NULL,
            last_invoice_id bigint(20) unsigned DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY next_invoice_date (next_invoice_date)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$namespace = 'ict/v1';

		// Invoices.
		register_rest_route(
			$namespace,
			'/invoices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_invoices' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_invoice' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/invoices/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_invoice' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_invoice' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_invoice' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Generate from project/quote.
		register_rest_route(
			$namespace,
			'/invoices/from-project/(?P<project_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_from_project' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/invoices/from-quote/(?P<quote_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_from_quote' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/invoices/from-time-entries',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_from_time' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Actions.
		register_rest_route(
			$namespace,
			'/invoices/(?P<id>\d+)/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_send_invoice' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/invoices/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_duplicate_invoice' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/invoices/(?P<id>\d+)/credit-note',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_credit_note' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Payments.
		register_rest_route(
			$namespace,
			'/invoices/(?P<invoice_id>\d+)/payments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_payments' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_record_payment' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Recurring.
		register_rest_route(
			$namespace,
			'/recurring-invoices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_recurring' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_recurring' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Stats.
		register_rest_route(
			$namespace,
			'/invoices/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_stats' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);
	}

	/**
	 * Check read permission.
	 */
	public function check_read_permission() {
		return current_user_can( 'read' );
	}

	/**
	 * Check manage permission.
	 */
	public function check_manage_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Generate invoice number.
	 */
	private function generate_invoice_number( $type = 'invoice' ) {
		global $wpdb;

		$prefix = $type === 'credit_note' ? 'CN' : 'INV';
		$prefix = get_option( 'ict_invoice_prefix', $prefix );
		$year   = date( 'Y' );

		$last_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(SUBSTRING(invoice_number, %d) AS UNSIGNED))
            FROM {$this->tables['invoices']}
            WHERE invoice_number LIKE %s",
				strlen( $prefix ) + 5,
				$prefix . $year . '%'
			)
		);

		$next_number = ( $last_number ?: 0 ) + 1;

		return $prefix . $year . str_pad( $next_number, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Get invoices.
	 */
	public function rest_get_invoices( $request ) {
		global $wpdb;

		$status    = $request->get_param( 'status' );
		$client_id = $request->get_param( 'client_id' );
		$page      = max( 1, intval( $request->get_param( 'page' ) ) );
		$per_page  = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$sql  = "SELECT * FROM {$this->tables['invoices']} WHERE 1=1";
		$args = array();

		if ( $status ) {
			if ( $status === 'outstanding' ) {
				$sql .= " AND status IN ('sent', 'viewed', 'partial', 'overdue')";
			} else {
				$sql   .= ' AND status = %s';
				$args[] = $status;
			}
		}

		if ( $client_id ) {
			$sql   .= ' AND client_id = %d';
			$args[] = $client_id;
		}

		$count_sql = str_replace( 'SELECT *', 'SELECT COUNT(*)', $sql );
		$total     = ! empty( $args ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql );

		$sql   .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$args[] = $per_page;
		$args[] = ( $page - 1 ) * $per_page;

		$invoices = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		return rest_ensure_response(
			array(
				'success'  => true,
				'invoices' => $invoices,
				'total'    => intval( $total ),
				'pages'    => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Get single invoice.
	 */
	public function rest_get_invoice( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$id
			)
		);

		if ( ! $invoice ) {
			return new WP_Error( 'not_found', 'Invoice not found', array( 'status' => 404 ) );
		}

		$invoice->line_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE invoice_id = %d ORDER BY sort_order, id",
				$id
			)
		);

		$invoice->payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['payments']} WHERE invoice_id = %d ORDER BY payment_date DESC",
				$id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'invoice' => $invoice,
			)
		);
	}

	/**
	 * Create invoice.
	 */
	public function rest_create_invoice( $request ) {
		global $wpdb;

		$invoice_type   = sanitize_text_field( $request->get_param( 'invoice_type' ) ) ?: 'invoice';
		$invoice_number = $this->generate_invoice_number( $invoice_type );

		$issue_date    = sanitize_text_field( $request->get_param( 'issue_date' ) ) ?: current_time( 'Y-m-d' );
		$payment_terms = sanitize_text_field( $request->get_param( 'payment_terms' ) ) ?: 'Net 30';
		$days          = intval( preg_replace( '/[^0-9]/', '', $payment_terms ) ) ?: 30;
		$due_date      = sanitize_text_field( $request->get_param( 'due_date' ) ) ?: date( 'Y-m-d', strtotime( $issue_date . " + $days days" ) );

		$data = array(
			'invoice_number'   => $invoice_number,
			'invoice_type'     => $invoice_type,
			'client_id'        => intval( $request->get_param( 'client_id' ) ) ?: null,
			'client_name'      => sanitize_text_field( $request->get_param( 'client_name' ) ),
			'client_email'     => sanitize_email( $request->get_param( 'client_email' ) ),
			'client_phone'     => sanitize_text_field( $request->get_param( 'client_phone' ) ),
			'client_address'   => sanitize_textarea_field( $request->get_param( 'client_address' ) ),
			'client_tax_id'    => sanitize_text_field( $request->get_param( 'client_tax_id' ) ),
			'project_id'       => intval( $request->get_param( 'project_id' ) ) ?: null,
			'currency'         => sanitize_text_field( $request->get_param( 'currency' ) ) ?: 'USD',
			'tax_rate'         => floatval( $request->get_param( 'tax_rate' ) ),
			'discount_type'    => sanitize_text_field( $request->get_param( 'discount_type' ) ) ?: 'percentage',
			'discount_value'   => floatval( $request->get_param( 'discount_value' ) ),
			'issue_date'       => $issue_date,
			'due_date'         => $due_date,
			'payment_terms'    => $payment_terms,
			'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'terms_conditions' => wp_kses_post( $request->get_param( 'terms_conditions' ) ),
			'created_by'       => get_current_user_id(),
		);

		$wpdb->insert( $this->tables['invoices'], $data );
		$invoice_id = $wpdb->insert_id;

		// Add line items.
		$items = $request->get_param( 'line_items' );
		if ( is_array( $items ) ) {
			foreach ( $items as $index => $item ) {
				$quantity   = floatval( $item['quantity'] ?? 1 );
				$unit_price = floatval( $item['unit_price'] ?? 0 );

				$wpdb->insert(
					$this->tables['line_items'],
					array(
						'invoice_id'  => $invoice_id,
						'item_type'   => sanitize_text_field( $item['item_type'] ?? 'service' ),
						'description' => sanitize_textarea_field( $item['description'] ?? '' ),
						'quantity'    => $quantity,
						'unit'        => sanitize_text_field( $item['unit'] ?? 'each' ),
						'unit_price'  => $unit_price,
						'total'       => $quantity * $unit_price,
						'is_taxable'  => isset( $item['is_taxable'] ) ? ( $item['is_taxable'] ? 1 : 0 ) : 1,
						'sort_order'  => $index,
					)
				);
			}
			$this->recalculate_invoice( $invoice_id );
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'invoice_id'     => $invoice_id,
				'invoice_number' => $invoice_number,
				'message'        => 'Invoice created successfully',
			)
		);
	}

	/**
	 * Update invoice.
	 */
	public function rest_update_invoice( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$fields = array(
			'client_name',
			'client_email',
			'client_phone',
			'client_address',
			'client_tax_id',
			'tax_rate',
			'discount_type',
			'discount_value',
			'issue_date',
			'due_date',
			'payment_terms',
			'notes',
			'terms_conditions',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->tables['invoices'], $data, array( 'id' => $id ) );
			$this->recalculate_invoice( $id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Invoice updated successfully',
			)
		);
	}

	/**
	 * Delete invoice.
	 */
	public function rest_delete_invoice( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->tables['invoices'], array( 'id' => $id ) );
		$wpdb->delete( $this->tables['line_items'], array( 'invoice_id' => $id ) );
		$wpdb->delete( $this->tables['payments'], array( 'invoice_id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Invoice deleted successfully',
			)
		);
	}

	/**
	 * Recalculate invoice totals.
	 */
	private function recalculate_invoice( $invoice_id ) {
		global $wpdb;

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$invoice_id
			)
		);

		if ( ! $invoice ) {
			return;
		}

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE invoice_id = %d",
				$invoice_id
			)
		);

		$subtotal         = 0;
		$taxable_subtotal = 0;

		foreach ( $items as $item ) {
			$subtotal += floatval( $item->total );
			if ( $item->is_taxable ) {
				$taxable_subtotal += floatval( $item->total );
			}
		}

		$discount_amount = 0;
		if ( $invoice->discount_value > 0 ) {
			if ( $invoice->discount_type === 'percentage' ) {
				$discount_amount = $subtotal * ( $invoice->discount_value / 100 );
			} else {
				$discount_amount = $invoice->discount_value;
			}
		}

		$taxable_after_discount = $subtotal > 0 ? $taxable_subtotal - ( $taxable_subtotal / $subtotal * $discount_amount ) : 0;
		$tax_amount             = $taxable_after_discount * ( $invoice->tax_rate / 100 );
		$total                  = $subtotal - $discount_amount + $tax_amount;
		$amount_due             = $total - $invoice->amount_paid;

		$wpdb->update(
			$this->tables['invoices'],
			array(
				'subtotal'        => round( $subtotal, 2 ),
				'discount_amount' => round( $discount_amount, 2 ),
				'tax_amount'      => round( $tax_amount, 2 ),
				'total'           => round( $total, 2 ),
				'amount_due'      => round( $amount_due, 2 ),
			),
			array( 'id' => $invoice_id )
		);

		$this->update_invoice_status( $invoice_id );
	}

	/**
	 * Update invoice status based on payments.
	 */
	private function update_invoice_status( $invoice_id ) {
		global $wpdb;

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$invoice_id
			)
		);

		if ( ! $invoice || in_array( $invoice->status, array( 'draft', 'cancelled' ) ) ) {
			return;
		}

		$new_status = $invoice->status;

		if ( $invoice->amount_paid >= $invoice->total ) {
			$new_status = 'paid';
		} elseif ( $invoice->amount_paid > 0 ) {
			$new_status = 'partial';
		} elseif ( strtotime( $invoice->due_date ) < time() ) {
			$new_status = 'overdue';
		}

		if ( $new_status !== $invoice->status ) {
			$update_data = array( 'status' => $new_status );
			if ( $new_status === 'paid' && ! $invoice->paid_at ) {
				$update_data['paid_at'] = current_time( 'mysql' );
			}
			$wpdb->update( $this->tables['invoices'], $update_data, array( 'id' => $invoice_id ) );
		}
	}

	/**
	 * Create invoice from time entries.
	 */
	public function rest_create_from_time( $request ) {
		global $wpdb;

		$time_entry_ids = $request->get_param( 'time_entry_ids' );
		$client_id      = intval( $request->get_param( 'client_id' ) );
		$hourly_rate    = floatval( $request->get_param( 'hourly_rate' ) );

		if ( empty( $time_entry_ids ) ) {
			return new WP_Error( 'no_entries', 'No time entries specified', array( 'status' => 400 ) );
		}

		// Get time entries.
		$placeholders = implode( ',', array_fill( 0, count( $time_entry_ids ), '%d' ) );
		$entries      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_time_entries WHERE id IN ($placeholders)",
				$time_entry_ids
			)
		);

		if ( empty( $entries ) ) {
			return new WP_Error( 'no_entries', 'No valid time entries found', array( 'status' => 404 ) );
		}

		// Group by project/description.
		$grouped = array();
		foreach ( $entries as $entry ) {
			$key = $entry->project_id . '_' . $entry->description;
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'project_id'  => $entry->project_id,
					'description' => $entry->description,
					'hours'       => 0,
					'entry_ids'   => array(),
				);
			}
			$hours                          = ( strtotime( $entry->end_time ) - strtotime( $entry->start_time ) ) / 3600;
			$grouped[ $key ]['hours']      += $hours;
			$grouped[ $key ]['entry_ids'][] = $entry->id;
		}

		// Create invoice.
		$invoice_number = $this->generate_invoice_number();

		$wpdb->insert(
			$this->tables['invoices'],
			array(
				'invoice_number' => $invoice_number,
				'client_id'      => $client_id,
				'client_name'    => sanitize_text_field( $request->get_param( 'client_name' ) ),
				'client_email'   => sanitize_email( $request->get_param( 'client_email' ) ),
				'issue_date'     => current_time( 'Y-m-d' ),
				'due_date'       => date( 'Y-m-d', strtotime( '+30 days' ) ),
				'created_by'     => get_current_user_id(),
			)
		);
		$invoice_id = $wpdb->insert_id;

		// Add line items.
		foreach ( $grouped as $item ) {
			$total = $item['hours'] * $hourly_rate;
			$wpdb->insert(
				$this->tables['line_items'],
				array(
					'invoice_id'     => $invoice_id,
					'item_type'      => 'time',
					'description'    => $item['description'] ?: 'Professional services',
					'quantity'       => round( $item['hours'], 2 ),
					'unit'           => 'hours',
					'unit_price'     => $hourly_rate,
					'total'          => round( $total, 2 ),
					'time_entry_ids' => wp_json_encode( $item['entry_ids'] ),
				)
			);
		}

		$this->recalculate_invoice( $invoice_id );

		return rest_ensure_response(
			array(
				'success'        => true,
				'invoice_id'     => $invoice_id,
				'invoice_number' => $invoice_number,
				'message'        => 'Invoice created from time entries',
			)
		);
	}

	/**
	 * Send invoice.
	 */
	public function rest_send_invoice( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$id
			)
		);

		if ( ! $invoice ) {
			return new WP_Error( 'not_found', 'Invoice not found', array( 'status' => 404 ) );
		}

		if ( ! $invoice->client_email ) {
			return new WP_Error( 'no_email', 'Client email is required', array( 'status' => 400 ) );
		}

		$subject = sprintf( 'Invoice %s - %s', $invoice->invoice_number, get_bloginfo( 'name' ) );
		$message = sprintf(
			"Dear %s,\n\nPlease find attached invoice %s for %s.\n\nAmount Due: %s %s\nDue Date: %s\n\nThank you for your business.",
			$invoice->client_name,
			$invoice->invoice_number,
			$invoice->currency . ' ' . number_format( $invoice->total, 2 ),
			$invoice->currency,
			number_format( $invoice->amount_due, 2 ),
			date( 'F j, Y', strtotime( $invoice->due_date ) )
		);

		wp_mail( $invoice->client_email, $subject, $message );

		$wpdb->update(
			$this->tables['invoices'],
			array(
				'status'  => $invoice->status === 'draft' ? 'sent' : $invoice->status,
				'sent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Invoice sent successfully',
			)
		);
	}

	/**
	 * Record payment.
	 */
	public function rest_record_payment( $request ) {
		global $wpdb;

		$invoice_id = intval( $request->get_param( 'invoice_id' ) );
		$amount     = floatval( $request->get_param( 'amount' ) );

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$invoice_id
			)
		);

		if ( ! $invoice ) {
			return new WP_Error( 'not_found', 'Invoice not found', array( 'status' => 404 ) );
		}

		$wpdb->insert(
			$this->tables['payments'],
			array(
				'invoice_id'       => $invoice_id,
				'payment_date'     => sanitize_text_field( $request->get_param( 'payment_date' ) ) ?: current_time( 'Y-m-d' ),
				'amount'           => $amount,
				'payment_method'   => sanitize_text_field( $request->get_param( 'payment_method' ) ) ?: 'bank_transfer',
				'reference_number' => sanitize_text_field( $request->get_param( 'reference_number' ) ),
				'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ),
				'recorded_by'      => get_current_user_id(),
			)
		);

		// Update invoice.
		$new_amount_paid = $invoice->amount_paid + $amount;
		$new_amount_due  = $invoice->total - $new_amount_paid;

		$wpdb->update(
			$this->tables['invoices'],
			array(
				'amount_paid' => $new_amount_paid,
				'amount_due'  => max( 0, $new_amount_due ),
			),
			array( 'id' => $invoice_id )
		);

		$this->update_invoice_status( $invoice_id );

		return rest_ensure_response(
			array(
				'success'    => true,
				'payment_id' => $wpdb->insert_id,
				'message'    => 'Payment recorded successfully',
			)
		);
	}

	/**
	 * Get payments.
	 */
	public function rest_get_payments( $request ) {
		global $wpdb;

		$invoice_id = intval( $request->get_param( 'invoice_id' ) );

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, u.display_name as recorded_by_name
            FROM {$this->tables['payments']} p
            LEFT JOIN {$wpdb->users} u ON p.recorded_by = u.ID
            WHERE p.invoice_id = %d
            ORDER BY p.payment_date DESC",
				$invoice_id
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'payments' => $payments,
			)
		);
	}

	/**
	 * Get recurring invoices.
	 */
	public function rest_get_recurring( $request ) {
		global $wpdb;

		$recurring = $wpdb->get_results(
			"SELECT * FROM {$this->tables['recurring']} ORDER BY next_invoice_date ASC"
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'recurring' => $recurring,
			)
		);
	}

	/**
	 * Create recurring invoice.
	 */
	public function rest_create_recurring( $request ) {
		global $wpdb;

		$frequency    = sanitize_text_field( $request->get_param( 'frequency' ) ) ?: 'monthly';
		$day_of_month = intval( $request->get_param( 'day_of_month' ) ) ?: 1;

		// Calculate next invoice date.
		$next_date = date( 'Y-m-' . str_pad( $day_of_month, 2, '0', STR_PAD_LEFT ) );
		if ( strtotime( $next_date ) <= time() ) {
			$next_date = date( 'Y-m-d', strtotime( $next_date . ' + 1 month' ) );
		}

		$wpdb->insert(
			$this->tables['recurring'],
			array(
				'name'              => sanitize_text_field( $request->get_param( 'name' ) ),
				'client_id'         => intval( $request->get_param( 'client_id' ) ),
				'client_name'       => sanitize_text_field( $request->get_param( 'client_name' ) ),
				'client_email'      => sanitize_email( $request->get_param( 'client_email' ) ),
				'frequency'         => $frequency,
				'day_of_month'      => $day_of_month,
				'line_items'        => wp_json_encode( $request->get_param( 'line_items' ) ),
				'subtotal'          => floatval( $request->get_param( 'subtotal' ) ),
				'tax_rate'          => floatval( $request->get_param( 'tax_rate' ) ),
				'total'             => floatval( $request->get_param( 'total' ) ),
				'payment_terms'     => sanitize_text_field( $request->get_param( 'payment_terms' ) ) ?: 'Net 30',
				'notes'             => sanitize_textarea_field( $request->get_param( 'notes' ) ),
				'auto_send'         => $request->get_param( 'auto_send' ) ? 1 : 0,
				'next_invoice_date' => $next_date,
				'created_by'        => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'success'      => true,
				'recurring_id' => $wpdb->insert_id,
				'message'      => 'Recurring invoice created successfully',
			)
		);
	}

	/**
	 * Process recurring invoices.
	 */
	public function process_recurring() {
		global $wpdb;

		$due = $wpdb->get_results(
			"SELECT * FROM {$this->tables['recurring']}
            WHERE is_active = 1 AND next_invoice_date <= CURDATE()"
		);

		foreach ( $due as $recurring ) {
			// Create invoice.
			$invoice_number = $this->generate_invoice_number();
			$payment_terms  = $recurring->payment_terms ?: 'Net 30';
			$days           = intval( preg_replace( '/[^0-9]/', '', $payment_terms ) ) ?: 30;

			$wpdb->insert(
				$this->tables['invoices'],
				array(
					'invoice_number' => $invoice_number,
					'client_id'      => $recurring->client_id,
					'client_name'    => $recurring->client_name,
					'client_email'   => $recurring->client_email,
					'subtotal'       => $recurring->subtotal,
					'tax_rate'       => $recurring->tax_rate,
					'total'          => $recurring->total,
					'amount_due'     => $recurring->total,
					'issue_date'     => current_time( 'Y-m-d' ),
					'due_date'       => date( 'Y-m-d', strtotime( "+$days days" ) ),
					'payment_terms'  => $payment_terms,
					'notes'          => $recurring->notes,
					'status'         => $recurring->auto_send ? 'sent' : 'draft',
					'sent_at'        => $recurring->auto_send ? current_time( 'mysql' ) : null,
				)
			);
			$invoice_id = $wpdb->insert_id;

			// Add line items.
			$items = json_decode( $recurring->line_items, true );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					$wpdb->insert(
						$this->tables['line_items'],
						array(
							'invoice_id'  => $invoice_id,
							'item_type'   => $item['item_type'] ?? 'service',
							'description' => $item['description'] ?? '',
							'quantity'    => $item['quantity'] ?? 1,
							'unit'        => $item['unit'] ?? 'each',
							'unit_price'  => $item['unit_price'] ?? 0,
							'total'       => $item['total'] ?? 0,
							'is_taxable'  => $item['is_taxable'] ?? 1,
						)
					);
				}
			}

			// Calculate next date.
			$next_date = $this->calculate_next_recurring_date( $recurring->frequency, $recurring->day_of_month );

			$wpdb->update(
				$this->tables['recurring'],
				array(
					'last_invoice_date' => current_time( 'Y-m-d' ),
					'last_invoice_id'   => $invoice_id,
					'next_invoice_date' => $next_date,
				),
				array( 'id' => $recurring->id )
			);

			// Auto-send if enabled.
			if ( $recurring->auto_send && $recurring->client_email ) {
				$this->rest_send_invoice( new WP_REST_Request( 'POST', '', array( 'id' => $invoice_id ) ) );
			}
		}
	}

	/**
	 * Calculate next recurring date.
	 */
	private function calculate_next_recurring_date( $frequency, $day_of_month ) {
		$base = date( 'Y-m-' . str_pad( $day_of_month, 2, '0', STR_PAD_LEFT ) );

		switch ( $frequency ) {
			case 'weekly':
				return date( 'Y-m-d', strtotime( '+1 week' ) );
			case 'monthly':
				return date( 'Y-m-d', strtotime( $base . ' +1 month' ) );
			case 'quarterly':
				return date( 'Y-m-d', strtotime( $base . ' +3 months' ) );
			case 'yearly':
				return date( 'Y-m-d', strtotime( $base . ' +1 year' ) );
			default:
				return date( 'Y-m-d', strtotime( $base . ' +1 month' ) );
		}
	}

	/**
	 * Send payment reminders.
	 */
	public function send_reminders() {
		global $wpdb;

		// Update overdue invoices.
		$wpdb->query(
			"UPDATE {$this->tables['invoices']}
            SET status = 'overdue'
            WHERE status IN ('sent', 'viewed', 'partial')
            AND due_date < CURDATE()"
		);

		// Get invoices for reminders (7 days overdue, weekly thereafter).
		$overdue = $wpdb->get_results(
			"SELECT * FROM {$this->tables['invoices']}
            WHERE status = 'overdue'
            AND (reminder_sent_at IS NULL OR reminder_sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
            AND client_email IS NOT NULL"
		);

		foreach ( $overdue as $invoice ) {
			$days_overdue = floor( ( time() - strtotime( $invoice->due_date ) ) / 86400 );

			$subject = sprintf( 'Payment Reminder - Invoice %s', $invoice->invoice_number );
			$message = sprintf(
				"Dear %s,\n\nThis is a reminder that invoice %s is %d days overdue.\n\nAmount Due: %s %s\n\nPlease arrange payment at your earliest convenience.\n\nThank you.",
				$invoice->client_name,
				$invoice->invoice_number,
				$days_overdue,
				$invoice->currency,
				number_format( $invoice->amount_due, 2 )
			);

			wp_mail( $invoice->client_email, $subject, $message );

			$wpdb->update(
				$this->tables['invoices'],
				array( 'reminder_sent_at' => current_time( 'mysql' ) ),
				array( 'id' => $invoice->id )
			);
		}
	}

	/**
	 * Get stats.
	 */
	public function rest_get_stats( $request ) {
		global $wpdb;

		$stats = array(
			'total_invoices'    => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['invoices']} WHERE invoice_type = 'invoice'"
			),
			'draft'             => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['invoices']} WHERE status = 'draft'"
			),
			'outstanding'       => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['invoices']} WHERE status IN ('sent', 'viewed', 'partial')"
			),
			'overdue'           => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['invoices']} WHERE status = 'overdue'"
			),
			'paid'              => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['invoices']} WHERE status = 'paid'"
			),
			'total_invoiced'    => $wpdb->get_var(
				"SELECT COALESCE(SUM(total), 0) FROM {$this->tables['invoices']} WHERE invoice_type = 'invoice'"
			),
			'total_outstanding' => $wpdb->get_var(
				"SELECT COALESCE(SUM(amount_due), 0) FROM {$this->tables['invoices']} WHERE status IN ('sent', 'viewed', 'partial', 'overdue')"
			),
			'total_overdue'     => $wpdb->get_var(
				"SELECT COALESCE(SUM(amount_due), 0) FROM {$this->tables['invoices']} WHERE status = 'overdue'"
			),
			'total_paid_ytd'    => $wpdb->get_var(
				"SELECT COALESCE(SUM(amount), 0) FROM {$this->tables['payments']} WHERE YEAR(payment_date) = YEAR(CURDATE())"
			),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Duplicate invoice.
	 */
	public function rest_duplicate_invoice( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$id
			)
		);

		if ( ! $invoice ) {
			return new WP_Error( 'not_found', 'Invoice not found', array( 'status' => 404 ) );
		}

		$new_invoice_number = $this->generate_invoice_number();

		$new_data = (array) $invoice;
		unset( $new_data['id'], $new_data['created_at'], $new_data['updated_at'] );
		$new_data['invoice_number'] = $new_invoice_number;
		$new_data['status']         = 'draft';
		$new_data['amount_paid']    = 0;
		$new_data['amount_due']     = $invoice->total;
		$new_data['issue_date']     = current_time( 'Y-m-d' );
		$new_data['due_date']       = date( 'Y-m-d', strtotime( '+30 days' ) );
		$new_data['sent_at']        = null;
		$new_data['viewed_at']      = null;
		$new_data['paid_at']        = null;
		$new_data['created_by']     = get_current_user_id();

		$wpdb->insert( $this->tables['invoices'], $new_data );
		$new_invoice_id = $wpdb->insert_id;

		// Copy line items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE invoice_id = %d",
				$id
			)
		);

		foreach ( $items as $item ) {
			$item_data = (array) $item;
			unset( $item_data['id'], $item_data['created_at'] );
			$item_data['invoice_id'] = $new_invoice_id;
			$wpdb->insert( $this->tables['line_items'], $item_data );
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'invoice_id'     => $new_invoice_id,
				'invoice_number' => $new_invoice_number,
				'message'        => 'Invoice duplicated successfully',
			)
		);
	}

	/**
	 * Create credit note.
	 */
	public function rest_create_credit_note( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$invoice = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['invoices']} WHERE id = %d",
				$id
			)
		);

		if ( ! $invoice ) {
			return new WP_Error( 'not_found', 'Invoice not found', array( 'status' => 404 ) );
		}

		$credit_note_number = $this->generate_invoice_number( 'credit_note' );
		$amount             = floatval( $request->get_param( 'amount' ) ) ?: $invoice->total;

		$wpdb->insert(
			$this->tables['invoices'],
			array(
				'invoice_number' => $credit_note_number,
				'invoice_type'   => 'credit_note',
				'client_id'      => $invoice->client_id,
				'client_name'    => $invoice->client_name,
				'client_email'   => $invoice->client_email,
				'project_id'     => $invoice->project_id,
				'subtotal'       => -$amount,
				'total'          => -$amount,
				'amount_due'     => 0,
				'issue_date'     => current_time( 'Y-m-d' ),
				'due_date'       => current_time( 'Y-m-d' ),
				'status'         => 'paid',
				'notes'          => sprintf( 'Credit note for invoice %s', $invoice->invoice_number ),
				'created_by'     => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'success'            => true,
				'credit_note_id'     => $wpdb->insert_id,
				'credit_note_number' => $credit_note_number,
				'message'            => 'Credit note created successfully',
			)
		);
	}

	/**
	 * Create invoice from project.
	 */
	public function rest_create_from_project( $request ) {
		global $wpdb;

		$project_id = intval( $request->get_param( 'project_id' ) );

		// Get project details (simplified).
		$project = get_post( $project_id );
		if ( ! $project || $project->post_type !== 'ict_project' ) {
			return new WP_Error( 'not_found', 'Project not found', array( 'status' => 404 ) );
		}

		$invoice_number = $this->generate_invoice_number();

		$wpdb->insert(
			$this->tables['invoices'],
			array(
				'invoice_number' => $invoice_number,
				'client_id'      => get_post_meta( $project_id, '_ict_client_id', true ),
				'client_name'    => get_post_meta( $project_id, '_ict_client_name', true ),
				'project_id'     => $project_id,
				'issue_date'     => current_time( 'Y-m-d' ),
				'due_date'       => date( 'Y-m-d', strtotime( '+30 days' ) ),
				'notes'          => 'Invoice for project: ' . $project->post_title,
				'created_by'     => get_current_user_id(),
			)
		);

		$invoice_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'success'        => true,
				'invoice_id'     => $invoice_id,
				'invoice_number' => $invoice_number,
				'message'        => 'Invoice created from project',
			)
		);
	}

	/**
	 * Create invoice from quote.
	 */
	public function rest_create_from_quote( $request ) {
		global $wpdb;

		$quote_id = intval( $request->get_param( 'quote_id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_quotes WHERE id = %d",
				$quote_id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		$invoice_number = $this->generate_invoice_number();

		$wpdb->insert(
			$this->tables['invoices'],
			array(
				'invoice_number'   => $invoice_number,
				'client_id'        => $quote->client_id,
				'client_name'      => $quote->client_name,
				'client_email'     => $quote->client_email,
				'client_address'   => $quote->client_address,
				'quote_id'         => $quote_id,
				'currency'         => $quote->currency,
				'subtotal'         => $quote->subtotal,
				'discount_type'    => $quote->discount_type,
				'discount_value'   => $quote->discount_value,
				'discount_amount'  => $quote->discount_amount,
				'tax_rate'         => $quote->tax_rate,
				'tax_amount'       => $quote->tax_amount,
				'total'            => $quote->total,
				'amount_due'       => $quote->total,
				'issue_date'       => current_time( 'Y-m-d' ),
				'due_date'         => date( 'Y-m-d', strtotime( '+30 days' ) ),
				'terms_conditions' => $quote->terms_conditions,
				'notes'            => $quote->notes,
				'created_by'       => get_current_user_id(),
			)
		);

		$invoice_id = $wpdb->insert_id;

		// Copy line items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_quote_line_items WHERE quote_id = %d AND is_optional = 0",
				$quote_id
			)
		);

		foreach ( $items as $item ) {
			$wpdb->insert(
				$this->tables['line_items'],
				array(
					'invoice_id'  => $invoice_id,
					'item_type'   => $item->item_type === 'labor' ? 'service' : ( $item->item_type === 'material' ? 'product' : 'other' ),
					'description' => $item->description,
					'quantity'    => $item->quantity,
					'unit'        => $item->unit,
					'unit_price'  => $item->unit_price,
					'total'       => $item->total,
					'is_taxable'  => $item->is_taxable,
					'sort_order'  => $item->sort_order,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'invoice_id'     => $invoice_id,
				'invoice_number' => $invoice_number,
				'message'        => 'Invoice created from quote',
			)
		);
	}
}
