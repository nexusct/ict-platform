<?php
/**
 * In-App Messaging System
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Messaging {

	private static $instance = null;
	private $conversations_table;
	private $messages_table;
	private $participants_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->conversations_table = $wpdb->prefix . 'ict_conversations';
		$this->messages_table      = $wpdb->prefix . 'ict_messages';
		$this->participants_table  = $wpdb->prefix . 'ict_conversation_participants';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/conversations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversations' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversation' ),
					'permission_callback' => array( $this, 'check_conversation_access' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_conversation' ),
					'permission_callback' => array( $this, 'check_conversation_access' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/conversations/(?P<id>\d+)/messages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_messages' ),
					'permission_callback' => array( $this, 'check_conversation_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'check_conversation_access' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/conversations/(?P<id>\d+)/read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'mark_read' ),
				'permission_callback' => array( $this, 'check_conversation_access' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/conversations/(?P<id>\d+)/participants',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_participants' ),
					'permission_callback' => array( $this, 'check_conversation_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_participant' ),
					'permission_callback' => array( $this, 'check_conversation_access' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/messages/unread-count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_unread_count' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/messages/direct',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_direct_message' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/messages/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_messages' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function check_conversation_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$conv_id = (int) $request->get_param( 'id' );
		return $this->user_in_conversation( $conv_id, get_current_user_id() );
	}

	private function user_in_conversation( $conv_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$this->participants_table} WHERE conversation_id = %d AND user_id = %d",
				$conv_id,
				$user_id
			)
		);
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->conversations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255),
            type enum('direct','group','project') DEFAULT 'direct',
            project_id bigint(20) unsigned,
            created_by bigint(20) unsigned NOT NULL,
            last_message_at datetime,
            last_message_preview varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY project_id (project_id),
            KEY last_message_at (last_message_at)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->messages_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            message text NOT NULL,
            message_type enum('text','file','image','system') DEFAULT 'text',
            attachment_url varchar(500),
            attachment_name varchar(255),
            is_edited tinyint(1) DEFAULT 0,
            edited_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_id (sender_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->participants_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            last_read_at datetime,
            is_muted tinyint(1) DEFAULT 0,
            joined_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY conv_user (conversation_id, user_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
	}

	public function get_conversations( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, p.last_read_at, p.is_muted,
                    (SELECT COUNT(*) FROM {$this->messages_table} m
                     WHERE m.conversation_id = c.id
                     AND m.created_at > COALESCE(p.last_read_at, '1970-01-01')
                     AND m.sender_id != %d) as unread_count
             FROM {$this->conversations_table} c
             JOIN {$this->participants_table} p ON c.id = p.conversation_id AND p.user_id = %d
             ORDER BY c.last_message_at DESC",
				$user_id,
				$user_id
			)
		);

		foreach ( $conversations as &$conv ) {
			$conv->participants = $this->get_conversation_participants( $conv->id );
		}

		return rest_ensure_response( $conversations );
	}

	private function get_conversation_participants( $conv_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, u.display_name, u.user_email
             FROM {$this->participants_table} p
             JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.conversation_id = %d",
				$conv_id
			)
		);
	}

	public function get_conversation( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$conv = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->conversations_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $conv ) {
			return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
		}

		$conv->participants = $this->get_conversation_participants( $id );

		return rest_ensure_response( $conv );
	}

	public function create_conversation( $request ) {
		global $wpdb;

		$type         = sanitize_text_field( $request->get_param( 'type' ) ?: 'direct' );
		$participants = $request->get_param( 'participants' ) ?: array();
		$user_id      = get_current_user_id();

		if ( ! in_array( $user_id, $participants ) ) {
			$participants[] = $user_id;
		}

		if ( count( $participants ) < 2 ) {
			return new WP_Error( 'invalid', 'Need at least 2 participants', array( 'status' => 400 ) );
		}

		// Check for existing direct conversation
		if ( $type === 'direct' && count( $participants ) === 2 ) {
			$existing = $this->find_direct_conversation( $participants[0], $participants[1] );
			if ( $existing ) {
				return rest_ensure_response(
					array(
						'success'  => true,
						'id'       => $existing,
						'existing' => true,
					)
				);
			}
		}

		$wpdb->insert(
			$this->conversations_table,
			array(
				'title'      => sanitize_text_field( $request->get_param( 'title' ) ),
				'type'       => $type,
				'project_id' => (int) $request->get_param( 'project_id' ) ?: null,
				'created_by' => $user_id,
			)
		);

		$conv_id = $wpdb->insert_id;

		foreach ( $participants as $pid ) {
			$wpdb->insert(
				$this->participants_table,
				array(
					'conversation_id' => $conv_id,
					'user_id'         => (int) $pid,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $conv_id,
			)
		);
	}

	private function find_direct_conversation( $user1, $user2 ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT c.id FROM {$this->conversations_table} c
             WHERE c.type = 'direct'
             AND EXISTS (SELECT 1 FROM {$this->participants_table} WHERE conversation_id = c.id AND user_id = %d)
             AND EXISTS (SELECT 1 FROM {$this->participants_table} WHERE conversation_id = c.id AND user_id = %d)
             AND (SELECT COUNT(*) FROM {$this->participants_table} WHERE conversation_id = c.id) = 2
             LIMIT 1",
				$user1,
				$user2
			)
		);
	}

	public function delete_conversation( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->delete( $this->messages_table, array( 'conversation_id' => $id ) );
		$wpdb->delete( $this->participants_table, array( 'conversation_id' => $id ) );
		$wpdb->delete( $this->conversations_table, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_messages( $request ) {
		global $wpdb;

		$conv_id = (int) $request->get_param( 'id' );
		$before  = $request->get_param( 'before' );
		$limit   = (int) $request->get_param( 'limit' ) ?: 50;

		$where  = 'conversation_id = %d';
		$values = array( $conv_id );

		if ( $before ) {
			$where   .= ' AND id < %d';
			$values[] = (int) $before;
		}

		$values[] = $limit;

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.display_name as sender_name
             FROM {$this->messages_table} m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE {$where}
             ORDER BY m.created_at DESC
             LIMIT %d",
				$values
			)
		);

		return rest_ensure_response( array_reverse( $messages ) );
	}

	public function send_message( $request ) {
		global $wpdb;

		$conv_id = (int) $request->get_param( 'id' );
		$message = sanitize_textarea_field( $request->get_param( 'message' ) );
		$user_id = get_current_user_id();

		if ( empty( $message ) ) {
			return new WP_Error( 'empty', 'Message cannot be empty', array( 'status' => 400 ) );
		}

		$data = array(
			'conversation_id' => $conv_id,
			'sender_id'       => $user_id,
			'message'         => $message,
			'message_type'    => sanitize_text_field( $request->get_param( 'message_type' ) ?: 'text' ),
			'attachment_url'  => esc_url_raw( $request->get_param( 'attachment_url' ) ),
			'attachment_name' => sanitize_text_field( $request->get_param( 'attachment_name' ) ),
		);

		$wpdb->insert( $this->messages_table, $data );
		$message_id = $wpdb->insert_id;

		// Update conversation
		$preview = mb_substr( strip_tags( $message ), 0, 100 );
		$wpdb->update(
			$this->conversations_table,
			array(
				'last_message_at'      => current_time( 'mysql' ),
				'last_message_preview' => $preview,
			),
			array( 'id' => $conv_id )
		);

		// Update sender's read timestamp
		$wpdb->update(
			$this->participants_table,
			array( 'last_read_at' => current_time( 'mysql' ) ),
			array(
				'conversation_id' => $conv_id,
				'user_id'         => $user_id,
			)
		);

		// Notify other participants
		do_action( 'ict_message_sent', $message_id, $conv_id, $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $message_id,
			)
		);
	}

	public function mark_read( $request ) {
		global $wpdb;

		$conv_id = (int) $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$wpdb->update(
			$this->participants_table,
			array( 'last_read_at' => current_time( 'mysql' ) ),
			array(
				'conversation_id' => $conv_id,
				'user_id'         => $user_id,
			)
		);

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_participants( $request ) {
		$conv_id = (int) $request->get_param( 'id' );
		return rest_ensure_response( $this->get_conversation_participants( $conv_id ) );
	}

	public function add_participant( $request ) {
		global $wpdb;

		$conv_id = (int) $request->get_param( 'id' );
		$user_id = (int) $request->get_param( 'user_id' );

		if ( $this->user_in_conversation( $conv_id, $user_id ) ) {
			return new WP_Error( 'exists', 'Already a participant', array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$this->participants_table,
			array(
				'conversation_id' => $conv_id,
				'user_id'         => $user_id,
			)
		);

		// Add system message
		$user = get_userdata( $user_id );
		$wpdb->insert(
			$this->messages_table,
			array(
				'conversation_id' => $conv_id,
				'sender_id'       => get_current_user_id(),
				'message'         => sprintf( '%s was added to the conversation', $user ? $user->display_name : 'User' ),
				'message_type'    => 'system',
			)
		);

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_unread_count( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->messages_table} m
             JOIN {$this->participants_table} p ON m.conversation_id = p.conversation_id
             WHERE p.user_id = %d
             AND m.sender_id != %d
             AND m.created_at > COALESCE(p.last_read_at, '1970-01-01')",
				$user_id,
				$user_id
			)
		);

		return rest_ensure_response( array( 'unread' => (int) $count ) );
	}

	public function send_direct_message( $request ) {
		$recipient_id = (int) $request->get_param( 'recipient_id' );
		$message      = sanitize_textarea_field( $request->get_param( 'message' ) );
		$user_id      = get_current_user_id();

		if ( $recipient_id === $user_id ) {
			return new WP_Error( 'invalid', 'Cannot message yourself', array( 'status' => 400 ) );
		}

		// Find or create conversation
		$conv_id = $this->find_direct_conversation( $user_id, $recipient_id );

		if ( ! $conv_id ) {
			global $wpdb;
			$wpdb->insert(
				$this->conversations_table,
				array(
					'type'       => 'direct',
					'created_by' => $user_id,
				)
			);
			$conv_id = $wpdb->insert_id;

			$wpdb->insert(
				$this->participants_table,
				array(
					'conversation_id' => $conv_id,
					'user_id'         => $user_id,
				)
			);
			$wpdb->insert(
				$this->participants_table,
				array(
					'conversation_id' => $conv_id,
					'user_id'         => $recipient_id,
				)
			);
		}

		// Send message
		$request->set_param( 'id', $conv_id );
		return $this->send_message( $request );
	}

	public function search_messages( $request ) {
		global $wpdb;

		$query   = sanitize_text_field( $request->get_param( 'q' ) );
		$user_id = get_current_user_id();

		if ( strlen( $query ) < 2 ) {
			return new WP_Error( 'too_short', 'Query too short', array( 'status' => 400 ) );
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, c.title as conversation_title, u.display_name as sender_name
             FROM {$this->messages_table} m
             JOIN {$this->conversations_table} c ON m.conversation_id = c.id
             JOIN {$this->participants_table} p ON c.id = p.conversation_id AND p.user_id = %d
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.message LIKE %s
             ORDER BY m.created_at DESC
             LIMIT 50",
				$user_id,
				'%' . $wpdb->esc_like( $query ) . '%'
			)
		);

		return rest_ensure_response( $messages );
	}
}
