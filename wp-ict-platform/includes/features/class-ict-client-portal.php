<?php
/**
 * Client Portal
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Client_Portal {

	private static $instance = null;
	private $access_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->access_table = $wpdb->prefix . 'ict_client_access';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'init', array( $this, 'register_client_role' ) );
	}

	public function register_routes() {
		// Client-facing endpoints
		register_rest_route(
			'ict/v1',
			'/portal/projects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_projects' ),
				'permission_callback' => array( $this, 'check_client_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/projects/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_details' ),
				'permission_callback' => array( $this, 'check_project_access' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/projects/(?P<id>\d+)/updates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_updates' ),
				'permission_callback' => array( $this, 'check_project_access' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/projects/(?P<id>\d+)/documents',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_documents' ),
				'permission_callback' => array( $this, 'check_project_access' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/projects/(?P<id>\d+)/invoices',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_invoices' ),
				'permission_callback' => array( $this, 'check_project_access' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/projects/(?P<id>\d+)/request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_request' ),
				'permission_callback' => array( $this, 'check_project_access' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/requests',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_client_requests' ),
				'permission_callback' => array( $this, 'check_client_permission' ),
			)
		);

		// Admin endpoints
		register_rest_route(
			'ict/v1',
			'/portal/access',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_access' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'grant_access' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/access/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'revoke_access' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/invite',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'invite_client' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/portal/projects/(?P<id>\d+)/post-update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_update' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	public function check_client_permission() {
		return is_user_logged_in() && ( current_user_can( 'ict_client' ) || current_user_can( 'manage_options' ) );
	}

	public function check_project_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$project_id = (int) $request->get_param( 'id' );
		return $this->user_has_project_access( get_current_user_id(), $project_id );
	}

	public function check_admin_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	private function user_has_project_access( $user_id, $project_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$this->access_table}
             WHERE user_id = %d AND project_id = %d AND is_active = 1
             AND (expires_at IS NULL OR expires_at > NOW())",
				$user_id,
				$project_id
			)
		);
	}

	public function register_client_role() {
		if ( ! get_role( 'ict_client' ) ) {
			add_role(
				'ict_client',
				'ICT Client',
				array(
					'read'       => true,
					'ict_client' => true,
				)
			);
		}
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->access_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned NOT NULL,
            client_id bigint(20) unsigned,
            access_level enum('view','comment','full') DEFAULT 'view',
            can_view_documents tinyint(1) DEFAULT 1,
            can_view_invoices tinyint(1) DEFAULT 1,
            can_view_schedule tinyint(1) DEFAULT 1,
            can_submit_requests tinyint(1) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            expires_at datetime,
            granted_by bigint(20) unsigned NOT NULL,
            granted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_project (user_id, project_id),
            KEY project_id (project_id),
            KEY client_id (client_id)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ict_project_updates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            content text NOT NULL,
            update_type enum('progress','milestone','issue','general') DEFAULT 'general',
            is_public tinyint(1) DEFAULT 1,
            attachment_url varchar(500),
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY is_public (is_public)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ict_client_requests (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            request_type enum('change','question','issue','other') DEFAULT 'other',
            subject varchar(255) NOT NULL,
            description text NOT NULL,
            priority enum('low','medium','high') DEFAULT 'medium',
            status enum('open','in_progress','resolved','closed') DEFAULT 'open',
            response text,
            responded_by bigint(20) unsigned,
            responded_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	public function get_client_projects( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.name, p.project_number, p.status, p.start_date, p.end_date,
                    p.progress_percent, a.access_level, a.can_view_documents,
                    a.can_view_invoices, a.can_view_schedule, a.can_submit_requests
             FROM {$wpdb->prefix}ict_projects p
             JOIN {$this->access_table} a ON p.id = a.project_id
             WHERE a.user_id = %d AND a.is_active = 1
             AND (a.expires_at IS NULL OR a.expires_at > NOW())
             ORDER BY p.created_at DESC",
				$user_id
			)
		);

		return rest_ensure_response( $projects );
	}

	public function get_project_details( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.id, p.name, p.project_number, p.description, p.status,
                    p.start_date, p.end_date, p.progress_percent,
                    pm.display_name as project_manager
             FROM {$wpdb->prefix}ict_projects p
             LEFT JOIN {$wpdb->users} pm ON p.project_manager_id = pm.ID
             WHERE p.id = %d",
				$project_id
			)
		);

		if ( ! $project ) {
			return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}

		// Get milestones
		$project->milestones = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, status, due_date, progress_percent
             FROM {$wpdb->prefix}ict_project_milestones
             WHERE project_id = %d
             ORDER BY sort_order",
				$project_id
			)
		);

		// Get recent updates
		$project->recent_updates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT title, content, update_type, created_at
             FROM {$wpdb->prefix}ict_project_updates
             WHERE project_id = %d AND is_public = 1
             ORDER BY created_at DESC LIMIT 5",
				$project_id
			)
		);

		return rest_ensure_response( $project );
	}

	public function get_project_updates( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'id' );

		$updates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.*, a.display_name as author_name
             FROM {$wpdb->prefix}ict_project_updates u
             LEFT JOIN {$wpdb->users} a ON u.created_by = a.ID
             WHERE u.project_id = %d AND u.is_public = 1
             ORDER BY u.created_at DESC",
				$project_id
			)
		);

		return rest_ensure_response( $updates );
	}

	public function get_project_documents( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();

		// Check permission
		$access = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->access_table} WHERE user_id = %d AND project_id = %d",
				$user_id,
				$project_id
			)
		);

		if ( ! $access || ! $access->can_view_documents ) {
			return new WP_Error( 'forbidden', 'No document access', array( 'status' => 403 ) );
		}

		$documents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, file_type, file_size, category, created_at
             FROM {$wpdb->prefix}ict_documents
             WHERE project_id = %d AND is_public = 1 AND is_latest = 1
             ORDER BY category, name",
				$project_id
			)
		);

		return rest_ensure_response( $documents );
	}

	public function get_project_invoices( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();

		$access = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->access_table} WHERE user_id = %d AND project_id = %d",
				$user_id,
				$project_id
			)
		);

		if ( ! $access || ! $access->can_view_invoices ) {
			return new WP_Error( 'forbidden', 'No invoice access', array( 'status' => 403 ) );
		}

		$invoices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT invoice_number, amount, status, due_date, paid_date
             FROM {$wpdb->prefix}ict_invoices
             WHERE project_id = %d
             ORDER BY created_at DESC",
				$project_id
			)
		);

		return rest_ensure_response( $invoices );
	}

	public function submit_request( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'id' );
		$user_id    = get_current_user_id();

		$access = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->access_table} WHERE user_id = %d AND project_id = %d",
				$user_id,
				$project_id
			)
		);

		if ( ! $access || ! $access->can_submit_requests ) {
			return new WP_Error( 'forbidden', 'Cannot submit requests', array( 'status' => 403 ) );
		}

		$data = array(
			'project_id'   => $project_id,
			'user_id'      => $user_id,
			'request_type' => sanitize_text_field( $request->get_param( 'request_type' ) ?: 'other' ),
			'subject'      => sanitize_text_field( $request->get_param( 'subject' ) ),
			'description'  => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'priority'     => sanitize_text_field( $request->get_param( 'priority' ) ?: 'medium' ),
		);

		$wpdb->insert( $wpdb->prefix . 'ict_client_requests', $data );
		$request_id = $wpdb->insert_id;

		do_action( 'ict_client_request_submitted', $request_id, $project_id, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $request_id,
			)
		);
	}

	public function get_client_requests( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, p.name as project_name
             FROM {$wpdb->prefix}ict_client_requests r
             JOIN {$wpdb->prefix}ict_projects p ON r.project_id = p.id
             WHERE r.user_id = %d
             ORDER BY r.created_at DESC",
				$user_id
			)
		);

		return rest_ensure_response( $requests );
	}

	public function get_all_access( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'project_id' );

		$where = $project_id ? $wpdb->prepare( 'WHERE a.project_id = %d', $project_id ) : '';

		$access = $wpdb->get_results(
			"SELECT a.*, u.display_name, u.user_email, p.name as project_name
             FROM {$this->access_table} a
             JOIN {$wpdb->users} u ON a.user_id = u.ID
             JOIN {$wpdb->prefix}ict_projects p ON a.project_id = p.id
             {$where}
             ORDER BY a.granted_at DESC"
		);

		return rest_ensure_response( $access );
	}

	public function grant_access( $request ) {
		global $wpdb;

		$data = array(
			'user_id'             => (int) $request->get_param( 'user_id' ),
			'project_id'          => (int) $request->get_param( 'project_id' ),
			'client_id'           => (int) $request->get_param( 'client_id' ) ?: null,
			'access_level'        => sanitize_text_field( $request->get_param( 'access_level' ) ?: 'view' ),
			'can_view_documents'  => (int) $request->get_param( 'can_view_documents' ),
			'can_view_invoices'   => (int) $request->get_param( 'can_view_invoices' ),
			'can_view_schedule'   => (int) $request->get_param( 'can_view_schedule' ),
			'can_submit_requests' => (int) $request->get_param( 'can_submit_requests' ),
			'expires_at'          => sanitize_text_field( $request->get_param( 'expires_at' ) ) ?: null,
			'granted_by'          => get_current_user_id(),
		);

		// Check for existing
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->access_table} WHERE user_id = %d AND project_id = %d",
				$data['user_id'],
				$data['project_id']
			)
		);

		if ( $existing ) {
			unset( $data['granted_by'] );
			$data['is_active'] = 1;
			$wpdb->update( $this->access_table, $data, array( 'id' => $existing ) );
			return rest_ensure_response(
				array(
					'success' => true,
					'id'      => $existing,
					'updated' => true,
				)
			);
		}

		$wpdb->insert( $this->access_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function revoke_access( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->update( $this->access_table, array( 'is_active' => 0 ), array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function invite_client( $request ) {
		$email      = sanitize_email( $request->get_param( 'email' ) );
		$name       = sanitize_text_field( $request->get_param( 'name' ) );
		$project_id = (int) $request->get_param( 'project_id' );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Invalid email', array( 'status' => 400 ) );
		}

		// Check if user exists
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			// Create user
			$password = wp_generate_password( 12, true );
			$user_id  = wp_create_user( $email, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
				)
			);
			$user = get_userdata( $user_id );
			$user->set_role( 'ict_client' );

			// Send invitation email
			do_action( 'ict_client_invited', $user_id, $email, $password, $project_id );
		} else {
			$user_id = $user->ID;
		}

		// Grant access
		$request->set_param( 'user_id', $user_id );
		return $this->grant_access( $request );
	}

	public function post_update( $request ) {
		global $wpdb;
		$project_id = (int) $request->get_param( 'id' );

		$data = array(
			'project_id'     => $project_id,
			'title'          => sanitize_text_field( $request->get_param( 'title' ) ),
			'content'        => wp_kses_post( $request->get_param( 'content' ) ),
			'update_type'    => sanitize_text_field( $request->get_param( 'update_type' ) ?: 'general' ),
			'is_public'      => (int) $request->get_param( 'is_public' ),
			'attachment_url' => esc_url_raw( $request->get_param( 'attachment_url' ) ),
			'created_by'     => get_current_user_id(),
		);

		$wpdb->insert( $wpdb->prefix . 'ict_project_updates', $data );
		$update_id = $wpdb->insert_id;

		if ( $data['is_public'] ) {
			do_action( 'ict_project_update_posted', $update_id, $project_id, $data );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $update_id,
			)
		);
	}
}
