<?php
/**
 * Mobile Push Notifications
 *
 * Push notification system including:
 * - Web Push (Service Worker based)
 * - Firebase Cloud Messaging integration
 * - Device registration and management
 * - Topic-based subscriptions
 * - Scheduled push notifications
 * - Analytics and delivery tracking
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Push_Notifications
 */
class ICT_Push_Notifications {

    /**
     * Singleton instance.
     *
     * @var ICT_Push_Notifications
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
     * @return ICT_Push_Notifications
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
            'devices'       => $wpdb->prefix . 'ict_push_devices',
            'subscriptions' => $wpdb->prefix . 'ict_push_subscriptions',
            'messages'      => $wpdb->prefix . 'ict_push_messages',
            'delivery'      => $wpdb->prefix . 'ict_push_delivery',
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

        // Handle notification sending.
        add_action( 'ict_send_push_notification', array( $this, 'send_to_user' ), 10, 4 );

        // Process scheduled notifications.
        add_action( 'ict_process_scheduled_push', array( $this, 'process_scheduled' ) );
        if ( ! wp_next_scheduled( 'ict_process_scheduled_push' ) ) {
            wp_schedule_event( time(), 'minutely', 'ict_process_scheduled_push' );
        }

        // Cleanup old data.
        add_action( 'ict_cleanup_push_data', array( $this, 'cleanup_old_data' ) );
        if ( ! wp_next_scheduled( 'ict_cleanup_push_data' ) ) {
            wp_schedule_event( time(), 'daily', 'ict_cleanup_push_data' );
        }
    }

    /**
     * Create database tables.
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Device registrations.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['devices']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            device_token varchar(500) NOT NULL,
            device_type enum('web','ios','android') DEFAULT 'web',
            device_name varchar(200) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            push_endpoint varchar(1000) DEFAULT NULL,
            push_key varchar(500) DEFAULT NULL,
            push_auth varchar(500) DEFAULT NULL,
            fcm_token varchar(500) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            last_used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY device_token (device_token(255)),
            KEY user_id (user_id),
            KEY device_type (device_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Topic subscriptions.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['subscriptions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            device_id bigint(20) unsigned NOT NULL,
            topic varchar(100) NOT NULL,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY device_topic (device_id, topic),
            KEY topic (topic)
        ) $charset_collate;";

        // Push messages.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['messages']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            body text NOT NULL,
            icon varchar(500) DEFAULT NULL,
            image varchar(500) DEFAULT NULL,
            action_url varchar(500) DEFAULT NULL,
            data longtext,
            target_type enum('user','topic','all','segment') DEFAULT 'user',
            target_value varchar(255) DEFAULT NULL,
            status enum('draft','scheduled','sending','sent','failed') DEFAULT 'draft',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            total_recipients int(11) DEFAULT 0,
            successful_count int(11) DEFAULT 0,
            failed_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        // Delivery tracking.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['delivery']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            device_id bigint(20) unsigned NOT NULL,
            status enum('pending','sent','delivered','clicked','failed') DEFAULT 'pending',
            error_message varchar(500) DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY message_device (message_id, device_id),
            KEY message_id (message_id),
            KEY status (status)
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

        // Device registration.
        register_rest_route( $namespace, '/push/register', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_register_device' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Unregister device.
        register_rest_route( $namespace, '/push/unregister', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_unregister_device' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Get user devices.
        register_rest_route( $namespace, '/push/devices', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_devices' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Delete device.
        register_rest_route( $namespace, '/push/devices/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'rest_delete_device' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Subscribe to topic.
        register_rest_route( $namespace, '/push/subscribe', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_subscribe_topic' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Unsubscribe from topic.
        register_rest_route( $namespace, '/push/unsubscribe', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_unsubscribe_topic' ),
            'permission_callback' => array( $this, 'check_user_permission' ),
        ) );

        // Get VAPID public key.
        register_rest_route( $namespace, '/push/vapid-key', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_vapid_key' ),
            'permission_callback' => '__return_true',
        ) );

        // Track delivery/click.
        register_rest_route( $namespace, '/push/track', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_track_event' ),
            'permission_callback' => '__return_true',
        ) );

        // Admin: Send notification.
        register_rest_route( $namespace, '/push/send', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'rest_send_notification' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Admin: Get messages.
        register_rest_route( $namespace, '/push/messages', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_messages' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Admin: Get analytics.
        register_rest_route( $namespace, '/push/analytics', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_analytics' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // Admin: Get topics.
        register_rest_route( $namespace, '/push/topics', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_topics' ),
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
     * Register device.
     */
    public function rest_register_device( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $device_type = sanitize_text_field( $request->get_param( 'device_type' ) ) ?: 'web';
        $device_token = sanitize_text_field( $request->get_param( 'device_token' ) );

        if ( ! $device_token ) {
            return new WP_Error( 'no_token', 'Device token is required', array( 'status' => 400 ) );
        }

        // Check if device already exists.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['devices']} WHERE device_token = %s",
            $device_token
        ) );

        if ( $existing ) {
            // Update existing device.
            $wpdb->update(
                $this->tables['devices'],
                array(
                    'user_id'      => $user_id,
                    'is_active'    => 1,
                    'last_used_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $existing->id )
            );

            return rest_ensure_response( array(
                'success'   => true,
                'device_id' => $existing->id,
                'message'   => 'Device updated successfully',
            ) );
        }

        // Create new device.
        $data = array(
            'user_id'      => $user_id,
            'device_token' => $device_token,
            'device_type'  => $device_type,
            'device_name'  => sanitize_text_field( $request->get_param( 'device_name' ) ),
            'browser'      => sanitize_text_field( $request->get_param( 'browser' ) ),
            'os'           => sanitize_text_field( $request->get_param( 'os' ) ),
            'last_used_at' => current_time( 'mysql' ),
        );

        // Web Push specific.
        if ( $device_type === 'web' ) {
            $subscription = $request->get_param( 'subscription' );
            if ( is_array( $subscription ) ) {
                $data['push_endpoint'] = esc_url_raw( $subscription['endpoint'] ?? '' );
                $data['push_key'] = sanitize_text_field( $subscription['keys']['p256dh'] ?? '' );
                $data['push_auth'] = sanitize_text_field( $subscription['keys']['auth'] ?? '' );
            }
        }

        // FCM token.
        $fcm_token = $request->get_param( 'fcm_token' );
        if ( $fcm_token ) {
            $data['fcm_token'] = sanitize_text_field( $fcm_token );
        }

        $wpdb->insert( $this->tables['devices'], $data );
        $device_id = $wpdb->insert_id;

        // Subscribe to default topics.
        $default_topics = array( 'announcements', 'system' );
        foreach ( $default_topics as $topic ) {
            $wpdb->insert(
                $this->tables['subscriptions'],
                array(
                    'device_id' => $device_id,
                    'topic'     => $topic,
                )
            );
        }

        return rest_ensure_response( array(
            'success'   => true,
            'device_id' => $device_id,
            'message'   => 'Device registered successfully',
        ) );
    }

    /**
     * Unregister device.
     */
    public function rest_unregister_device( $request ) {
        global $wpdb;

        $device_token = sanitize_text_field( $request->get_param( 'device_token' ) );

        $wpdb->update(
            $this->tables['devices'],
            array( 'is_active' => 0 ),
            array( 'device_token' => $device_token )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Device unregistered successfully',
        ) );
    }

    /**
     * Get user devices.
     */
    public function rest_get_devices( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();

        $devices = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, device_type, device_name, browser, os, is_active, last_used_at, created_at
            FROM {$this->tables['devices']}
            WHERE user_id = %d
            ORDER BY last_used_at DESC",
            $user_id
        ) );

        // Get subscriptions for each device.
        foreach ( $devices as &$device ) {
            $device->subscriptions = $wpdb->get_col( $wpdb->prepare(
                "SELECT topic FROM {$this->tables['subscriptions']} WHERE device_id = %d",
                $device->id
            ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'devices' => $devices,
        ) );
    }

    /**
     * Delete device.
     */
    public function rest_delete_device( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $device_id = intval( $request->get_param( 'id' ) );

        $wpdb->delete(
            $this->tables['devices'],
            array( 'id' => $device_id, 'user_id' => $user_id )
        );

        $wpdb->delete(
            $this->tables['subscriptions'],
            array( 'device_id' => $device_id )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Device deleted successfully',
        ) );
    }

    /**
     * Subscribe to topic.
     */
    public function rest_subscribe_topic( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $topic = sanitize_text_field( $request->get_param( 'topic' ) );
        $device_id = intval( $request->get_param( 'device_id' ) );

        // If no device specified, subscribe all user's devices.
        if ( ! $device_id ) {
            $devices = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$this->tables['devices']} WHERE user_id = %d AND is_active = 1",
                $user_id
            ) );
        } else {
            $devices = array( $device_id );
        }

        foreach ( $devices as $did ) {
            $wpdb->replace(
                $this->tables['subscriptions'],
                array(
                    'device_id' => $did,
                    'topic'     => $topic,
                )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Subscribed to topic successfully',
        ) );
    }

    /**
     * Unsubscribe from topic.
     */
    public function rest_unsubscribe_topic( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $topic = sanitize_text_field( $request->get_param( 'topic' ) );
        $device_id = intval( $request->get_param( 'device_id' ) );

        if ( ! $device_id ) {
            $devices = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$this->tables['devices']} WHERE user_id = %d",
                $user_id
            ) );

            $placeholders = implode( ',', array_fill( 0, count( $devices ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$this->tables['subscriptions']}
                WHERE device_id IN ($placeholders) AND topic = %s",
                array_merge( $devices, array( $topic ) )
            ) );
        } else {
            $wpdb->delete(
                $this->tables['subscriptions'],
                array( 'device_id' => $device_id, 'topic' => $topic )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Unsubscribed from topic successfully',
        ) );
    }

    /**
     * Get VAPID public key.
     */
    public function rest_get_vapid_key( $request ) {
        $public_key = get_option( 'ict_vapid_public_key', '' );

        if ( ! $public_key ) {
            // Generate VAPID keys if not exist.
            $keys = $this->generate_vapid_keys();
            $public_key = $keys['public'];
        }

        return rest_ensure_response( array(
            'success'    => true,
            'public_key' => $public_key,
        ) );
    }

    /**
     * Generate VAPID keys.
     *
     * @return array
     */
    private function generate_vapid_keys() {
        // For production, use a proper ECDSA key generation.
        // This is a simplified example.
        $private_key = base64_encode( random_bytes( 32 ) );
        $public_key = base64_encode( random_bytes( 65 ) );

        update_option( 'ict_vapid_private_key', $private_key );
        update_option( 'ict_vapid_public_key', $public_key );

        return array(
            'private' => $private_key,
            'public'  => $public_key,
        );
    }

    /**
     * Track delivery/click event.
     */
    public function rest_track_event( $request ) {
        global $wpdb;

        $message_id = intval( $request->get_param( 'message_id' ) );
        $device_id = intval( $request->get_param( 'device_id' ) );
        $event = sanitize_text_field( $request->get_param( 'event' ) );

        $update = array();

        switch ( $event ) {
            case 'delivered':
                $update['status'] = 'delivered';
                $update['delivered_at'] = current_time( 'mysql' );
                break;
            case 'clicked':
                $update['status'] = 'clicked';
                $update['clicked_at'] = current_time( 'mysql' );
                break;
        }

        if ( ! empty( $update ) ) {
            $wpdb->update(
                $this->tables['delivery'],
                $update,
                array( 'message_id' => $message_id, 'device_id' => $device_id )
            );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Send notification (admin).
     */
    public function rest_send_notification( $request ) {
        global $wpdb;

        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $body = sanitize_textarea_field( $request->get_param( 'body' ) );
        $target_type = sanitize_text_field( $request->get_param( 'target_type' ) ) ?: 'all';
        $target_value = sanitize_text_field( $request->get_param( 'target_value' ) );
        $scheduled_at = sanitize_text_field( $request->get_param( 'scheduled_at' ) );

        // Create message.
        $wpdb->insert(
            $this->tables['messages'],
            array(
                'title'        => $title,
                'body'         => $body,
                'icon'         => sanitize_url( $request->get_param( 'icon' ) ),
                'image'        => sanitize_url( $request->get_param( 'image' ) ),
                'action_url'   => sanitize_url( $request->get_param( 'action_url' ) ),
                'data'         => wp_json_encode( $request->get_param( 'data' ) ),
                'target_type'  => $target_type,
                'target_value' => $target_value,
                'status'       => $scheduled_at ? 'scheduled' : 'sending',
                'scheduled_at' => $scheduled_at ?: null,
                'created_by'   => get_current_user_id(),
            )
        );

        $message_id = $wpdb->insert_id;

        // If not scheduled, send immediately.
        if ( ! $scheduled_at ) {
            $this->send_message( $message_id );
        }

        return rest_ensure_response( array(
            'success'    => true,
            'message_id' => $message_id,
            'message'    => $scheduled_at ? 'Notification scheduled' : 'Notification sent',
        ) );
    }

    /**
     * Get messages (admin).
     */
    public function rest_get_messages( $request ) {
        global $wpdb;

        $status = $request->get_param( 'status' );
        $page = max( 1, intval( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

        $sql = "SELECT m.*, u.display_name as created_by_name
                FROM {$this->tables['messages']} m
                LEFT JOIN {$wpdb->users} u ON m.created_by = u.ID
                WHERE 1=1";
        $args = array();

        if ( $status ) {
            $sql .= " AND m.status = %s";
            $args[] = $status;
        }

        $count_sql = str_replace( 'SELECT m.*, u.display_name as created_by_name', 'SELECT COUNT(*)', $sql );
        $total = ! empty( $args ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql );

        $sql .= " ORDER BY m.created_at DESC LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = ( $page - 1 ) * $per_page;

        $messages = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        return rest_ensure_response( array(
            'success'  => true,
            'messages' => $messages,
            'total'    => intval( $total ),
            'pages'    => ceil( $total / $per_page ),
        ) );
    }

    /**
     * Get analytics (admin).
     */
    public function rest_get_analytics( $request ) {
        global $wpdb;

        $days = intval( $request->get_param( 'days' ) ) ?: 30;

        $stats = array(
            'total_devices' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['devices']} WHERE is_active = 1"
            ),
            'devices_by_type' => $wpdb->get_results(
                "SELECT device_type, COUNT(*) as count
                FROM {$this->tables['devices']}
                WHERE is_active = 1
                GROUP BY device_type"
            ),
            'total_messages' => $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['messages']}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ) ),
            'total_sent' => $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(successful_count), 0)
                FROM {$this->tables['messages']}
                WHERE status = 'sent' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ) ),
            'total_clicked' => $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['delivery']}
                WHERE status = 'clicked' AND clicked_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ) ),
            'delivery_rate' => 0,
            'click_rate' => 0,
        );

        // Calculate rates.
        $total_delivered = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['delivery']}
            WHERE status IN ('delivered', 'clicked') AND sent_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );

        $total_sent_delivery = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['delivery']}
            WHERE status != 'pending' AND sent_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );

        if ( $total_sent_delivery > 0 ) {
            $stats['delivery_rate'] = round( ( $total_delivered / $total_sent_delivery ) * 100, 2 );
            $stats['click_rate'] = round( ( $stats['total_clicked'] / $total_sent_delivery ) * 100, 2 );
        }

        // Messages by day.
        $stats['messages_by_day'] = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
            FROM {$this->tables['messages']}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(created_at)
            ORDER BY date",
            $days
        ) );

        return rest_ensure_response( array(
            'success' => true,
            'stats'   => $stats,
        ) );
    }

    /**
     * Get topics (admin).
     */
    public function rest_get_topics( $request ) {
        global $wpdb;

        $topics = $wpdb->get_results(
            "SELECT topic, COUNT(*) as subscriber_count
            FROM {$this->tables['subscriptions']}
            GROUP BY topic
            ORDER BY subscriber_count DESC"
        );

        return rest_ensure_response( array(
            'success' => true,
            'topics'  => $topics,
        ) );
    }

    /**
     * Send push notification to user.
     *
     * @param int    $user_id User ID.
     * @param string $title   Title.
     * @param string $body    Body.
     * @param array  $options Options.
     */
    public function send_to_user( $user_id, $title, $body, $options = array() ) {
        global $wpdb;

        // Get user's active devices.
        $devices = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables['devices']}
            WHERE user_id = %d AND is_active = 1",
            $user_id
        ) );

        foreach ( $devices as $device ) {
            $this->send_to_device( $device, $title, $body, $options );
        }
    }

    /**
     * Send push notification to device.
     *
     * @param object $device  Device object.
     * @param string $title   Title.
     * @param string $body    Body.
     * @param array  $options Options.
     * @return bool
     */
    private function send_to_device( $device, $title, $body, $options = array() ) {
        $payload = array(
            'title' => $title,
            'body'  => $body,
            'icon'  => $options['icon'] ?? get_site_icon_url(),
            'badge' => $options['badge'] ?? null,
            'image' => $options['image'] ?? null,
            'data'  => array(
                'action_url' => $options['action_url'] ?? home_url(),
                'timestamp'  => time(),
            ),
        );

        if ( $device->device_type === 'web' && $device->push_endpoint ) {
            return $this->send_web_push( $device, $payload );
        } elseif ( $device->fcm_token ) {
            return $this->send_fcm( $device, $payload );
        }

        return false;
    }

    /**
     * Send Web Push notification.
     *
     * @param object $device  Device object.
     * @param array  $payload Payload.
     * @return bool
     */
    private function send_web_push( $device, $payload ) {
        // Simplified Web Push implementation.
        // In production, use a proper Web Push library like minishlink/web-push.

        $endpoint = $device->push_endpoint;
        $payload_json = wp_json_encode( $payload );

        $response = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Content-Type'     => 'application/json',
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => '86400',
            ),
            'body'    => $payload_json,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // Handle expired subscription.
        if ( $status_code === 410 || $status_code === 404 ) {
            global $wpdb;
            $wpdb->update(
                $this->tables['devices'],
                array( 'is_active' => 0 ),
                array( 'id' => $device->id )
            );
            return false;
        }

        return $status_code >= 200 && $status_code < 300;
    }

    /**
     * Send FCM notification.
     *
     * @param object $device  Device object.
     * @param array  $payload Payload.
     * @return bool
     */
    private function send_fcm( $device, $payload ) {
        $server_key = get_option( 'ict_fcm_server_key', '' );

        if ( ! $server_key ) {
            return false;
        }

        $fcm_payload = array(
            'to'           => $device->fcm_token,
            'notification' => array(
                'title' => $payload['title'],
                'body'  => $payload['body'],
                'icon'  => $payload['icon'],
                'image' => $payload['image'],
            ),
            'data'         => $payload['data'],
        );

        $response = wp_remote_post( 'https://fcm.googleapis.com/fcm/send', array(
            'headers' => array(
                'Authorization' => 'key=' . $server_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $fcm_payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Handle invalid token.
        if ( isset( $body['results'][0]['error'] ) ) {
            $error = $body['results'][0]['error'];
            if ( in_array( $error, array( 'NotRegistered', 'InvalidRegistration' ) ) ) {
                global $wpdb;
                $wpdb->update(
                    $this->tables['devices'],
                    array( 'is_active' => 0 ),
                    array( 'id' => $device->id )
                );
            }
            return false;
        }

        return isset( $body['success'] ) && $body['success'] > 0;
    }

    /**
     * Send message to targets.
     *
     * @param int $message_id Message ID.
     */
    private function send_message( $message_id ) {
        global $wpdb;

        $message = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['messages']} WHERE id = %d",
            $message_id
        ) );

        if ( ! $message ) {
            return;
        }

        // Get target devices.
        $devices = array();

        switch ( $message->target_type ) {
            case 'user':
                $devices = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$this->tables['devices']}
                    WHERE user_id = %d AND is_active = 1",
                    $message->target_value
                ) );
                break;

            case 'topic':
                $devices = $wpdb->get_results( $wpdb->prepare(
                    "SELECT d.* FROM {$this->tables['devices']} d
                    INNER JOIN {$this->tables['subscriptions']} s ON d.id = s.device_id
                    WHERE s.topic = %s AND d.is_active = 1",
                    $message->target_value
                ) );
                break;

            case 'all':
                $devices = $wpdb->get_results(
                    "SELECT * FROM {$this->tables['devices']} WHERE is_active = 1"
                );
                break;
        }

        // Update message with recipient count.
        $wpdb->update(
            $this->tables['messages'],
            array( 'total_recipients' => count( $devices ) ),
            array( 'id' => $message_id )
        );

        $successful = 0;
        $failed = 0;

        foreach ( $devices as $device ) {
            // Create delivery record.
            $wpdb->insert(
                $this->tables['delivery'],
                array(
                    'message_id' => $message_id,
                    'device_id'  => $device->id,
                    'status'     => 'pending',
                )
            );

            // Send notification.
            $options = array(
                'icon'       => $message->icon,
                'image'      => $message->image,
                'action_url' => $message->action_url,
                'data'       => json_decode( $message->data, true ),
            );

            $sent = $this->send_to_device( $device, $message->title, $message->body, $options );

            // Update delivery status.
            $wpdb->update(
                $this->tables['delivery'],
                array(
                    'status'  => $sent ? 'sent' : 'failed',
                    'sent_at' => current_time( 'mysql' ),
                ),
                array( 'message_id' => $message_id, 'device_id' => $device->id )
            );

            if ( $sent ) {
                $successful++;
            } else {
                $failed++;
            }
        }

        // Update message status.
        $wpdb->update(
            $this->tables['messages'],
            array(
                'status'           => 'sent',
                'sent_at'          => current_time( 'mysql' ),
                'successful_count' => $successful,
                'failed_count'     => $failed,
            ),
            array( 'id' => $message_id )
        );
    }

    /**
     * Process scheduled notifications.
     */
    public function process_scheduled() {
        global $wpdb;

        $messages = $wpdb->get_results(
            "SELECT id FROM {$this->tables['messages']}
            WHERE status = 'scheduled' AND scheduled_at <= NOW()"
        );

        foreach ( $messages as $message ) {
            $wpdb->update(
                $this->tables['messages'],
                array( 'status' => 'sending' ),
                array( 'id' => $message->id )
            );

            $this->send_message( $message->id );
        }
    }

    /**
     * Cleanup old data.
     */
    public function cleanup_old_data() {
        global $wpdb;

        $retention_days = get_option( 'ict_push_retention_days', 30 );

        // Remove old delivery records.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->tables['delivery']}
            WHERE sent_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ) );

        // Remove inactive devices not used in 90 days.
        $wpdb->query(
            "DELETE FROM {$this->tables['devices']}
            WHERE is_active = 0 AND last_used_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }
}
