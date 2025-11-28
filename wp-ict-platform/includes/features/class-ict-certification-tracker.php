<?php
/**
 * Certification & Compliance Tracker
 *
 * Track technician certifications and compliance including:
 * - Certification types and requirements
 * - Expiration tracking and renewal reminders
 * - Training record management
 * - Compliance verification for projects
 * - Certification upload and verification
 * - Skills matrix generation
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Certification_Tracker
 */
class ICT_Certification_Tracker {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Certification_Tracker
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
	 * @return ICT_Certification_Tracker
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
			'cert_types'     => $wpdb->prefix . 'ict_certification_types',
			'certifications' => $wpdb->prefix . 'ict_certifications',
			'training'       => $wpdb->prefix . 'ict_training_records',
			'requirements'   => $wpdb->prefix . 'ict_project_requirements',
			'skills'         => $wpdb->prefix . 'ict_skill_matrix',
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
		add_action( 'ict_check_certification_expirations', array( $this, 'check_expirations' ) );

		if ( ! wp_next_scheduled( 'ict_check_certification_expirations' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_check_certification_expirations' );
		}
	}

	/**
	 * Create database tables.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Certification types.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['cert_types']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            code varchar(50) NOT NULL,
            category varchar(100) DEFAULT NULL,
            issuing_body varchar(200) DEFAULT NULL,
            description text,
            requirements text,
            validity_months int(11) DEFAULT 12,
            renewal_process text,
            is_mandatory tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY category (category)
        ) $charset_collate;";

		// User certifications.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['certifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            cert_type_id bigint(20) unsigned NOT NULL,
            certificate_number varchar(100) DEFAULT NULL,
            issue_date date NOT NULL,
            expiry_date date DEFAULT NULL,
            status enum('active','expired','suspended','revoked','pending') DEFAULT 'active',
            issuing_authority varchar(200) DEFAULT NULL,
            verification_url varchar(500) DEFAULT NULL,
            document_path varchar(500) DEFAULT NULL,
            notes text,
            verified_by bigint(20) unsigned DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            reminder_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY cert_type_id (cert_type_id),
            KEY expiry_date (expiry_date),
            KEY status (status)
        ) $charset_collate;";

		// Training records.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['training']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            training_name varchar(200) NOT NULL,
            provider varchar(200) DEFAULT NULL,
            training_type enum('online','classroom','on_the_job','workshop','conference') DEFAULT 'classroom',
            cert_type_id bigint(20) unsigned DEFAULT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            hours decimal(6,2) DEFAULT NULL,
            status enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
            score decimal(5,2) DEFAULT NULL,
            passed tinyint(1) DEFAULT NULL,
            certificate_path varchar(500) DEFAULT NULL,
            cost decimal(12,2) DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY cert_type_id (cert_type_id),
            KEY status (status)
        ) $charset_collate;";

		// Project certification requirements.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['requirements']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            cert_type_id bigint(20) unsigned NOT NULL,
            required_count int(11) DEFAULT 1,
            is_mandatory tinyint(1) DEFAULT 1,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY cert_type_id (cert_type_id)
        ) $charset_collate;";

		// Skills matrix.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['skills']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            skill_name varchar(200) NOT NULL,
            skill_category varchar(100) DEFAULT NULL,
            proficiency_level enum('beginner','intermediate','advanced','expert') DEFAULT 'beginner',
            years_experience decimal(4,1) DEFAULT NULL,
            last_used_date date DEFAULT NULL,
            self_assessment tinyint(2) DEFAULT NULL,
            manager_assessment tinyint(2) DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_skill (user_id, skill_name),
            KEY skill_category (skill_category)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		$this->seed_default_cert_types();
	}

	/**
	 * Seed default certification types.
	 */
	private function seed_default_cert_types() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables['cert_types']}" );
		if ( $count > 0 ) {
			return;
		}

		$types = array(
			array( 'Electrical License', 'ELEC-LIC', 'Electrical', 'State Licensing Board', 24, 1 ),
			array( 'OSHA 10-Hour', 'OSHA-10', 'Safety', 'OSHA', 60, 1 ),
			array( 'OSHA 30-Hour', 'OSHA-30', 'Safety', 'OSHA', 60, 0 ),
			array( 'First Aid/CPR', 'FA-CPR', 'Safety', 'Red Cross/AHA', 24, 1 ),
			array( 'Forklift Operator', 'FORK-OP', 'Equipment', 'OSHA', 36, 0 ),
			array( 'Aerial Lift', 'AERIAL', 'Equipment', 'Manufacturer', 36, 0 ),
			array( 'Confined Space Entry', 'CONF-SPACE', 'Safety', 'OSHA', 12, 0 ),
			array( 'Fall Protection', 'FALL-PROT', 'Safety', 'OSHA', 12, 0 ),
			array( 'Arc Flash Safety', 'ARC-FLASH', 'Electrical', 'NFPA', 12, 0 ),
			array( 'Network Cabling (BICSI)', 'BICSI', 'Data/Comm', 'BICSI', 36, 0 ),
			array( 'Fiber Optic (FOA)', 'FOA-CFOT', 'Data/Comm', 'FOA', 36, 0 ),
			array( 'Project Management (PMP)', 'PMP', 'Management', 'PMI', 36, 0 ),
		);

		foreach ( $types as $type ) {
			$wpdb->insert(
				$this->tables['cert_types'],
				array(
					'name'            => $type[0],
					'code'            => $type[1],
					'category'        => $type[2],
					'issuing_body'    => $type[3],
					'validity_months' => $type[4],
					'is_mandatory'    => $type[5],
				)
			);
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$namespace = 'ict/v1';

		// Certification types.
		register_rest_route(
			$namespace,
			'/certification-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_cert_types' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_cert_type' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// User certifications.
		register_rest_route(
			$namespace,
			'/certifications',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_certifications' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_certification' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/certifications/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_certification' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_certification' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// User certifications.
		register_rest_route(
			$namespace,
			'/users/(?P<user_id>\d+)/certifications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_user_certifications' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Training records.
		register_rest_route(
			$namespace,
			'/training',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_training' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_training' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Expiring certifications.
		register_rest_route(
			$namespace,
			'/certifications/expiring',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_expiring' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Project requirements.
		register_rest_route(
			$namespace,
			'/projects/(?P<project_id>\d+)/cert-requirements',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_project_requirements' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_set_project_requirements' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Compliance check.
		register_rest_route(
			$namespace,
			'/projects/(?P<project_id>\d+)/compliance-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_check_compliance' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Skills matrix.
		register_rest_route(
			$namespace,
			'/skills-matrix',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_skills_matrix' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/users/(?P<user_id>\d+)/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_user_skills' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_update_user_skills' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// Dashboard stats.
		register_rest_route(
			$namespace,
			'/certifications/stats',
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
		return current_user_can( 'edit_users' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get certification types.
	 */
	public function rest_get_cert_types( $request ) {
		global $wpdb;

		$active_only = $request->get_param( 'active_only' );
		$category    = $request->get_param( 'category' );

		$sql  = "SELECT * FROM {$this->tables['cert_types']} WHERE 1=1";
		$args = array();

		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}

		if ( $category ) {
			$sql   .= ' AND category = %s';
			$args[] = $category;
		}

		$sql .= ' ORDER BY category, name';

		$types = ! empty( $args )
			? $wpdb->get_results( $wpdb->prepare( $sql, $args ) )
			: $wpdb->get_results( $sql );

		// Get categories.
		$categories = $wpdb->get_col(
			"SELECT DISTINCT category FROM {$this->tables['cert_types']} WHERE category IS NOT NULL ORDER BY category"
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'types'      => $types,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Create certification type.
	 */
	public function rest_create_cert_type( $request ) {
		global $wpdb;

		$wpdb->insert(
			$this->tables['cert_types'],
			array(
				'name'            => sanitize_text_field( $request->get_param( 'name' ) ),
				'code'            => sanitize_text_field( $request->get_param( 'code' ) ),
				'category'        => sanitize_text_field( $request->get_param( 'category' ) ),
				'issuing_body'    => sanitize_text_field( $request->get_param( 'issuing_body' ) ),
				'description'     => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'requirements'    => sanitize_textarea_field( $request->get_param( 'requirements' ) ),
				'validity_months' => intval( $request->get_param( 'validity_months' ) ) ?: 12,
				'renewal_process' => sanitize_textarea_field( $request->get_param( 'renewal_process' ) ),
				'is_mandatory'    => $request->get_param( 'is_mandatory' ) ? 1 : 0,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'type_id' => $wpdb->insert_id,
				'message' => 'Certification type created successfully',
			)
		);
	}

	/**
	 * Get certifications.
	 */
	public function rest_get_certifications( $request ) {
		global $wpdb;

		$status       = $request->get_param( 'status' );
		$cert_type_id = $request->get_param( 'cert_type_id' );
		$page         = max( 1, intval( $request->get_param( 'page' ) ) );
		$per_page     = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

		$sql  = "SELECT c.*, ct.name as cert_type_name, ct.code as cert_code, ct.category,
                u.display_name as user_name
                FROM {$this->tables['certifications']} c
                LEFT JOIN {$this->tables['cert_types']} ct ON c.cert_type_id = ct.id
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE 1=1";
		$args = array();

		if ( $status ) {
			$sql   .= ' AND c.status = %s';
			$args[] = $status;
		}

		if ( $cert_type_id ) {
			$sql   .= ' AND c.cert_type_id = %d';
			$args[] = $cert_type_id;
		}

		$count_sql = str_replace(
			'SELECT c.*, ct.name as cert_type_name, ct.code as cert_code, ct.category,
                u.display_name as user_name',
			'SELECT COUNT(*)',
			$sql
		);
		$total     = ! empty( $args ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql );

		$sql   .= ' ORDER BY c.expiry_date ASC LIMIT %d OFFSET %d';
		$args[] = $per_page;
		$args[] = ( $page - 1 ) * $per_page;

		$certifications = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		foreach ( $certifications as &$cert ) {
			if ( $cert->expiry_date ) {
				$cert->days_until_expiry = floor( ( strtotime( $cert->expiry_date ) - time() ) / 86400 );
			}
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'certifications' => $certifications,
				'total'          => intval( $total ),
				'pages'          => ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Create certification.
	 */
	public function rest_create_certification( $request ) {
		global $wpdb;

		$user_id      = intval( $request->get_param( 'user_id' ) );
		$cert_type_id = intval( $request->get_param( 'cert_type_id' ) );

		// Get cert type for default expiry.
		$cert_type = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['cert_types']} WHERE id = %d",
				$cert_type_id
			)
		);

		$issue_date  = sanitize_text_field( $request->get_param( 'issue_date' ) ) ?: current_time( 'Y-m-d' );
		$expiry_date = $request->get_param( 'expiry_date' );

		if ( ! $expiry_date && $cert_type && $cert_type->validity_months ) {
			$expiry_date = date( 'Y-m-d', strtotime( $issue_date . ' + ' . $cert_type->validity_months . ' months' ) );
		}

		$wpdb->insert(
			$this->tables['certifications'],
			array(
				'user_id'            => $user_id,
				'cert_type_id'       => $cert_type_id,
				'certificate_number' => sanitize_text_field( $request->get_param( 'certificate_number' ) ),
				'issue_date'         => $issue_date,
				'expiry_date'        => $expiry_date,
				'issuing_authority'  => sanitize_text_field( $request->get_param( 'issuing_authority' ) ),
				'verification_url'   => esc_url_raw( $request->get_param( 'verification_url' ) ),
				'document_path'      => sanitize_text_field( $request->get_param( 'document_path' ) ),
				'notes'              => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			)
		);

		return rest_ensure_response(
			array(
				'success'          => true,
				'certification_id' => $wpdb->insert_id,
				'message'          => 'Certification added successfully',
			)
		);
	}

	/**
	 * Update certification.
	 */
	public function rest_update_certification( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$fields = array(
			'certificate_number',
			'issue_date',
			'expiry_date',
			'status',
			'issuing_authority',
			'verification_url',
			'document_path',
			'notes',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		// Handle verification.
		if ( $request->get_param( 'verify' ) && current_user_can( 'edit_users' ) ) {
			$data['verified_by'] = get_current_user_id();
			$data['verified_at'] = current_time( 'mysql' );
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->tables['certifications'], $data, array( 'id' => $id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Certification updated successfully',
			)
		);
	}

	/**
	 * Delete certification.
	 */
	public function rest_delete_certification( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->tables['certifications'], array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Certification deleted successfully',
			)
		);
	}

	/**
	 * Get user certifications.
	 */
	public function rest_get_user_certifications( $request ) {
		global $wpdb;

		$user_id = intval( $request->get_param( 'user_id' ) );

		$certifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, ct.name as cert_type_name, ct.code as cert_code, ct.category, ct.is_mandatory
            FROM {$this->tables['certifications']} c
            LEFT JOIN {$this->tables['cert_types']} ct ON c.cert_type_id = ct.id
            WHERE c.user_id = %d
            ORDER BY ct.is_mandatory DESC, c.expiry_date ASC",
				$user_id
			)
		);

		foreach ( $certifications as &$cert ) {
			if ( $cert->expiry_date ) {
				$cert->days_until_expiry = floor( ( strtotime( $cert->expiry_date ) - time() ) / 86400 );
				$cert->is_expiring_soon  = $cert->days_until_expiry <= 30 && $cert->days_until_expiry > 0;
			}
		}

		// Get missing mandatory certifications.
		$mandatory = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ct.* FROM {$this->tables['cert_types']} ct
            WHERE ct.is_mandatory = 1 AND ct.is_active = 1
            AND ct.id NOT IN (
                SELECT cert_type_id FROM {$this->tables['certifications']}
                WHERE user_id = %d AND status = 'active'
            )",
				$user_id
			)
		);

		return rest_ensure_response(
			array(
				'success'        => true,
				'certifications' => $certifications,
				'missing'        => $mandatory,
			)
		);
	}

	/**
	 * Get expiring certifications.
	 */
	public function rest_get_expiring( $request ) {
		global $wpdb;

		$days = intval( $request->get_param( 'days' ) ) ?: 30;

		$certifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, ct.name as cert_type_name, ct.code as cert_code,
                u.display_name as user_name, u.user_email
            FROM {$this->tables['certifications']} c
            LEFT JOIN {$this->tables['cert_types']} ct ON c.cert_type_id = ct.id
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE c.status = 'active'
            AND c.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
            ORDER BY c.expiry_date ASC",
				$days
			)
		);

		foreach ( $certifications as &$cert ) {
			$cert->days_until_expiry = floor( ( strtotime( $cert->expiry_date ) - time() ) / 86400 );
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'certifications' => $certifications,
				'count'          => count( $certifications ),
			)
		);
	}

	/**
	 * Get training records.
	 */
	public function rest_get_training( $request ) {
		global $wpdb;

		$user_id = $request->get_param( 'user_id' );
		$status  = $request->get_param( 'status' );

		$sql  = "SELECT t.*, ct.name as cert_type_name, u.display_name as user_name
                FROM {$this->tables['training']} t
                LEFT JOIN {$this->tables['cert_types']} ct ON t.cert_type_id = ct.id
                LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                WHERE 1=1";
		$args = array();

		if ( $user_id ) {
			$sql   .= ' AND t.user_id = %d';
			$args[] = $user_id;
		}

		if ( $status ) {
			$sql   .= ' AND t.status = %s';
			$args[] = $status;
		}

		$sql .= ' ORDER BY t.start_date DESC';

		$training = ! empty( $args )
			? $wpdb->get_results( $wpdb->prepare( $sql, $args ) )
			: $wpdb->get_results( $sql );

		return rest_ensure_response(
			array(
				'success'  => true,
				'training' => $training,
			)
		);
	}

	/**
	 * Create training record.
	 */
	public function rest_create_training( $request ) {
		global $wpdb;

		$wpdb->insert(
			$this->tables['training'],
			array(
				'user_id'       => intval( $request->get_param( 'user_id' ) ),
				'training_name' => sanitize_text_field( $request->get_param( 'training_name' ) ),
				'provider'      => sanitize_text_field( $request->get_param( 'provider' ) ),
				'training_type' => sanitize_text_field( $request->get_param( 'training_type' ) ) ?: 'classroom',
				'cert_type_id'  => intval( $request->get_param( 'cert_type_id' ) ) ?: null,
				'start_date'    => sanitize_text_field( $request->get_param( 'start_date' ) ),
				'end_date'      => sanitize_text_field( $request->get_param( 'end_date' ) ),
				'hours'         => floatval( $request->get_param( 'hours' ) ),
				'status'        => sanitize_text_field( $request->get_param( 'status' ) ) ?: 'scheduled',
				'cost'          => floatval( $request->get_param( 'cost' ) ),
				'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			)
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'training_id' => $wpdb->insert_id,
				'message'     => 'Training record created successfully',
			)
		);
	}

	/**
	 * Get project certification requirements.
	 */
	public function rest_get_project_requirements( $request ) {
		global $wpdb;

		$project_id = intval( $request->get_param( 'project_id' ) );

		$requirements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, ct.name as cert_type_name, ct.code as cert_code, ct.category
            FROM {$this->tables['requirements']} r
            LEFT JOIN {$this->tables['cert_types']} ct ON r.cert_type_id = ct.id
            WHERE r.project_id = %d
            ORDER BY r.is_mandatory DESC, ct.name",
				$project_id
			)
		);

		return rest_ensure_response(
			array(
				'success'      => true,
				'requirements' => $requirements,
			)
		);
	}

	/**
	 * Set project requirements.
	 */
	public function rest_set_project_requirements( $request ) {
		global $wpdb;

		$project_id   = intval( $request->get_param( 'project_id' ) );
		$requirements = $request->get_param( 'requirements' );

		// Clear existing.
		$wpdb->delete( $this->tables['requirements'], array( 'project_id' => $project_id ) );

		// Insert new.
		foreach ( $requirements as $req ) {
			$wpdb->insert(
				$this->tables['requirements'],
				array(
					'project_id'     => $project_id,
					'cert_type_id'   => intval( $req['cert_type_id'] ),
					'required_count' => intval( $req['required_count'] ) ?: 1,
					'is_mandatory'   => isset( $req['is_mandatory'] ) ? ( $req['is_mandatory'] ? 1 : 0 ) : 1,
					'notes'          => sanitize_textarea_field( $req['notes'] ?? '' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Requirements updated successfully',
			)
		);
	}

	/**
	 * Check project compliance.
	 */
	public function rest_check_compliance( $request ) {
		global $wpdb;

		$project_id = intval( $request->get_param( 'project_id' ) );

		// Get requirements.
		$requirements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, ct.name as cert_type_name, ct.code as cert_code
            FROM {$this->tables['requirements']} r
            LEFT JOIN {$this->tables['cert_types']} ct ON r.cert_type_id = ct.id
            WHERE r.project_id = %d",
				$project_id
			)
		);

		// Get assigned team members (would need project resources table).
		$team_members = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$wpdb->prefix}ict_project_resources WHERE project_id = %d",
				$project_id
			)
		);

		$compliance   = array();
		$is_compliant = true;

		foreach ( $requirements as $req ) {
			// Count team members with this certification.
			if ( ! empty( $team_members ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $team_members ), '%d' ) );
				$args         = array_merge( array( $req->cert_type_id ), $team_members );

				$certified_count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT user_id) FROM {$this->tables['certifications']}
                    WHERE cert_type_id = %d AND status = 'active'
                    AND user_id IN ($placeholders)",
						$args
					)
				);
			} else {
				$certified_count = 0;
			}

			$meets_requirement = $certified_count >= $req->required_count;

			$compliance[] = array(
				'cert_type_id'      => $req->cert_type_id,
				'cert_type_name'    => $req->cert_type_name,
				'cert_code'         => $req->cert_code,
				'required_count'    => $req->required_count,
				'certified_count'   => intval( $certified_count ),
				'is_mandatory'      => (bool) $req->is_mandatory,
				'meets_requirement' => $meets_requirement,
			);

			if ( $req->is_mandatory && ! $meets_requirement ) {
				$is_compliant = false;
			}
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'is_compliant' => $is_compliant,
				'compliance'   => $compliance,
				'team_size'    => count( $team_members ),
			)
		);
	}

	/**
	 * Get skills matrix.
	 */
	public function rest_get_skills_matrix( $request ) {
		global $wpdb;

		$category = $request->get_param( 'category' );

		$sql  = "SELECT s.*, u.display_name as user_name
                FROM {$this->tables['skills']} s
                LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                WHERE 1=1";
		$args = array();

		if ( $category ) {
			$sql   .= ' AND s.skill_category = %s';
			$args[] = $category;
		}

		$sql .= ' ORDER BY u.display_name, s.skill_category, s.skill_name';

		$skills = ! empty( $args )
			? $wpdb->get_results( $wpdb->prepare( $sql, $args ) )
			: $wpdb->get_results( $sql );

		// Get categories.
		$categories = $wpdb->get_col(
			"SELECT DISTINCT skill_category FROM {$this->tables['skills']} WHERE skill_category IS NOT NULL ORDER BY skill_category"
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'skills'     => $skills,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Get user skills.
	 */
	public function rest_get_user_skills( $request ) {
		global $wpdb;

		$user_id = intval( $request->get_param( 'user_id' ) );

		$skills = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['skills']} WHERE user_id = %d ORDER BY skill_category, skill_name",
				$user_id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'skills'  => $skills,
			)
		);
	}

	/**
	 * Update user skills.
	 */
	public function rest_update_user_skills( $request ) {
		global $wpdb;

		$user_id = intval( $request->get_param( 'user_id' ) );
		$skills  = $request->get_param( 'skills' );

		foreach ( $skills as $skill ) {
			$skill_name = sanitize_text_field( $skill['skill_name'] );

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->tables['skills']} WHERE user_id = %d AND skill_name = %s",
					$user_id,
					$skill_name
				)
			);

			$data = array(
				'user_id'            => $user_id,
				'skill_name'         => $skill_name,
				'skill_category'     => sanitize_text_field( $skill['skill_category'] ?? '' ),
				'proficiency_level'  => sanitize_text_field( $skill['proficiency_level'] ?? 'beginner' ),
				'years_experience'   => floatval( $skill['years_experience'] ?? 0 ),
				'self_assessment'    => intval( $skill['self_assessment'] ?? null ),
				'manager_assessment' => intval( $skill['manager_assessment'] ?? null ),
				'notes'              => sanitize_textarea_field( $skill['notes'] ?? '' ),
			);

			if ( $existing ) {
				$wpdb->update( $this->tables['skills'], $data, array( 'id' => $existing ) );
			} else {
				$wpdb->insert( $this->tables['skills'], $data );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Skills updated successfully',
			)
		);
	}

	/**
	 * Get stats.
	 */
	public function rest_get_stats( $request ) {
		global $wpdb;

		$stats = array(
			'total_certifications'   => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['certifications']}"
			),
			'active_certifications'  => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['certifications']} WHERE status = 'active'"
			),
			'expiring_30_days'       => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['certifications']}
                WHERE status = 'active'
                AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
			),
			'expired'                => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['certifications']} WHERE status = 'expired'"
			),
			'certified_users'        => $wpdb->get_var(
				"SELECT COUNT(DISTINCT user_id) FROM {$this->tables['certifications']} WHERE status = 'active'"
			),
			'training_completed_ytd' => $wpdb->get_var(
				"SELECT COUNT(*) FROM {$this->tables['training']}
                WHERE status = 'completed' AND YEAR(end_date) = YEAR(CURDATE())"
			),
			'training_hours_ytd'     => $wpdb->get_var(
				"SELECT COALESCE(SUM(hours), 0) FROM {$this->tables['training']}
                WHERE status = 'completed' AND YEAR(end_date) = YEAR(CURDATE())"
			),
		);

		// By category breakdown.
		$stats['by_category'] = $wpdb->get_results(
			"SELECT ct.category, COUNT(*) as count
            FROM {$this->tables['certifications']} c
            LEFT JOIN {$this->tables['cert_types']} ct ON c.cert_type_id = ct.id
            WHERE c.status = 'active'
            GROUP BY ct.category"
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Check certification expirations and send reminders.
	 */
	public function check_expirations() {
		global $wpdb;

		// Update expired certifications.
		$wpdb->query(
			"UPDATE {$this->tables['certifications']}
            SET status = 'expired'
            WHERE status = 'active' AND expiry_date < CURDATE()"
		);

		// Get certifications needing reminders (30, 14, 7 days before).
		$intervals = array( 30, 14, 7 );

		foreach ( $intervals as $days ) {
			$expiring = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.*, ct.name as cert_type_name, u.display_name, u.user_email
                FROM {$this->tables['certifications']} c
                LEFT JOIN {$this->tables['cert_types']} ct ON c.cert_type_id = ct.id
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.status = 'active'
                AND c.expiry_date = DATE_ADD(CURDATE(), INTERVAL %d DAY)",
					$days
				)
			);

			foreach ( $expiring as $cert ) {
				do_action( 'ict_certification_expiring', $cert, $days );
			}
		}
	}
}
