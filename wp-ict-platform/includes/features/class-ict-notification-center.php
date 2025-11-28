<?php
/**
 * Notification Center
 *
 * Centralized notification management including:
 * - In-app notifications
 * - Notification preferences per user
 * - Notification categories and priorities
 * - Read/unread tracking
 * - Notification actions
 * - Batch operations
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Notification_Center
 */
class ICT_Notification_Center {

    /**
     * Singleton instance.
     *
     * @var ICT_Notification_Center
     */
    private static $instance = null;

    /**
     * Table names.
     *
     * @var array
     */
    private $tables = array();

    /**
     * Notification types.
     *
     * @var array
     */
    private $notification_types = array(
        'project_assigned'     => array( 'label' => 'Project Assignment', 'icon' => 'folder', 'category' => 'project' ),
        'project_updated'      => array( 'label' => 'Project Update', 'icon' => 'folder', 'category' => 'project' ),
        'project_completed'    => array( 'label' => 'Project Completed', 'icon' => 'check-circle', 'category' => 'project' ),
        'task_assigned'        => array( 'label' => 'Task Assignment', 'icon' => 'clipboard', 'category' => 'task' ),
        'task_due'             => array( 'label' => 'Task Due Soon', 'icon' => 'clock', 'category' => 'task' ),
        'task_overdue'         => array( 'label' => 'Task Overdue', 'icon' => 'alert-circle', 'category' => 'task' ),
        'time_approved'        => array( 'label' => 'Time Entry Approved', 'icon' => 'check', 'category' => 'time' ),
        'time_rejected'        => array( 'label' => 'Time Entry Rejected', 'icon' => 'x-circle', 'category' => 'time' ),
        'message_received'     => array( 'label' => 'New Message', 'icon' => 'message-circle', 'category' => 'communication' ),
        'mention'              => array( 'label' => 'You Were Mentioned', 'icon' => 'at-sign', 'category' => 'communication' ),
        'invoice_paid'         => array( 'label' => 'Invoice Paid', 'icon' => 'dollar-sign', 'category' => 'finance' ),
        'invoice_overdue'      => array( 'label' => 'Invoice Overdue', 'icon' => 'alert-triangle', 'category' => 'finance' ),
        'quote_accepted'       => array( 'label' => 'Quote Accepted', 'icon' => 'thumbs-up', 'category' => 'finance' ),
        'quote_rejected'       => array( 'label' => 'Quote Rejected', 'icon' => 'thumbs-down', 'category' => 'finance' ),
        'inventory_low'        => array( 'label' => 'Low Inventory Alert', 'icon' => 'package', 'category' => 'inventory' ),
        'equipment_due'        => array( 'label' => 'Equipment Maintenance Due', 'icon' => 'tool', 'category' => 'equipment' ),
        'certification_expiring' => array( 'label' => 'Certification Expiring', 'icon' => 'award', 'category' => 'compliance' ),
        'system_alert'         => array( 'label' => 'System Alert', 'icon' => 'alert-circle', 'category' => 'system' ),
        'announcement'         => array( 'label' => 'Announcement', 'icon' => 'bell', 'category' => 'system' ),
    );

    /**
     * Get singleton instance.
     *
     * @return ICT_Notification_Center
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
            'notifications' => $wpdb->prefix . 'ict_notifications',
            'preferences'   => $wpdb->prefix . 'ict_notification_preferences',
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

        // Cleanup old notifications.
        add_action( 'ict_cleanup_old_notifications', array( $this, 'cleanup_old_notifications' ) );
        if ( ! wp_next_scheduled( 'ict_cleanup_old_notifications' ) ) {
            wp_schedule_event( time(), 'daily', 'ict_cleanup_old_notifications' );
        }
    }

    /**
     * Create database tables.
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Notifications table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['notifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext,
            action_url varchar(500) DEFAULT NULL,
            action_label varchar(100) DEFAULT NULL,
            priority enum('low','normal','high','urgent') DEFAULT 'normal',
            is_read tinyint(1) DEFAULT 0,
            read_at datetime DEFAULT NULL,
            is_dismissed tinyint(1) DEFAULT 0,
            dismissed_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY priority (priority),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // User preferences.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['preferences']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            notification_type varchar(50) NOT NULL,
            channel_email tinyint(1) DEFAULT 1,
            channel_push tinyint(1) DEFAULT 1,
            channel_in_app tinyint(1) DEFAULT 1,
            channel_sms tinyint(1) DEFAULT 0,
            is_enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_type (user_id, notification_type),
            KEY user_id (user_id)
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

        // Get notifications.
        register_rest_route( $namespace, '/notifications', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_notifications' ),
                'permission_callback' => array( $this, 'check_user_permission' ),
            ),
        ) );

        // Get single notification.
        register_rest_route( $namespace, '/notifications/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_notification' ),
                'permission_callback' => array( $this, 'check_user_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'rest_delete_notification' ),
                'permission_callback' => array( $this, 'check_user_permission' ),
            ),
        ) );

        // Mark as read.
        register_rest_route( $namespace, '/notifications/(?P<id>\d+)/read', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_mark_read' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Mark all as read.
        register_rest_route( $namespace, '/notifications/read-all', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_mark_all_read' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Dismiss notification.
        register_rest_route( $namespace, '/notifications/(?P<id>\d+)/dismiss', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_dismiss_notification' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Dismiss all.
        register_rest_route( $namespace, '/notifications/dismiss-all', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_dismiss_all' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Unread count.
        register_rest_route( $namespace, '/notifications/unread-count', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_unread_count' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Preferences.
        register_rest_route( $namespace, '/notifications/preferences', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_preferences' ),
                'permission_callback' => array( $this, 'check_user_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_update_preferences' ),
                'permission_callback' => array( $this, 'check_user_permission' ),
            ),
        ) );

        // Notification types.
        register_rest_route( $namespace, '/notifications/types', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_types' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Admin: Send notification.
        register_rest_route( $namespace, '/notifications/send', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_send_notification' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Admin: Broadcast notification.
        register_rest_route( $namespace, '/notifications/broadcast', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_broadcast_notification' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    /**
     * Check user permission.
     */
    public function check_user_permission() {
        return is_user_logged_in();
    }

    /**
     * Check admin permission.
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get notifications.
     */
    public function rest_get_notifications( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $type = $request->get_param( 'type' );
        $category = $request->get_param( 'category' );
        $is_read = $request->get_param( 'is_read' );
        $priority = $request->get_param( 'priority' );
        $page = max( 1, intval( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

        $sql = "SELECT * FROM {$this->tables['notifications']}
                WHERE user_id = %d AND is_dismissed = 0
                AND (expires_at IS NULL OR expires_at > NOW())";
        $args = array( $user_id );

        if ( $type ) {
            $sql .= " AND type = %s";
            $args[] = $type;
        }

        if ( $category ) {
            $types_in_category = array_keys( array_filter( $this->notification_types, function( $t ) use ( $category ) {
                return $t['category'] === $category;
            } ) );
            if ( ! empty( $types_in_category ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $types_in_category ), '%s' ) );
                $sql .= " AND type IN ($placeholders)";
                $args = array_merge( $args, $types_in_category );
            }
        }

        if ( $is_read !== null ) {
            $sql .= " AND is_read = %d";
            $args[] = intval( $is_read );
        }

        if ( $priority ) {
            $sql .= " AND priority = %s";
            $args[] = $priority;
        }

        // Get count.
        $count_sql = str_replace( 'SELECT *', 'SELECT COUNT(*)', $sql );
        $total = $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );

        // Get notifications.
        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = ( $page - 1 ) * $per_page;

        $notifications = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        // Add type info.
        foreach ( $notifications as &$notification ) {
            $notification->data = json_decode( $notification->data, true );
            $notification->type_info = $this->notification_types[ $notification->type ] ?? null;
        }

        return rest_ensure_response( array(
            'success'       => true,
            'notifications' => $notifications,
            'total'         => intval( $total ),
            'pages'         => ceil( $total / $per_page ),
            'unread_count'  => $this->get_unread_count( $user_id ),
        ) );
    }

    /**
     * Get single notification.
     */
    public function rest_get_notification( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval( $request->get_param( 'id' ) );

        $notification = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['notifications']} WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ) );

        if ( ! $notification ) {
            return new WP_Error( 'not_found', 'Notification not found', array( 'status' => 404 ) );
        }

        $notification->data = json_decode( $notification->data, true );
        $notification->type_info = $this->notification_types[ $notification->type ] ?? null;

        // Mark as read.
        if ( ! $notification->is_read ) {
            $wpdb->update(
                $this->tables['notifications'],
                array( 'is_read' => 1, 'read_at' => current_time( 'mysql' ) ),
                array( 'id' => $id )
            );
        }

        return rest_ensure_response( array(
            'success'      => true,
            'notification' => $notification,
        ) );
    }

    /**
     * Delete notification.
     */
    public function rest_delete_notification( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval( $request->get_param( 'id' ) );

        $wpdb->delete(
            $this->tables['notifications'],
            array( 'id' => $id, 'user_id' => $user_id )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Notification deleted successfully',
        ) );
    }

    /**
     * Mark notification as read.
     */
    public function rest_mark_read( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval( $request->get_param( 'id' ) );

        $wpdb->update(
            $this->tables['notifications'],
            array( 'is_read' => 1, 'read_at' => current_time( 'mysql' ) ),
            array( 'id' => $id, 'user_id' => $user_id )
        );

        return rest_ensure_response( array(
            'success'      => true,
            'unread_count' => $this->get_unread_count( $user_id ),
        ) );
    }

    /**
     * Mark all notifications as read.
     */
    public function rest_mark_all_read( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $type = $request->get_param( 'type' );

        $sql = "UPDATE {$this->tables['notifications']}
                SET is_read = 1, read_at = %s
                WHERE user_id = %d AND is_read = 0";
        $args = array( current_time( 'mysql' ), $user_id );

        if ( $type ) {
            $sql .= " AND type = %s";
            $args[] = $type;
        }

        $wpdb->query( $wpdb->prepare( $sql, $args ) );

        return rest_ensure_response( array(
            'success'      => true,
            'unread_count' => $this->get_unread_count( $user_id ),
            'message'      => 'All notifications marked as read',
        ) );
    }

    /**
     * Dismiss notification.
     */
    public function rest_dismiss_notification( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $id = intval( $request->get_param( 'id' ) );

        $wpdb->update(
            $this->tables['notifications'],
            array( 'is_dismissed' => 1, 'dismissed_at' => current_time( 'mysql' ) ),
            array( 'id' => $id, 'user_id' => $user_id )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Notification dismissed',
        ) );
    }

    /**
     * Dismiss all notifications.
     */
    public function rest_dismiss_all( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();

        $wpdb->update(
            $this->tables['notifications'],
            array( 'is_dismissed' => 1, 'dismissed_at' => current_time( 'mysql' ) ),
            array( 'user_id' => $user_id, 'is_dismissed' => 0 )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'All notifications dismissed',
        ) );
    }

    /**
     * Get unread count.
     */
    public function rest_get_unread_count( $request ) {
        $user_id = get_current_user_id();

        return rest_ensure_response( array(
            'success' => true,
            'count'   => $this->get_unread_count( $user_id ),
        ) );
    }

    /**
     * Get unread count for user.
     *
     * @param int $user_id User ID.
     * @return int
     */
    private function get_unread_count( $user_id ) {
        global $wpdb;

        return intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['notifications']}
            WHERE user_id = %d AND is_read = 0 AND is_dismissed = 0
            AND (expires_at IS NULL OR expires_at > NOW())",
            $user_id
        ) ) );
    }

    /**
     * Get preferences.
     */
    public function rest_get_preferences( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();

        $preferences = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables['preferences']} WHERE user_id = %d",
            $user_id
        ) );

        // Build complete preferences with defaults.
        $all_preferences = array();
        foreach ( $this->notification_types as $type => $info ) {
            $pref = null;
            foreach ( $preferences as $p ) {
                if ( $p->notification_type === $type ) {
                    $pref = $p;
                    break;
                }
            }

            $all_preferences[] = array(
                'type'          => $type,
                'label'         => $info['label'],
                'category'      => $info['category'],
                'channel_email' => $pref ? (bool) $pref->channel_email : true,
                'channel_push'  => $pref ? (bool) $pref->channel_push : true,
                'channel_in_app' => $pref ? (bool) $pref->channel_in_app : true,
                'channel_sms'   => $pref ? (bool) $pref->channel_sms : false,
                'is_enabled'    => $pref ? (bool) $pref->is_enabled : true,
            );
        }

        // Group by category.
        $grouped = array();
        foreach ( $all_preferences as $pref ) {
            $category = $pref['category'];
            if ( ! isset( $grouped[ $category ] ) ) {
                $grouped[ $category ] = array();
            }
            $grouped[ $category ][] = $pref;
        }

        return rest_ensure_response( array(
            'success'     => true,
            'preferences' => $all_preferences,
            'grouped'     => $grouped,
        ) );
    }

    /**
     * Update preferences.
     */
    public function rest_update_preferences( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $preferences = $request->get_param( 'preferences' );

        if ( ! is_array( $preferences ) ) {
            return new WP_Error( 'invalid_data', 'Invalid preferences data', array( 'status' => 400 ) );
        }

        foreach ( $preferences as $pref ) {
            $type = sanitize_text_field( $pref['type'] );

            if ( ! isset( $this->notification_types[ $type ] ) ) {
                continue;
            }

            $data = array(
                'user_id'           => $user_id,
                'notification_type' => $type,
                'channel_email'     => isset( $pref['channel_email'] ) ? ( $pref['channel_email'] ? 1 : 0 ) : 1,
                'channel_push'      => isset( $pref['channel_push'] ) ? ( $pref['channel_push'] ? 1 : 0 ) : 1,
                'channel_in_app'    => isset( $pref['channel_in_app'] ) ? ( $pref['channel_in_app'] ? 1 : 0 ) : 1,
                'channel_sms'       => isset( $pref['channel_sms'] ) ? ( $pref['channel_sms'] ? 1 : 0 ) : 0,
                'is_enabled'        => isset( $pref['is_enabled'] ) ? ( $pref['is_enabled'] ? 1 : 0 ) : 1,
            );

            $wpdb->replace( $this->tables['preferences'], $data );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Preferences updated successfully',
        ) );
    }

    /**
     * Get notification types.
     */
    public function rest_get_types( $request ) {
        $types = array();
        foreach ( $this->notification_types as $type => $info ) {
            $types[] = array_merge( array( 'type' => $type ), $info );
        }

        return rest_ensure_response( array(
            'success' => true,
            'types'   => $types,
        ) );
    }

    /**
     * Send notification to user.
     */
    public function rest_send_notification( $request ) {
        $user_id = intval( $request->get_param( 'user_id' ) );
        $type = sanitize_text_field( $request->get_param( 'type' ) );
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $message = sanitize_textarea_field( $request->get_param( 'message' ) );
        $data = $request->get_param( 'data' );
        $priority = sanitize_text_field( $request->get_param( 'priority' ) ) ?: 'normal';

        $notification_id = $this->send( $user_id, $type, $title, $message, array(
            'data'     => $data,
            'priority' => $priority,
            'action_url' => sanitize_url( $request->get_param( 'action_url' ) ),
            'action_label' => sanitize_text_field( $request->get_param( 'action_label' ) ),
        ) );

        return rest_ensure_response( array(
            'success'         => true,
            'notification_id' => $notification_id,
            'message'         => 'Notification sent successfully',
        ) );
    }

    /**
     * Broadcast notification to multiple users.
     */
    public function rest_broadcast_notification( $request ) {
        global $wpdb;

        $user_ids = $request->get_param( 'user_ids' );
        $roles = $request->get_param( 'roles' );
        $all_users = $request->get_param( 'all_users' );

        $type = sanitize_text_field( $request->get_param( 'type' ) ) ?: 'announcement';
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $message = sanitize_textarea_field( $request->get_param( 'message' ) );
        $data = $request->get_param( 'data' );
        $priority = sanitize_text_field( $request->get_param( 'priority' ) ) ?: 'normal';

        $recipients = array();

        if ( $all_users ) {
            $recipients = $wpdb->get_col( "SELECT ID FROM {$wpdb->users}" );
        } elseif ( ! empty( $roles ) ) {
            foreach ( $roles as $role ) {
                $users = get_users( array( 'role' => $role, 'fields' => 'ID' ) );
                $recipients = array_merge( $recipients, $users );
            }
            $recipients = array_unique( $recipients );
        } elseif ( ! empty( $user_ids ) ) {
            $recipients = array_map( 'intval', $user_ids );
        }

        $sent = 0;
        foreach ( $recipients as $user_id ) {
            $this->send( $user_id, $type, $title, $message, array(
                'data'     => $data,
                'priority' => $priority,
                'action_url' => sanitize_url( $request->get_param( 'action_url' ) ),
                'action_label' => sanitize_text_field( $request->get_param( 'action_label' ) ),
            ) );
            $sent++;
        }

        return rest_ensure_response( array(
            'success'    => true,
            'sent_count' => $sent,
            'message'    => "Notification sent to $sent users",
        ) );
    }

    /**
     * Send notification.
     *
     * @param int    $user_id User ID.
     * @param string $type    Notification type.
     * @param string $title   Title.
     * @param string $message Message.
     * @param array  $options Additional options.
     * @return int|false Notification ID or false.
     */
    public function send( $user_id, $type, $title, $message, $options = array() ) {
        global $wpdb;

        // Check user preferences.
        $pref = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['preferences']}
            WHERE user_id = %d AND notification_type = %s",
            $user_id,
            $type
        ) );

        // If preference exists and is disabled, don't send.
        if ( $pref && ! $pref->is_enabled ) {
            return false;
        }

        // Create in-app notification.
        if ( ! $pref || $pref->channel_in_app ) {
            $wpdb->insert(
                $this->tables['notifications'],
                array(
                    'user_id'      => $user_id,
                    'type'         => $type,
                    'title'        => $title,
                    'message'      => $message,
                    'data'         => wp_json_encode( $options['data'] ?? array() ),
                    'action_url'   => $options['action_url'] ?? null,
                    'action_label' => $options['action_label'] ?? null,
                    'priority'     => $options['priority'] ?? 'normal',
                    'expires_at'   => $options['expires_at'] ?? null,
                    'created_by'   => get_current_user_id() ?: null,
                )
            );
            $notification_id = $wpdb->insert_id;
        } else {
            $notification_id = false;
        }

        // Send email if enabled.
        if ( ! $pref || $pref->channel_email ) {
            $user = get_user_by( 'ID', $user_id );
            if ( $user && $user->user_email ) {
                do_action( 'ict_send_email', 'notification', $user->user_email, array(
                    'user_first' => $user->first_name ?: $user->display_name,
                    'notification_title' => $title,
                    'notification_message' => $message,
                    'action_url' => $options['action_url'] ?? '',
                    'action_label' => $options['action_label'] ?? 'View',
                ) );
            }
        }

        // Send push notification if enabled.
        if ( ! $pref || $pref->channel_push ) {
            do_action( 'ict_send_push_notification', $user_id, $title, $message, $options );
        }

        return $notification_id;
    }

    /**
     * Cleanup old notifications.
     */
    public function cleanup_old_notifications() {
        global $wpdb;

        $retention_days = get_option( 'ict_notification_retention_days', 90 );

        // Delete old dismissed notifications.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->tables['notifications']}
            WHERE is_dismissed = 1 AND dismissed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ) );

        // Delete old read notifications.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->tables['notifications']}
            WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ) );

        // Delete expired notifications.
        $wpdb->query(
            "DELETE FROM {$this->tables['notifications']}
            WHERE expires_at IS NOT NULL AND expires_at < NOW()"
        );
    }

    /**
     * Register a custom notification type.
     *
     * @param string $type     Type slug.
     * @param string $label    Display label.
     * @param string $icon     Icon name.
     * @param string $category Category.
     */
    public function register_type( $type, $label, $icon = 'bell', $category = 'custom' ) {
        $this->notification_types[ $type ] = array(
            'label'    => $label,
            'icon'     => $icon,
            'category' => $category,
        );
    }
}
