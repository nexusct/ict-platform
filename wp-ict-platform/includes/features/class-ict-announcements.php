<?php
/**
 * Announcement Broadcasts
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Announcements {

	private static $instance = null;
	private $table_name;
	private $reads_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->table_name  = $wpdb->prefix . 'ict_announcements';
		$this->reads_table = $wpdb->prefix . 'ict_announcement_reads';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/announcements',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_announcements' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_announcement' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/announcements/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_announcement' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_announcement' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_announcement' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/announcements/(?P<id>\d+)/read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mark_read' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/announcements/(?P<id>\d+)/acknowledge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'acknowledge' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/announcements/(?P<id>\d+)/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/announcements/unread',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_unread' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/announcements/active',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function check_admin_permission() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_ict_projects' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            excerpt varchar(500),
            type enum('info','warning','critical','success') DEFAULT 'info',
            priority int DEFAULT 0,
            target_roles varchar(500),
            target_users text,
            is_pinned tinyint(1) DEFAULT 0,
            requires_acknowledgment tinyint(1) DEFAULT 0,
            publish_at datetime,
            expires_at datetime,
            is_published tinyint(1) DEFAULT 0,
            send_notification tinyint(1) DEFAULT 1,
            send_email tinyint(1) DEFAULT 0,
            attachment_url varchar(500),
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_published (is_published),
            KEY publish_at (publish_at),
            KEY expires_at (expires_at),
            KEY type (type)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->reads_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            announcement_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            read_at datetime,
            acknowledged_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY ann_user (announcement_id, user_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	public function get_announcements( $request ) {
		global $wpdb;

		$type         = $request->get_param( 'type' );
		$is_published = $request->get_param( 'is_published' );
		$page         = (int) $request->get_param( 'page' ) ?: 1;
		$per_page     = (int) $request->get_param( 'per_page' ) ?: 20;
		$offset       = ( $page - 1 ) * $per_page;

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		$roles   = $user ? $user->roles : array();

		$where  = array( '1=1' );
		$values = array();

		if ( $type ) {
			$where[]  = 'a.type = %s';
			$values[] = $type;
		}

		if ( null !== $is_published ) {
			$where[]  = 'a.is_published = %d';
			$values[] = (int) $is_published;
		} else {
			$where[] = 'a.is_published = 1';
			$where[] = '(a.publish_at IS NULL OR a.publish_at <= NOW())';
			$where[] = '(a.expires_at IS NULL OR a.expires_at > NOW())';
		}

		$where_clause = implode( ' AND ', $where );
		$values[]     = $user_id;
		$values[]     = $per_page;
		$values[]     = $offset;

		$announcements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name as author_name,
                    r.read_at, r.acknowledged_at
             FROM {$this->table_name} a
             LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID
             LEFT JOIN {$this->reads_table} r ON a.id = r.announcement_id AND r.user_id = %d
             WHERE {$where_clause}
             ORDER BY a.is_pinned DESC, a.priority DESC, a.created_at DESC
             LIMIT %d OFFSET %d",
				array_merge( array( $user_id ), $values )
			)
		);

		// Filter by roles
		$filtered = array();
		foreach ( $announcements as $ann ) {
			if ( $this->user_can_view( $ann, $user_id, $roles ) ) {
				$filtered[] = $ann;
			}
		}

		return rest_ensure_response( $filtered );
	}

	private function user_can_view( $announcement, $user_id, $user_roles ) {
		// Check target users
		if ( $announcement->target_users ) {
			$users = json_decode( $announcement->target_users, true );
			if ( ! empty( $users ) && ! in_array( $user_id, $users ) ) {
				return false;
			}
		}

		// Check target roles
		if ( $announcement->target_roles ) {
			$roles = explode( ',', $announcement->target_roles );
			$roles = array_map( 'trim', $roles );
			if ( ! empty( $roles ) && empty( array_intersect( $user_roles, $roles ) ) ) {
				return false;
			}
		}

		return true;
	}

	public function get_announcement( $request ) {
		global $wpdb;
		$id      = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$ann = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, u.display_name as author_name,
                    r.read_at, r.acknowledged_at
             FROM {$this->table_name} a
             LEFT JOIN {$wpdb->users} u ON a.created_by = u.ID
             LEFT JOIN {$this->reads_table} r ON a.id = r.announcement_id AND r.user_id = %d
             WHERE a.id = %d",
				$user_id,
				$id
			)
		);

		if ( ! $ann ) {
			return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $ann );
	}

	public function create_announcement( $request ) {
		global $wpdb;

		$target_users = $request->get_param( 'target_users' );

		$data = array(
			'title'                   => sanitize_text_field( $request->get_param( 'title' ) ),
			'content'                 => wp_kses_post( $request->get_param( 'content' ) ),
			'excerpt'                 => sanitize_text_field( $request->get_param( 'excerpt' ) ),
			'type'                    => sanitize_text_field( $request->get_param( 'type' ) ?: 'info' ),
			'priority'                => (int) $request->get_param( 'priority' ),
			'target_roles'            => sanitize_text_field( $request->get_param( 'target_roles' ) ),
			'target_users'            => $target_users ? wp_json_encode( $target_users ) : null,
			'is_pinned'               => (int) $request->get_param( 'is_pinned' ),
			'requires_acknowledgment' => (int) $request->get_param( 'requires_acknowledgment' ),
			'publish_at'              => sanitize_text_field( $request->get_param( 'publish_at' ) ) ?: current_time( 'mysql' ),
			'expires_at'              => sanitize_text_field( $request->get_param( 'expires_at' ) ) ?: null,
			'is_published'            => (int) $request->get_param( 'is_published' ),
			'send_notification'       => (int) $request->get_param( 'send_notification' ),
			'send_email'              => (int) $request->get_param( 'send_email' ),
			'attachment_url'          => esc_url_raw( $request->get_param( 'attachment_url' ) ),
			'created_by'              => get_current_user_id(),
		);

		$wpdb->insert( $this->table_name, $data );
		$ann_id = $wpdb->insert_id;

		if ( $data['is_published'] && $data['send_notification'] ) {
			do_action( 'ict_announcement_published', $ann_id, $data );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $ann_id,
			)
		);
	}

	public function update_announcement( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$data        = array();
		$text_fields = array( 'title', 'excerpt', 'type', 'target_roles', 'publish_at', 'expires_at' );
		foreach ( $text_fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = sanitize_text_field( $v );
			}
		}

		$content = $request->get_param( 'content' );
		if ( null !== $content ) {
			$data['content'] = wp_kses_post( $content );
		}

		$target_users = $request->get_param( 'target_users' );
		if ( null !== $target_users ) {
			$data['target_users'] = wp_json_encode( $target_users );
		}

		$int_fields = array( 'priority', 'is_pinned', 'requires_acknowledgment', 'is_published', 'send_notification', 'send_email' );
		foreach ( $int_fields as $f ) {
			$v = $request->get_param( $f );
			if ( null !== $v ) {
				$data[ $f ] = (int) $v;
			}
		}

		$url = $request->get_param( 'attachment_url' );
		if ( null !== $url ) {
			$data['attachment_url'] = esc_url_raw( $url );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->table_name, $data, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_announcement( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->delete( $this->reads_table, array( 'announcement_id' => $id ) );
		$wpdb->delete( $this->table_name, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function mark_read( $request ) {
		global $wpdb;
		$ann_id  = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->reads_table} WHERE announcement_id = %d AND user_id = %d",
				$ann_id,
				$user_id
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$this->reads_table,
				array( 'read_at' => current_time( 'mysql' ) ),
				array( 'id' => $existing )
			);
		} else {
			$wpdb->insert(
				$this->reads_table,
				array(
					'announcement_id' => $ann_id,
					'user_id'         => $user_id,
					'read_at'         => current_time( 'mysql' ),
				)
			);
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function acknowledge( $request ) {
		global $wpdb;
		$ann_id  = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$ann = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$ann_id
			)
		);

		if ( ! $ann || ! $ann->requires_acknowledgment ) {
			return new WP_Error( 'invalid', 'Acknowledgment not required', array( 'status' => 400 ) );
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->reads_table} WHERE announcement_id = %d AND user_id = %d",
				$ann_id,
				$user_id
			)
		);

		$now = current_time( 'mysql' );

		if ( $existing ) {
			$wpdb->update(
				$this->reads_table,
				array(
					'read_at'         => $now,
					'acknowledged_at' => $now,
				),
				array( 'id' => $existing )
			);
		} else {
			$wpdb->insert(
				$this->reads_table,
				array(
					'announcement_id' => $ann_id,
					'user_id'         => $user_id,
					'read_at'         => $now,
					'acknowledged_at' => $now,
				)
			);
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_stats( $request ) {
		global $wpdb;
		$ann_id = (int) $request->get_param( 'id' );

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                (SELECT COUNT(*) FROM {$this->reads_table} WHERE announcement_id = %d AND read_at IS NOT NULL) as read_count,
                (SELECT COUNT(*) FROM {$this->reads_table} WHERE announcement_id = %d AND acknowledged_at IS NOT NULL) as ack_count",
				$ann_id,
				$ann_id
			)
		);

		$readers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name, u.user_email
             FROM {$this->reads_table} r
             JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.announcement_id = %d
             ORDER BY r.read_at DESC",
				$ann_id
			)
		);

		return rest_ensure_response(
			array(
				'stats'   => $stats,
				'readers' => $readers,
			)
		);
	}

	public function get_unread( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} a
             WHERE a.is_published = 1
             AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             AND (a.expires_at IS NULL OR a.expires_at > NOW())
             AND NOT EXISTS (
                 SELECT 1 FROM {$this->reads_table} r
                 WHERE r.announcement_id = a.id AND r.user_id = %d
             )",
				$user_id
			)
		);

		return rest_ensure_response( array( 'unread' => (int) $count ) );
	}

	public function get_active( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$announcements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, r.read_at, r.acknowledged_at
             FROM {$this->table_name} a
             LEFT JOIN {$this->reads_table} r ON a.id = r.announcement_id AND r.user_id = %d
             WHERE a.is_published = 1
             AND (a.publish_at IS NULL OR a.publish_at <= NOW())
             AND (a.expires_at IS NULL OR a.expires_at > NOW())
             ORDER BY a.is_pinned DESC, a.type = 'critical' DESC, a.priority DESC, a.created_at DESC
             LIMIT 10",
				$user_id
			)
		);

		return rest_ensure_response( $announcements );
	}
}
