<?php
/**
 * Equipment/Asset Management
 *
 * Comprehensive equipment and asset tracking including:
 * - Tool and equipment inventory
 * - Assignment to technicians/projects
 * - Maintenance schedules and history
 * - Calibration tracking
 * - Check-in/check-out system
 * - Depreciation calculations
 * - QR code/barcode integration
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Equipment_Manager
 */
class ICT_Equipment_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Equipment_Manager
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
	 * @return ICT_Equipment_Manager
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
			'equipment'   => $wpdb->prefix . 'ict_equipment',
			'assignments' => $wpdb->prefix . 'ict_equipment_assignments',
			'maintenance' => $wpdb->prefix . 'ict_equipment_maintenance',
			'checkouts'   => $wpdb->prefix . 'ict_equipment_checkouts',
			'categories'  => $wpdb->prefix . 'ict_equipment_categories',
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
		add_action( 'ict_check_maintenance_schedules', array( $this, 'check_maintenance_schedules' ) );

		if ( ! wp_next_scheduled( 'ict_check_maintenance_schedules' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_check_maintenance_schedules' );
		}
	}

	/**
	 * Create database tables.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Equipment categories.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['categories']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            parent_id bigint(20) unsigned DEFAULT NULL,
            depreciation_years int(11) DEFAULT 5,
            maintenance_interval_days int(11) DEFAULT 365,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id)
        ) $charset_collate;";

		// Equipment items.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['equipment']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            category_id bigint(20) unsigned DEFAULT NULL,
            serial_number varchar(100) DEFAULT NULL,
            model_number varchar(100) DEFAULT NULL,
            manufacturer varchar(100) DEFAULT NULL,
            barcode varchar(100) DEFAULT NULL,
            qr_code varchar(255) DEFAULT NULL,
            purchase_date date DEFAULT NULL,
            purchase_price decimal(12,2) DEFAULT NULL,
            current_value decimal(12,2) DEFAULT NULL,
            warranty_expiry date DEFAULT NULL,
            status enum('available','assigned','maintenance','retired','lost') DEFAULT 'available',
            condition_rating enum('excellent','good','fair','poor') DEFAULT 'good',
            location varchar(200) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            specifications longtext,
            notes text,
            last_maintenance_date date DEFAULT NULL,
            next_maintenance_date date DEFAULT NULL,
            last_calibration_date date DEFAULT NULL,
            next_calibration_date date DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY serial_number (serial_number),
            UNIQUE KEY barcode (barcode),
            KEY category_id (category_id),
            KEY status (status),
            KEY next_maintenance_date (next_maintenance_date)
        ) $charset_collate;";

		// Equipment assignments.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['assignments']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            assigned_to bigint(20) unsigned NOT NULL,
            assigned_type enum('user','project','location') DEFAULT 'user',
            project_id bigint(20) unsigned DEFAULT NULL,
            assigned_by bigint(20) unsigned NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            expected_return date DEFAULT NULL,
            returned_at datetime DEFAULT NULL,
            return_condition enum('excellent','good','fair','poor','damaged') DEFAULT NULL,
            notes text,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY assigned_to (assigned_to),
            KEY project_id (project_id),
            KEY assigned_at (assigned_at)
        ) $charset_collate;";

		// Maintenance records.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['maintenance']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            maintenance_type enum('preventive','corrective','calibration','inspection') DEFAULT 'preventive',
            description text NOT NULL,
            performed_by bigint(20) unsigned DEFAULT NULL,
            vendor varchar(200) DEFAULT NULL,
            cost decimal(12,2) DEFAULT NULL,
            scheduled_date date DEFAULT NULL,
            completed_date date DEFAULT NULL,
            status enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
            findings text,
            actions_taken text,
            parts_replaced text,
            next_maintenance_date date DEFAULT NULL,
            attachments longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY maintenance_type (maintenance_type),
            KEY status (status),
            KEY scheduled_date (scheduled_date)
        ) $charset_collate;";

		// Check-out log.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['checkouts']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            equipment_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            checkout_time datetime DEFAULT CURRENT_TIMESTAMP,
            checkin_time datetime DEFAULT NULL,
            checkout_location varchar(200) DEFAULT NULL,
            checkin_location varchar(200) DEFAULT NULL,
            checkout_condition enum('excellent','good','fair','poor') DEFAULT NULL,
            checkin_condition enum('excellent','good','fair','poor','damaged') DEFAULT NULL,
            purpose text,
            notes text,
            PRIMARY KEY (id),
            KEY equipment_id (equipment_id),
            KEY user_id (user_id),
            KEY checkout_time (checkout_time)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		$this->seed_default_categories();
	}

	/**
	 * Seed default equipment categories.
	 */
	private function seed_default_categories() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables['categories']}" );
		if ( $count > 0 ) {
			return;
		}

		$categories = array(
			array( 'Power Tools', 'power-tools', 'Electric and battery-powered tools', 5, 180 ),
			array( 'Hand Tools', 'hand-tools', 'Manual hand tools', 10, 365 ),
			array( 'Test Equipment', 'test-equipment', 'Meters, testers, and diagnostic equipment', 7, 365 ),
			array( 'Safety Equipment', 'safety-equipment', 'PPE and safety gear', 3, 90 ),
			array( 'Vehicles', 'vehicles', 'Company vehicles and trailers', 7, 90 ),
			array( 'Ladders & Scaffolding', 'ladders-scaffolding', 'Ladders, scaffolds, and access equipment', 10, 180 ),
			array( 'Communication', 'communication', 'Radios, phones, and communication devices', 3, 365 ),
			array( 'IT Equipment', 'it-equipment', 'Computers, tablets, and accessories', 4, 365 ),
		);

		foreach ( $categories as $cat ) {
			$wpdb->insert(
				$this->tables['categories'],
				array(
					'name'                      => $cat[0],
					'slug'                      => $cat[1],
					'description'               => $cat[2],
					'depreciation_years'        => $cat[3],
					'maintenance_interval_days' => $cat[4],
				),
				array( '%s', '%s', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$namespace = 'ict/v1';

		// Equipment endpoints.
		register_rest_route(
			$namespace,
			'/equipment',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_equipment' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_equipment' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/equipment/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_equipment_item' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_equipment' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_equipment' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Assignment endpoints.
		register_rest_route(
			$namespace,
			'/equipment/(?P<id>\d+)/assign',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_assign_equipment' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/equipment/(?P<id>\d+)/return',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_return_equipment' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Checkout endpoints.
		register_rest_route(
			$namespace,
			'/equipment/(?P<id>\d+)/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_checkout_equipment' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/equipment/(?P<id>\d+)/checkin',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_checkin_equipment' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Maintenance endpoints.
		register_rest_route(
			$namespace,
			'/equipment/(?P<id>\d+)/maintenance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_maintenance' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_maintenance' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Categories.
		register_rest_route(
			$namespace,
			'/equipment-categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_categories' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_category' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Barcode/QR lookup.
		register_rest_route(
			$namespace,
			'/equipment/lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_lookup_equipment' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Reports.
		register_rest_route(
			$namespace,
			'/equipment/reports/depreciation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_depreciation_report' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/equipment/reports/utilization',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_utilization_report' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
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
		return current_user_can( 'manage_ict_inventory' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get equipment list.
	 */
	public function rest_get_equipment( $request ) {
		global $wpdb;

		$status      = $request->get_param( 'status' );
		$category    = $request->get_param( 'category' );
		$assigned_to = $request->get_param( 'assigned_to' );
		$search      = $request->get_param( 'search' );
		$page        = max( 1, intval( $request->get_param( 'page' ) ) );
		$per_page    = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$sql  = "SELECT e.*, c.name as category_name,
                (SELECT assigned_to FROM {$this->tables['assignments']}
                 WHERE equipment_id = e.id AND returned_at IS NULL
                 ORDER BY assigned_at DESC LIMIT 1) as current_assignee
                FROM {$this->tables['equipment']} e
                LEFT JOIN {$this->tables['categories']} c ON e.category_id = c.id
                WHERE 1=1";
		$args = array();

		if ( $status ) {
			$sql   .= ' AND e.status = %s';
			$args[] = $status;
		}

		if ( $category ) {
			$sql   .= ' AND e.category_id = %d';
			$args[] = $category;
		}

		if ( $assigned_to ) {
			$sql   .= " AND e.id IN (SELECT equipment_id FROM {$this->tables['assignments']} WHERE assigned_to = %d AND returned_at IS NULL)";
			$args[] = $assigned_to;
		}

		if ( $search ) {
			$sql   .= ' AND (e.name LIKE %s OR e.serial_number LIKE %s OR e.barcode LIKE %s)';
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		// Count total.
		$count_sql = str_replace(
			'SELECT e.*, c.name as category_name,
                (SELECT assigned_to FROM ' . $this->tables['assignments'] . '
                 WHERE equipment_id = e.id AND returned_at IS NULL
                 ORDER BY assigned_at DESC LIMIT 1) as current_assignee',
			'SELECT COUNT(*)',
			$sql
		);

		if ( ! empty( $args ) ) {
			$total = $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );
		} else {
			$total = $wpdb->get_var( $count_sql );
		}

		$sql   .= ' ORDER BY e.name ASC LIMIT %d OFFSET %d';
		$args[] = $per_page;
		$args[] = ( $page - 1 ) * $per_page;

		$equipment = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		return rest_ensure_response(
			array(
				'success'   => true,
				'equipment' => $equipment,
				'total'     => intval( $total ),
				'pages'     => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Get single equipment item.
	 */
	public function rest_get_equipment_item( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT e.*, c.name as category_name
            FROM {$this->tables['equipment']} e
            LEFT JOIN {$this->tables['categories']} c ON e.category_id = c.id
            WHERE e.id = %d",
				$id
			)
		);

		if ( ! $equipment ) {
			return new WP_Error( 'not_found', 'Equipment not found', array( 'status' => 404 ) );
		}

		// Get current assignment.
		$equipment->current_assignment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, u.display_name as assignee_name
            FROM {$this->tables['assignments']} a
            LEFT JOIN {$wpdb->users} u ON a.assigned_to = u.ID
            WHERE a.equipment_id = %d AND a.returned_at IS NULL
            ORDER BY a.assigned_at DESC LIMIT 1",
				$id
			)
		);

		// Get recent maintenance.
		$equipment->recent_maintenance = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['maintenance']}
            WHERE equipment_id = %d
            ORDER BY completed_date DESC, scheduled_date DESC
            LIMIT 5",
				$id
			)
		);

		// Get checkout history.
		$equipment->checkout_history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ch.*, u.display_name as user_name
            FROM {$this->tables['checkouts']} ch
            LEFT JOIN {$wpdb->users} u ON ch.user_id = u.ID
            WHERE ch.equipment_id = %d
            ORDER BY ch.checkout_time DESC
            LIMIT 10",
				$id
			)
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'equipment' => $equipment,
			)
		);
	}

	/**
	 * Create equipment.
	 */
	public function rest_create_equipment( $request ) {
		global $wpdb;

		$data = array(
			'name'             => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'      => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'category_id'      => intval( $request->get_param( 'category_id' ) ) ?: null,
			'serial_number'    => sanitize_text_field( $request->get_param( 'serial_number' ) ),
			'model_number'     => sanitize_text_field( $request->get_param( 'model_number' ) ),
			'manufacturer'     => sanitize_text_field( $request->get_param( 'manufacturer' ) ),
			'barcode'          => sanitize_text_field( $request->get_param( 'barcode' ) ),
			'purchase_date'    => sanitize_text_field( $request->get_param( 'purchase_date' ) ),
			'purchase_price'   => floatval( $request->get_param( 'purchase_price' ) ),
			'current_value'    => floatval( $request->get_param( 'current_value' ) ) ?: floatval( $request->get_param( 'purchase_price' ) ),
			'warranty_expiry'  => sanitize_text_field( $request->get_param( 'warranty_expiry' ) ),
			'status'           => 'available',
			'condition_rating' => sanitize_text_field( $request->get_param( 'condition_rating' ) ) ?: 'good',
			'location'         => sanitize_text_field( $request->get_param( 'location' ) ),
			'specifications'   => wp_json_encode( $request->get_param( 'specifications' ) ),
			'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'created_by'       => get_current_user_id(),
		);

		// Generate QR code data.
		$data['qr_code'] = wp_json_encode(
			array(
				'type' => 'ict_equipment',
				'id'   => 'PENDING',
				'sn'   => $data['serial_number'],
			)
		);

		// Calculate next maintenance date.
		if ( $data['category_id'] ) {
			$category = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT maintenance_interval_days FROM {$this->tables['categories']} WHERE id = %d",
					$data['category_id']
				)
			);
			if ( $category ) {
				$data['next_maintenance_date'] = date( 'Y-m-d', strtotime( '+' . $category->maintenance_interval_days . ' days' ) );
			}
		}

		$wpdb->insert( $this->tables['equipment'], $data );
		$equipment_id = $wpdb->insert_id;

		// Update QR code with actual ID.
		$wpdb->update(
			$this->tables['equipment'],
			array(
				'qr_code' => wp_json_encode(
					array(
						'type' => 'ict_equipment',
						'id'   => $equipment_id,
						'sn'   => $data['serial_number'],
					)
				),
			),
			array( 'id' => $equipment_id )
		);

		return rest_ensure_response(
			array(
				'success'      => true,
				'equipment_id' => $equipment_id,
				'message'      => 'Equipment created successfully',
			)
		);
	}

	/**
	 * Update equipment.
	 */
	public function rest_update_equipment( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['equipment']} WHERE id = %d",
				$id
			)
		);

		if ( ! $equipment ) {
			return new WP_Error( 'not_found', 'Equipment not found', array( 'status' => 404 ) );
		}

		$fields = array(
			'name',
			'description',
			'category_id',
			'serial_number',
			'model_number',
			'manufacturer',
			'barcode',
			'purchase_date',
			'purchase_price',
			'current_value',
			'warranty_expiry',
			'status',
			'condition_rating',
			'location',
			'notes',
			'next_maintenance_date',
			'next_calibration_date',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		$specifications = $request->get_param( 'specifications' );
		if ( $specifications !== null ) {
			$data['specifications'] = wp_json_encode( $specifications );
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->tables['equipment'], $data, array( 'id' => $id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Equipment updated successfully',
			)
		);
	}

	/**
	 * Delete equipment.
	 */
	public function rest_delete_equipment( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->tables['equipment'], array( 'id' => $id ) );
		$wpdb->delete( $this->tables['assignments'], array( 'equipment_id' => $id ) );
		$wpdb->delete( $this->tables['maintenance'], array( 'equipment_id' => $id ) );
		$wpdb->delete( $this->tables['checkouts'], array( 'equipment_id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Equipment deleted successfully',
			)
		);
	}

	/**
	 * Assign equipment.
	 */
	public function rest_assign_equipment( $request ) {
		global $wpdb;

		$id              = intval( $request->get_param( 'id' ) );
		$assigned_to     = intval( $request->get_param( 'assigned_to' ) );
		$assigned_type   = sanitize_text_field( $request->get_param( 'assigned_type' ) ) ?: 'user';
		$project_id      = intval( $request->get_param( 'project_id' ) ) ?: null;
		$expected_return = sanitize_text_field( $request->get_param( 'expected_return' ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['equipment']} WHERE id = %d",
				$id
			)
		);

		if ( ! $equipment ) {
			return new WP_Error( 'not_found', 'Equipment not found', array( 'status' => 404 ) );
		}

		if ( $equipment->status !== 'available' ) {
			return new WP_Error( 'not_available', 'Equipment is not available for assignment', array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$this->tables['assignments'],
			array(
				'equipment_id'    => $id,
				'assigned_to'     => $assigned_to,
				'assigned_type'   => $assigned_type,
				'project_id'      => $project_id,
				'assigned_by'     => get_current_user_id(),
				'expected_return' => $expected_return ?: null,
				'notes'           => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			)
		);

		$wpdb->update(
			$this->tables['equipment'],
			array( 'status' => 'assigned' ),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Equipment assigned successfully',
			)
		);
	}

	/**
	 * Return equipment.
	 */
	public function rest_return_equipment( $request ) {
		global $wpdb;

		$id               = intval( $request->get_param( 'id' ) );
		$return_condition = sanitize_text_field( $request->get_param( 'return_condition' ) ) ?: 'good';

		$assignment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['assignments']}
            WHERE equipment_id = %d AND returned_at IS NULL
            ORDER BY assigned_at DESC LIMIT 1",
				$id
			)
		);

		if ( ! $assignment ) {
			return new WP_Error( 'not_assigned', 'Equipment is not currently assigned', array( 'status' => 400 ) );
		}

		$wpdb->update(
			$this->tables['assignments'],
			array(
				'returned_at'      => current_time( 'mysql' ),
				'return_condition' => $return_condition,
				'notes'            => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			),
			array( 'id' => $assignment->id )
		);

		$new_status = 'available';
		if ( $return_condition === 'damaged' || $return_condition === 'poor' ) {
			$new_status = 'maintenance';
		}

		$wpdb->update(
			$this->tables['equipment'],
			array(
				'status'           => $new_status,
				'condition_rating' => $return_condition === 'damaged' ? 'poor' : $return_condition,
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Equipment returned successfully',
			)
		);
	}

	/**
	 * Checkout equipment.
	 */
	public function rest_checkout_equipment( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['equipment']} WHERE id = %d",
				$id
			)
		);

		if ( ! $equipment || $equipment->status !== 'available' ) {
			return new WP_Error( 'not_available', 'Equipment is not available', array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$this->tables['checkouts'],
			array(
				'equipment_id'       => $id,
				'user_id'            => get_current_user_id(),
				'checkout_location'  => sanitize_text_field( $request->get_param( 'location' ) ),
				'checkout_condition' => $equipment->condition_rating,
				'purpose'            => sanitize_textarea_field( $request->get_param( 'purpose' ) ),
			)
		);

		$wpdb->update(
			$this->tables['equipment'],
			array( 'status' => 'assigned' ),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Equipment checked out successfully',
			)
		);
	}

	/**
	 * Checkin equipment.
	 */
	public function rest_checkin_equipment( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$checkout = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['checkouts']}
            WHERE equipment_id = %d AND checkin_time IS NULL
            ORDER BY checkout_time DESC LIMIT 1",
				$id
			)
		);

		if ( ! $checkout ) {
			return new WP_Error( 'not_checked_out', 'Equipment is not checked out', array( 'status' => 400 ) );
		}

		$checkin_condition = sanitize_text_field( $request->get_param( 'condition' ) ) ?: 'good';

		$wpdb->update(
			$this->tables['checkouts'],
			array(
				'checkin_time'      => current_time( 'mysql' ),
				'checkin_location'  => sanitize_text_field( $request->get_param( 'location' ) ),
				'checkin_condition' => $checkin_condition,
				'notes'             => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			),
			array( 'id' => $checkout->id )
		);

		$wpdb->update(
			$this->tables['equipment'],
			array(
				'status'           => $checkin_condition === 'damaged' ? 'maintenance' : 'available',
				'condition_rating' => $checkin_condition === 'damaged' ? 'poor' : $checkin_condition,
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Equipment checked in successfully',
			)
		);
	}

	/**
	 * Get maintenance records.
	 */
	public function rest_get_maintenance( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$maintenance = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name as performed_by_name
            FROM {$this->tables['maintenance']} m
            LEFT JOIN {$wpdb->users} u ON m.performed_by = u.ID
            WHERE m.equipment_id = %d
            ORDER BY m.completed_date DESC, m.scheduled_date DESC",
				$id
			)
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'maintenance' => $maintenance,
			)
		);
	}

	/**
	 * Create maintenance record.
	 */
	public function rest_create_maintenance( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->insert(
			$this->tables['maintenance'],
			array(
				'equipment_id'          => $id,
				'maintenance_type'      => sanitize_text_field( $request->get_param( 'type' ) ) ?: 'preventive',
				'description'           => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'performed_by'          => intval( $request->get_param( 'performed_by' ) ) ?: get_current_user_id(),
				'vendor'                => sanitize_text_field( $request->get_param( 'vendor' ) ),
				'cost'                  => floatval( $request->get_param( 'cost' ) ),
				'scheduled_date'        => sanitize_text_field( $request->get_param( 'scheduled_date' ) ),
				'completed_date'        => sanitize_text_field( $request->get_param( 'completed_date' ) ),
				'status'                => sanitize_text_field( $request->get_param( 'status' ) ) ?: 'scheduled',
				'findings'              => sanitize_textarea_field( $request->get_param( 'findings' ) ),
				'actions_taken'         => sanitize_textarea_field( $request->get_param( 'actions_taken' ) ),
				'parts_replaced'        => sanitize_textarea_field( $request->get_param( 'parts_replaced' ) ),
				'next_maintenance_date' => sanitize_text_field( $request->get_param( 'next_maintenance_date' ) ),
			)
		);

		// Update equipment maintenance dates if completed.
		if ( $request->get_param( 'status' ) === 'completed' ) {
			$update_data = array( 'last_maintenance_date' => $request->get_param( 'completed_date' ) ?: current_time( 'Y-m-d' ) );
			if ( $request->get_param( 'next_maintenance_date' ) ) {
				$update_data['next_maintenance_date'] = $request->get_param( 'next_maintenance_date' );
			}
			if ( $request->get_param( 'type' ) === 'calibration' ) {
				$update_data['last_calibration_date'] = $update_data['last_maintenance_date'];
				if ( $request->get_param( 'next_calibration_date' ) ) {
					$update_data['next_calibration_date'] = $request->get_param( 'next_calibration_date' );
				}
			}
			$wpdb->update( $this->tables['equipment'], $update_data, array( 'id' => $id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Maintenance record created successfully',
			)
		);
	}

	/**
	 * Get categories.
	 */
	public function rest_get_categories( $request ) {
		global $wpdb;

		$categories = $wpdb->get_results(
			"SELECT c.*, (SELECT COUNT(*) FROM {$this->tables['equipment']} WHERE category_id = c.id) as equipment_count
            FROM {$this->tables['categories']} c
            ORDER BY c.name ASC"
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Create category.
	 */
	public function rest_create_category( $request ) {
		global $wpdb;

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_title( $request->get_param( 'slug' ) ?: $name );

		$wpdb->insert(
			$this->tables['categories'],
			array(
				'name'                      => $name,
				'slug'                      => $slug,
				'description'               => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'parent_id'                 => intval( $request->get_param( 'parent_id' ) ) ?: null,
				'depreciation_years'        => intval( $request->get_param( 'depreciation_years' ) ) ?: 5,
				'maintenance_interval_days' => intval( $request->get_param( 'maintenance_interval_days' ) ) ?: 365,
			)
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'category_id' => $wpdb->insert_id,
				'message'     => 'Category created successfully',
			)
		);
	}

	/**
	 * Lookup equipment by barcode/QR.
	 */
	public function rest_lookup_equipment( $request ) {
		global $wpdb;

		$code = sanitize_text_field( $request->get_param( 'code' ) );

		$equipment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['equipment']}
            WHERE barcode = %s OR serial_number = %s OR qr_code LIKE %s",
				$code,
				$code,
				'%' . $wpdb->esc_like( $code ) . '%'
			)
		);

		if ( ! $equipment ) {
			return new WP_Error( 'not_found', 'Equipment not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'equipment' => $equipment,
			)
		);
	}

	/**
	 * Depreciation report.
	 */
	public function rest_depreciation_report( $request ) {
		global $wpdb;

		$equipment = $wpdb->get_results(
			"SELECT e.*, c.depreciation_years
            FROM {$this->tables['equipment']} e
            LEFT JOIN {$this->tables['categories']} c ON e.category_id = c.id
            WHERE e.purchase_date IS NOT NULL AND e.purchase_price > 0
            ORDER BY e.name ASC"
		);

		$report             = array();
		$total_purchase     = 0;
		$total_current      = 0;
		$total_depreciation = 0;

		foreach ( $equipment as $item ) {
			$years     = $item->depreciation_years ?: 5;
			$age_days  = ( time() - strtotime( $item->purchase_date ) ) / 86400;
			$age_years = $age_days / 365;

			$annual_depreciation = $item->purchase_price / $years;
			$accumulated         = min( $item->purchase_price, $annual_depreciation * $age_years );
			$book_value          = max( 0, $item->purchase_price - $accumulated );

			$report[] = array(
				'id'                       => $item->id,
				'name'                     => $item->name,
				'purchase_date'            => $item->purchase_date,
				'purchase_price'           => floatval( $item->purchase_price ),
				'depreciation_years'       => $years,
				'age_years'                => round( $age_years, 2 ),
				'annual_depreciation'      => round( $annual_depreciation, 2 ),
				'accumulated_depreciation' => round( $accumulated, 2 ),
				'book_value'               => round( $book_value, 2 ),
				'fully_depreciated'        => $age_years >= $years,
			);

			$total_purchase     += $item->purchase_price;
			$total_current      += $book_value;
			$total_depreciation += $accumulated;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'report'  => $report,
				'summary' => array(
					'total_purchase_value' => round( $total_purchase, 2 ),
					'total_current_value'  => round( $total_current, 2 ),
					'total_depreciation'   => round( $total_depreciation, 2 ),
					'equipment_count'      => count( $report ),
				),
			)
		);
	}

	/**
	 * Utilization report.
	 */
	public function rest_utilization_report( $request ) {
		global $wpdb;

		$days = intval( $request->get_param( 'days' ) ) ?: 30;

		$equipment = $wpdb->get_results(
			"SELECT e.id, e.name, e.status,
                (SELECT COUNT(*) FROM {$this->tables['checkouts']}
                 WHERE equipment_id = e.id
                 AND checkout_time >= DATE_SUB(NOW(), INTERVAL {$days} DAY)) as checkout_count,
                (SELECT SUM(TIMESTAMPDIFF(HOUR, checkout_time, COALESCE(checkin_time, NOW())))
                 FROM {$this->tables['checkouts']}
                 WHERE equipment_id = e.id
                 AND checkout_time >= DATE_SUB(NOW(), INTERVAL {$days} DAY)) as hours_used
            FROM {$this->tables['equipment']} e
            ORDER BY hours_used DESC"
		);

		$total_hours = $days * 24;

		foreach ( $equipment as &$item ) {
			$item->hours_used       = floatval( $item->hours_used ) ?: 0;
			$item->utilization_rate = round( ( $item->hours_used / $total_hours ) * 100, 2 );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'report'  => $equipment,
				'period'  => array(
					'days'        => $days,
					'total_hours' => $total_hours,
				),
			)
		);
	}

	/**
	 * Check maintenance schedules and send notifications.
	 */
	public function check_maintenance_schedules() {
		global $wpdb;

		// Find equipment due for maintenance in next 7 days.
		$due_soon = $wpdb->get_results(
			"SELECT * FROM {$this->tables['equipment']}
            WHERE next_maintenance_date IS NOT NULL
            AND next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND status != 'retired'"
		);

		foreach ( $due_soon as $equipment ) {
			do_action( 'ict_equipment_maintenance_due', $equipment );
		}

		// Find overdue calibrations.
		$overdue_calibration = $wpdb->get_results(
			"SELECT * FROM {$this->tables['equipment']}
            WHERE next_calibration_date IS NOT NULL
            AND next_calibration_date < CURDATE()
            AND status != 'retired'"
		);

		foreach ( $overdue_calibration as $equipment ) {
			do_action( 'ict_equipment_calibration_overdue', $equipment );
		}
	}
}
