<?php
/**
 * Estimate/Quote Builder
 *
 * Comprehensive quoting system including:
 * - Quote creation with line items
 * - Labor, materials, and markup calculations
 * - Quote templates and versioning
 * - PDF generation and email delivery
 * - Quote acceptance/rejection tracking
 * - Conversion to projects
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Quote_Builder
 */
class ICT_Quote_Builder {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Quote_Builder
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
	 * @return ICT_Quote_Builder
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
			'quotes'     => $wpdb->prefix . 'ict_quotes',
			'line_items' => $wpdb->prefix . 'ict_quote_line_items',
			'versions'   => $wpdb->prefix . 'ict_quote_versions',
			'templates'  => $wpdb->prefix . 'ict_quote_templates',
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
		add_action( 'ict_check_quote_expirations', array( $this, 'check_expirations' ) );

		if ( ! wp_next_scheduled( 'ict_check_quote_expirations' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_check_quote_expirations' );
		}
	}

	/**
	 * Create database tables.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Quotes table.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['quotes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_number varchar(50) NOT NULL,
            version int(11) DEFAULT 1,
            client_id bigint(20) unsigned DEFAULT NULL,
            client_name varchar(200) NOT NULL,
            client_email varchar(200) DEFAULT NULL,
            client_phone varchar(50) DEFAULT NULL,
            client_address text,
            project_name varchar(200) NOT NULL,
            description text,
            status enum('draft','sent','viewed','accepted','rejected','expired','converted') DEFAULT 'draft',
            currency varchar(3) DEFAULT 'USD',
            subtotal decimal(12,2) DEFAULT 0,
            discount_type enum('percentage','fixed') DEFAULT 'percentage',
            discount_value decimal(12,2) DEFAULT 0,
            discount_amount decimal(12,2) DEFAULT 0,
            tax_rate decimal(5,2) DEFAULT 0,
            tax_amount decimal(12,2) DEFAULT 0,
            total decimal(12,2) DEFAULT 0,
            margin_percentage decimal(5,2) DEFAULT NULL,
            terms_conditions longtext,
            notes text,
            internal_notes text,
            valid_until date DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            viewed_at datetime DEFAULT NULL,
            accepted_at datetime DEFAULT NULL,
            rejected_at datetime DEFAULT NULL,
            rejection_reason text,
            converted_project_id bigint(20) unsigned DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY quote_number_version (quote_number, version),
            KEY client_id (client_id),
            KEY status (status),
            KEY valid_until (valid_until)
        ) $charset_collate;";

		// Line items.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['line_items']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            item_type enum('labor','material','equipment','subcontractor','other') DEFAULT 'material',
            category varchar(100) DEFAULT NULL,
            description text NOT NULL,
            quantity decimal(12,4) DEFAULT 1,
            unit varchar(50) DEFAULT 'each',
            unit_cost decimal(12,4) DEFAULT 0,
            markup_percentage decimal(5,2) DEFAULT 0,
            unit_price decimal(12,4) DEFAULT 0,
            total decimal(12,2) DEFAULT 0,
            is_taxable tinyint(1) DEFAULT 1,
            is_optional tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quote_id (quote_id),
            KEY item_type (item_type)
        ) $charset_collate;";

		// Quote versions.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['versions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            version int(11) NOT NULL,
            snapshot longtext NOT NULL,
            change_notes text,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quote_id (quote_id)
        ) $charset_collate;";

		// Quote templates.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['templates']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            category varchar(100) DEFAULT NULL,
            line_items longtext,
            terms_conditions longtext,
            default_markup decimal(5,2) DEFAULT 0,
            default_tax_rate decimal(5,2) DEFAULT 0,
            default_validity_days int(11) DEFAULT 30,
            is_active tinyint(1) DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category)
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

		// Quotes.
		register_rest_route(
			$namespace,
			'/quotes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_quotes' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_quote' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/quotes/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_quote' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_quote' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_quote' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Line items.
		register_rest_route(
			$namespace,
			'/quotes/(?P<quote_id>\d+)/items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_line_items' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_add_line_item' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/quote-items/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_line_item' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_line_item' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Quote actions.
		register_rest_route(
			$namespace,
			'/quotes/(?P<id>\d+)/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_send_quote' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/quotes/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_duplicate_quote' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/quotes/(?P<id>\d+)/new-version',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create_new_version' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/quotes/(?P<id>\d+)/convert',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_convert_to_project' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/quotes/(?P<id>\d+)/pdf',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_generate_pdf' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Client quote view (public).
		register_rest_route(
			$namespace,
			'/quotes/view/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_view_quote_public' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/quotes/respond/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_respond_to_quote' ),
				'permission_callback' => '__return_true',
			)
		);

		// Templates.
		register_rest_route(
			$namespace,
			'/quote-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_templates' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Stats.
		register_rest_route(
			$namespace,
			'/quotes/stats',
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
	 * Generate quote number.
	 */
	private function generate_quote_number() {
		global $wpdb;

		$prefix = get_option( 'ict_quote_prefix', 'Q' );
		$year   = date( 'Y' );

		$last_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(SUBSTRING(quote_number, %d) AS UNSIGNED))
            FROM {$this->tables['quotes']}
            WHERE quote_number LIKE %s",
				strlen( $prefix ) + 5,
				$prefix . $year . '%'
			)
		);

		$next_number = ( $last_number ?: 0 ) + 1;

		return $prefix . $year . str_pad( $next_number, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Get quotes.
	 */
	public function rest_get_quotes( $request ) {
		global $wpdb;

		$status    = $request->get_param( 'status' );
		$client_id = $request->get_param( 'client_id' );
		$search    = $request->get_param( 'search' );
		$page      = max( 1, intval( $request->get_param( 'page' ) ) );
		$per_page  = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$sql  = "SELECT q.*, u.display_name as created_by_name
                FROM {$this->tables['quotes']} q
                LEFT JOIN {$wpdb->users} u ON q.created_by = u.ID
                WHERE 1=1";
		$args = array();

		if ( $status ) {
			$sql   .= ' AND q.status = %s';
			$args[] = $status;
		}

		if ( $client_id ) {
			$sql   .= ' AND q.client_id = %d';
			$args[] = $client_id;
		}

		if ( $search ) {
			$sql   .= ' AND (q.quote_number LIKE %s OR q.client_name LIKE %s OR q.project_name LIKE %s)';
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		$count_sql = str_replace( 'SELECT q.*, u.display_name as created_by_name', 'SELECT COUNT(*)', $sql );
		$total     = ! empty( $args ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql );

		$sql   .= ' ORDER BY q.created_at DESC LIMIT %d OFFSET %d';
		$args[] = $per_page;
		$args[] = ( $page - 1 ) * $per_page;

		$quotes = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'quotes'  => $quotes,
				'total'   => intval( $total ),
				'pages'   => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Get single quote.
	 */
	public function rest_get_quote( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT q.*, u.display_name as created_by_name
            FROM {$this->tables['quotes']} q
            LEFT JOIN {$wpdb->users} u ON q.created_by = u.ID
            WHERE q.id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		// Get line items.
		$quote->line_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d ORDER BY sort_order, id",
				$id
			)
		);

		// Get versions.
		$quote->versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.id, v.version, v.change_notes, v.created_at, u.display_name as created_by_name
            FROM {$this->tables['versions']} v
            LEFT JOIN {$wpdb->users} u ON v.created_by = u.ID
            WHERE v.quote_id = %d
            ORDER BY v.version DESC",
				$id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'quote'   => $quote,
			)
		);
	}

	/**
	 * Create quote.
	 */
	public function rest_create_quote( $request ) {
		global $wpdb;

		$quote_number = $this->generate_quote_number();
		$template_id  = intval( $request->get_param( 'template_id' ) );

		$data = array(
			'quote_number'     => $quote_number,
			'client_id'        => intval( $request->get_param( 'client_id' ) ) ?: null,
			'client_name'      => sanitize_text_field( $request->get_param( 'client_name' ) ),
			'client_email'     => sanitize_email( $request->get_param( 'client_email' ) ),
			'client_phone'     => sanitize_text_field( $request->get_param( 'client_phone' ) ),
			'client_address'   => sanitize_textarea_field( $request->get_param( 'client_address' ) ),
			'project_name'     => sanitize_text_field( $request->get_param( 'project_name' ) ),
			'description'      => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'currency'         => sanitize_text_field( $request->get_param( 'currency' ) ) ?: 'USD',
			'discount_type'    => sanitize_text_field( $request->get_param( 'discount_type' ) ) ?: 'percentage',
			'discount_value'   => floatval( $request->get_param( 'discount_value' ) ),
			'tax_rate'         => floatval( $request->get_param( 'tax_rate' ) ),
			'terms_conditions' => wp_kses_post( $request->get_param( 'terms_conditions' ) ),
			'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'internal_notes'   => sanitize_textarea_field( $request->get_param( 'internal_notes' ) ),
			'valid_until'      => sanitize_text_field( $request->get_param( 'valid_until' ) ),
			'created_by'       => get_current_user_id(),
		);

		// Apply template if specified.
		if ( $template_id ) {
			$template = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->tables['templates']} WHERE id = %d",
					$template_id
				)
			);

			if ( $template ) {
				if ( empty( $data['terms_conditions'] ) && $template->terms_conditions ) {
					$data['terms_conditions'] = $template->terms_conditions;
				}
				if ( ! $data['tax_rate'] && $template->default_tax_rate ) {
					$data['tax_rate'] = $template->default_tax_rate;
				}
				if ( ! $data['valid_until'] && $template->default_validity_days ) {
					$data['valid_until'] = date( 'Y-m-d', strtotime( '+' . $template->default_validity_days . ' days' ) );
				}
			}
		}

		$wpdb->insert( $this->tables['quotes'], $data );
		$quote_id = $wpdb->insert_id;

		// Add template line items if specified.
		if ( $template_id && $template && $template->line_items ) {
			$items = json_decode( $template->line_items, true );
			if ( is_array( $items ) ) {
				foreach ( $items as $index => $item ) {
					$wpdb->insert(
						$this->tables['line_items'],
						array(
							'quote_id'          => $quote_id,
							'item_type'         => $item['item_type'] ?? 'material',
							'category'          => $item['category'] ?? null,
							'description'       => $item['description'] ?? '',
							'quantity'          => $item['quantity'] ?? 1,
							'unit'              => $item['unit'] ?? 'each',
							'unit_cost'         => $item['unit_cost'] ?? 0,
							'markup_percentage' => $item['markup_percentage'] ?? $template->default_markup,
							'is_taxable'        => $item['is_taxable'] ?? 1,
							'sort_order'        => $index,
						)
					);
				}
				$this->recalculate_quote( $quote_id );
			}
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'quote_id'     => $quote_id,
				'quote_number' => $quote_number,
				'message'      => 'Quote created successfully',
			)
		);
	}

	/**
	 * Update quote.
	 */
	public function rest_update_quote( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		$fields = array(
			'client_name',
			'client_email',
			'client_phone',
			'client_address',
			'project_name',
			'description',
			'discount_type',
			'discount_value',
			'tax_rate',
			'terms_conditions',
			'notes',
			'internal_notes',
			'valid_until',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->tables['quotes'], $data, array( 'id' => $id ) );
			$this->recalculate_quote( $id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Quote updated successfully',
			)
		);
	}

	/**
	 * Delete quote.
	 */
	public function rest_delete_quote( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->tables['quotes'], array( 'id' => $id ) );
		$wpdb->delete( $this->tables['line_items'], array( 'quote_id' => $id ) );
		$wpdb->delete( $this->tables['versions'], array( 'quote_id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Quote deleted successfully',
			)
		);
	}

	/**
	 * Get line items.
	 */
	public function rest_get_line_items( $request ) {
		global $wpdb;

		$quote_id = intval( $request->get_param( 'quote_id' ) );

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d ORDER BY sort_order, id",
				$quote_id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'items'   => $items,
			)
		);
	}

	/**
	 * Add line item.
	 */
	public function rest_add_line_item( $request ) {
		global $wpdb;

		$quote_id = intval( $request->get_param( 'quote_id' ) );

		$unit_cost  = floatval( $request->get_param( 'unit_cost' ) );
		$markup     = floatval( $request->get_param( 'markup_percentage' ) );
		$unit_price = $unit_cost * ( 1 + $markup / 100 );
		$quantity   = floatval( $request->get_param( 'quantity' ) ) ?: 1;
		$total      = $unit_price * $quantity;

		$wpdb->insert(
			$this->tables['line_items'],
			array(
				'quote_id'          => $quote_id,
				'item_type'         => sanitize_text_field( $request->get_param( 'item_type' ) ) ?: 'material',
				'category'          => sanitize_text_field( $request->get_param( 'category' ) ),
				'description'       => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'quantity'          => $quantity,
				'unit'              => sanitize_text_field( $request->get_param( 'unit' ) ) ?: 'each',
				'unit_cost'         => $unit_cost,
				'markup_percentage' => $markup,
				'unit_price'        => $unit_price,
				'total'             => $total,
				'is_taxable'        => $request->get_param( 'is_taxable' ) !== false ? 1 : 0,
				'is_optional'       => $request->get_param( 'is_optional' ) ? 1 : 0,
				'sort_order'        => intval( $request->get_param( 'sort_order' ) ),
				'notes'             => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			)
		);

		$this->recalculate_quote( $quote_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'item_id' => $wpdb->insert_id,
				'message' => 'Line item added successfully',
			)
		);
	}

	/**
	 * Update line item.
	 */
	public function rest_update_line_item( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE id = %d",
				$id
			)
		);

		if ( ! $item ) {
			return new WP_Error( 'not_found', 'Line item not found', array( 'status' => 404 ) );
		}

		$fields = array(
			'item_type',
			'category',
			'description',
			'quantity',
			'unit',
			'unit_cost',
			'markup_percentage',
			'is_taxable',
			'is_optional',
			'sort_order',
			'notes',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		// Recalculate prices.
		$unit_cost = isset( $data['unit_cost'] ) ? $data['unit_cost'] : $item->unit_cost;
		$markup    = isset( $data['markup_percentage'] ) ? $data['markup_percentage'] : $item->markup_percentage;
		$quantity  = isset( $data['quantity'] ) ? $data['quantity'] : $item->quantity;

		$data['unit_price'] = $unit_cost * ( 1 + $markup / 100 );
		$data['total']      = $data['unit_price'] * $quantity;

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->tables['line_items'], $data, array( 'id' => $id ) );
			$this->recalculate_quote( $item->quote_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Line item updated successfully',
			)
		);
	}

	/**
	 * Delete line item.
	 */
	public function rest_delete_line_item( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT quote_id FROM {$this->tables['line_items']} WHERE id = %d",
				$id
			)
		);

		if ( $item ) {
			$wpdb->delete( $this->tables['line_items'], array( 'id' => $id ) );
			$this->recalculate_quote( $item->quote_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Line item deleted successfully',
			)
		);
	}

	/**
	 * Recalculate quote totals.
	 */
	private function recalculate_quote( $quote_id ) {
		global $wpdb;

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$quote_id
			)
		);

		if ( ! $quote ) {
			return;
		}

		// Get non-optional items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d AND is_optional = 0",
				$quote_id
			)
		);

		$subtotal         = 0;
		$taxable_subtotal = 0;
		$total_cost       = 0;

		foreach ( $items as $item ) {
			$subtotal   += floatval( $item->total );
			$total_cost += floatval( $item->unit_cost ) * floatval( $item->quantity );
			if ( $item->is_taxable ) {
				$taxable_subtotal += floatval( $item->total );
			}
		}

		// Calculate discount.
		$discount_amount = 0;
		if ( $quote->discount_value > 0 ) {
			if ( $quote->discount_type === 'percentage' ) {
				$discount_amount = $subtotal * ( $quote->discount_value / 100 );
			} else {
				$discount_amount = $quote->discount_value;
			}
		}

		// Calculate tax.
		$taxable_after_discount = $taxable_subtotal - ( $taxable_subtotal / $subtotal * $discount_amount );
		$tax_amount             = $taxable_after_discount * ( $quote->tax_rate / 100 );

		// Calculate total.
		$total = $subtotal - $discount_amount + $tax_amount;

		// Calculate margin.
		$margin_percentage = $total_cost > 0 ? ( ( $subtotal - $total_cost ) / $subtotal ) * 100 : 0;

		$wpdb->update(
			$this->tables['quotes'],
			array(
				'subtotal'          => round( $subtotal, 2 ),
				'discount_amount'   => round( $discount_amount, 2 ),
				'tax_amount'        => round( $tax_amount, 2 ),
				'total'             => round( $total, 2 ),
				'margin_percentage' => round( $margin_percentage, 2 ),
			),
			array( 'id' => $quote_id )
		);
	}

	/**
	 * Send quote.
	 */
	public function rest_send_quote( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		if ( ! $quote->client_email ) {
			return new WP_Error( 'no_email', 'Client email is required', array( 'status' => 400 ) );
		}

		// Generate view token.
		$token = wp_generate_password( 32, false );
		update_post_meta( $id, '_ict_quote_token', $token );

		$view_url = add_query_arg(
			array(
				'ict_quote' => $token,
			),
			home_url()
		);

		// Send email.
		$subject = sprintf( 'Quote %s - %s', $quote->quote_number, $quote->project_name );
		$message = $request->get_param( 'message' ) ?: sprintf(
			"Dear %s,\n\nPlease find attached our quote for %s.\n\nView your quote online: %s\n\nThis quote is valid until %s.\n\nBest regards",
			$quote->client_name,
			$quote->project_name,
			$view_url,
			$quote->valid_until ? date( 'F j, Y', strtotime( $quote->valid_until ) ) : 'further notice'
		);

		wp_mail( $quote->client_email, $subject, $message );

		$wpdb->update(
			$this->tables['quotes'],
			array(
				'status'  => 'sent',
				'sent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'view_url' => $view_url,
				'message'  => 'Quote sent successfully',
			)
		);
	}

	/**
	 * Duplicate quote.
	 */
	public function rest_duplicate_quote( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		// Create new quote.
		$new_quote_number = $this->generate_quote_number();

		$new_data = (array) $quote;
		unset( $new_data['id'], $new_data['created_at'], $new_data['updated_at'] );
		$new_data['quote_number']         = $new_quote_number;
		$new_data['version']              = 1;
		$new_data['status']               = 'draft';
		$new_data['sent_at']              = null;
		$new_data['viewed_at']            = null;
		$new_data['accepted_at']          = null;
		$new_data['rejected_at']          = null;
		$new_data['converted_project_id'] = null;
		$new_data['created_by']           = get_current_user_id();

		$wpdb->insert( $this->tables['quotes'], $new_data );
		$new_quote_id = $wpdb->insert_id;

		// Copy line items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d",
				$id
			)
		);

		foreach ( $items as $item ) {
			$item_data = (array) $item;
			unset( $item_data['id'], $item_data['created_at'] );
			$item_data['quote_id'] = $new_quote_id;
			$wpdb->insert( $this->tables['line_items'], $item_data );
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'quote_id'     => $new_quote_id,
				'quote_number' => $new_quote_number,
				'message'      => 'Quote duplicated successfully',
			)
		);
	}

	/**
	 * Create new version.
	 */
	public function rest_create_new_version( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		// Save current version.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d",
				$id
			)
		);

		$snapshot = wp_json_encode(
			array(
				'quote' => $quote,
				'items' => $items,
			)
		);

		$wpdb->insert(
			$this->tables['versions'],
			array(
				'quote_id'     => $id,
				'version'      => $quote->version,
				'snapshot'     => $snapshot,
				'change_notes' => sanitize_textarea_field( $request->get_param( 'change_notes' ) ),
				'created_by'   => get_current_user_id(),
			)
		);

		// Increment version.
		$wpdb->update(
			$this->tables['quotes'],
			array(
				'version' => $quote->version + 1,
				'status'  => 'draft',
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'new_version' => $quote->version + 1,
				'message'     => 'New version created successfully',
			)
		);
	}

	/**
	 * Convert to project.
	 */
	public function rest_convert_to_project( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		if ( $quote->status !== 'accepted' ) {
			return new WP_Error( 'not_accepted', 'Quote must be accepted before conversion', array( 'status' => 400 ) );
		}

		// Create project (simplified - would integrate with project system).
		$project_data = array(
			'post_title'  => $quote->project_name,
			'post_type'   => 'ict_project',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_ict_client_id'   => $quote->client_id,
				'_ict_client_name' => $quote->client_name,
				'_ict_budget'      => $quote->total,
				'_ict_quote_id'    => $id,
				'_ict_description' => $quote->description,
			),
		);

		$project_id = wp_insert_post( $project_data );

		if ( $project_id ) {
			$wpdb->update(
				$this->tables['quotes'],
				array(
					'status'               => 'converted',
					'converted_project_id' => $project_id,
				),
				array( 'id' => $id )
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'project_id' => $project_id,
				'message'    => 'Quote converted to project successfully',
			)
		);
	}

	/**
	 * Generate PDF.
	 */
	public function rest_generate_pdf( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		// Get line items.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d ORDER BY sort_order, id",
				$id
			)
		);

		// Generate HTML for PDF (would use a PDF library like TCPDF or Dompdf).
		$html = $this->generate_quote_html( $quote, $items );

		return rest_ensure_response(
			array(
				'success' => true,
				'html'    => $html,
				'message' => 'PDF generation data prepared',
			)
		);
	}

	/**
	 * Generate quote HTML.
	 */
	private function generate_quote_html( $quote, $items ) {
		ob_start();
		?>
		<html>
		<head>
			<style>
				body { font-family: Arial, sans-serif; font-size: 12px; }
				.header { text-align: center; margin-bottom: 30px; }
				.client-info { margin-bottom: 20px; }
				table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
				th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
				th { background-color: #f5f5f5; }
				.totals { text-align: right; }
				.terms { margin-top: 30px; font-size: 10px; }
			</style>
		</head>
		<body>
			<div class="header">
				<h1>Quote <?php echo esc_html( $quote->quote_number ); ?></h1>
				<p>Version <?php echo esc_html( $quote->version ); ?></p>
			</div>

			<div class="client-info">
				<strong><?php echo esc_html( $quote->client_name ); ?></strong><br>
				<?php echo nl2br( esc_html( $quote->client_address ) ); ?><br>
				<?php echo esc_html( $quote->client_email ); ?>
			</div>

			<h2><?php echo esc_html( $quote->project_name ); ?></h2>
			<p><?php echo nl2br( esc_html( $quote->description ) ); ?></p>

			<table>
				<thead>
					<tr>
						<th>Description</th>
						<th>Qty</th>
						<th>Unit</th>
						<th>Price</th>
						<th>Total</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item->description ); ?></td>
						<td><?php echo esc_html( $item->quantity ); ?></td>
						<td><?php echo esc_html( $item->unit ); ?></td>
						<td><?php echo esc_html( number_format( $item->unit_price, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format( $item->total, 2 ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="totals">
				<p>Subtotal: <?php echo esc_html( $quote->currency . ' ' . number_format( $quote->subtotal, 2 ) ); ?></p>
				<?php if ( $quote->discount_amount > 0 ) : ?>
				<p>Discount: -<?php echo esc_html( $quote->currency . ' ' . number_format( $quote->discount_amount, 2 ) ); ?></p>
				<?php endif; ?>
				<?php if ( $quote->tax_amount > 0 ) : ?>
				<p>Tax (<?php echo esc_html( $quote->tax_rate ); ?>%): <?php echo esc_html( $quote->currency . ' ' . number_format( $quote->tax_amount, 2 ) ); ?></p>
				<?php endif; ?>
				<p><strong>Total: <?php echo esc_html( $quote->currency . ' ' . number_format( $quote->total, 2 ) ); ?></strong></p>
			</div>

			<?php if ( $quote->valid_until ) : ?>
			<p><strong>Valid Until: <?php echo esc_html( date( 'F j, Y', strtotime( $quote->valid_until ) ) ); ?></strong></p>
			<?php endif; ?>

			<?php if ( $quote->terms_conditions ) : ?>
			<div class="terms">
				<h3>Terms & Conditions</h3>
				<?php echo wp_kses_post( $quote->terms_conditions ); ?>
			</div>
			<?php endif; ?>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * View quote public.
	 */
	public function rest_view_quote_public( $request ) {
		global $wpdb;

		$token = sanitize_text_field( $request->get_param( 'token' ) );

		// Find quote by token.
		$quote_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ict_quote_token' AND meta_value = %s",
				$token
			)
		);

		if ( ! $quote_id ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$quote_id
			)
		);

		if ( ! $quote ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		// Mark as viewed.
		if ( ! $quote->viewed_at ) {
			$wpdb->update(
				$this->tables['quotes'],
				array( 'viewed_at' => current_time( 'mysql' ) ),
				array( 'id' => $quote_id )
			);
		}

		// Get line items.
		$quote->line_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['line_items']} WHERE quote_id = %d ORDER BY sort_order, id",
				$quote_id
			)
		);

		// Remove sensitive data.
		unset( $quote->internal_notes, $quote->margin_percentage );

		return rest_ensure_response(
			array(
				'success' => true,
				'quote'   => $quote,
			)
		);
	}

	/**
	 * Respond to quote.
	 */
	public function rest_respond_to_quote( $request ) {
		global $wpdb;

		$token  = sanitize_text_field( $request->get_param( 'token' ) );
		$action = sanitize_text_field( $request->get_param( 'action' ) );

		$quote_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ict_quote_token' AND meta_value = %s",
				$token
			)
		);

		if ( ! $quote_id ) {
			return new WP_Error( 'not_found', 'Quote not found', array( 'status' => 404 ) );
		}

		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['quotes']} WHERE id = %d",
				$quote_id
			)
		);

		if ( ! in_array( $quote->status, array( 'sent', 'viewed' ) ) ) {
			return new WP_Error( 'invalid_status', 'Quote cannot be responded to', array( 'status' => 400 ) );
		}

		if ( $action === 'accept' ) {
			$wpdb->update(
				$this->tables['quotes'],
				array(
					'status'      => 'accepted',
					'accepted_at' => current_time( 'mysql' ),
				),
				array( 'id' => $quote_id )
			);

			do_action( 'ict_quote_accepted', $quote );

		} elseif ( $action === 'reject' ) {
			$wpdb->update(
				$this->tables['quotes'],
				array(
					'status'           => 'rejected',
					'rejected_at'      => current_time( 'mysql' ),
					'rejection_reason' => sanitize_textarea_field( $request->get_param( 'reason' ) ),
				),
				array( 'id' => $quote_id )
			);

			do_action( 'ict_quote_rejected', $quote );

		} else {
			return new WP_Error( 'invalid_action', 'Invalid action', array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Response recorded successfully',
			)
		);
	}

	/**
	 * Get templates.
	 */
	public function rest_get_templates( $request ) {
		global $wpdb;

		$templates = $wpdb->get_results(
			"SELECT * FROM {$this->tables['templates']} WHERE is_active = 1 ORDER BY category, name"
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'templates' => $templates,
			)
		);
	}

	/**
	 * Create template.
	 */
	public function rest_create_template( $request ) {
		global $wpdb;

		$wpdb->insert(
			$this->tables['templates'],
			array(
				'name'                  => sanitize_text_field( $request->get_param( 'name' ) ),
				'description'           => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'category'              => sanitize_text_field( $request->get_param( 'category' ) ),
				'line_items'            => wp_json_encode( $request->get_param( 'line_items' ) ),
				'terms_conditions'      => wp_kses_post( $request->get_param( 'terms_conditions' ) ),
				'default_markup'        => floatval( $request->get_param( 'default_markup' ) ),
				'default_tax_rate'      => floatval( $request->get_param( 'default_tax_rate' ) ),
				'default_validity_days' => intval( $request->get_param( 'default_validity_days' ) ) ?: 30,
				'created_by'            => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'template_id' => $wpdb->insert_id,
				'message'     => 'Template created successfully',
			)
		);
	}

	/**
	 * Get stats.
	 */
	public function rest_get_stats( $request ) {
		global $wpdb;

		$stats = array(
			'total_quotes'    => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['quotes']}"
			),
			'draft'           => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['quotes']} WHERE status = 'draft'"
			),
			'sent'            => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['quotes']} WHERE status IN ('sent', 'viewed')"
			),
			'accepted'        => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['quotes']} WHERE status = 'accepted'"
			),
			'rejected'        => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['quotes']} WHERE status = 'rejected'"
			),
			'expired'         => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['quotes']} WHERE status = 'expired'"
			),
			'total_value'     => $wpdb->get_var(
				"SELECT COALESCE(SUM(total), 0) FROM {$this->tables['quotes']}"
			),
			'accepted_value'  => $wpdb->get_var(
				"SELECT COALESCE(SUM(total), 0) FROM {$this->tables['quotes']} WHERE status = 'accepted'"
			),
			'conversion_rate' => 0,
			'average_margin'  => $wpdb->get_var(
				"SELECT AVG(margin_percentage) FROM {$this->tables['quotes']} WHERE margin_percentage IS NOT NULL"
			),
		);

		// Calculate conversion rate.
		$sent_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->tables['quotes']} WHERE status IN ('sent', 'viewed', 'accepted', 'rejected')"
		);
		if ( $sent_count > 0 ) {
			$stats['conversion_rate'] = round( ( $stats['accepted'] / $sent_count ) * 100, 2 );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Check quote expirations.
	 */
	public function check_expirations() {
		global $wpdb;

		// Mark expired quotes.
		$wpdb->query(
			"UPDATE {$this->tables['quotes']}
            SET status = 'expired'
            WHERE status IN ('sent', 'viewed')
            AND valid_until IS NOT NULL
            AND valid_until < CURDATE()"
		);
	}
}
