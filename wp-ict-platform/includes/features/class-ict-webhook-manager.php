<?php
/**
 * Webhook Manager
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Webhook_Manager {

    private static $instance = null;
    private $webhooks_table;
    private $logs_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->webhooks_table = $wpdb->prefix . 'ict_webhooks';
        $this->logs_table = $wpdb->prefix . 'ict_webhook_logs';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
        $this->register_event_hooks();
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/webhooks', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_webhooks' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_webhook' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/webhooks/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_webhook' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_webhook' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_webhook' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/webhooks/(?P<id>\d+)/test', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'test_webhook' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/webhooks/(?P<id>\d+)/logs', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_logs' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/webhooks/events', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_events' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/webhooks/(?P<id>\d+)/toggle', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'toggle_webhook' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->webhooks_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            secret varchar(255),
            events text NOT NULL,
            headers text,
            is_active tinyint(1) DEFAULT 1,
            retry_count int DEFAULT 3,
            timeout int DEFAULT 30,
            last_triggered datetime,
            last_status varchar(20),
            failure_count int DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            webhook_id bigint(20) unsigned NOT NULL,
            event varchar(100) NOT NULL,
            payload longtext,
            response_code int,
            response_body text,
            response_time_ms int,
            status enum('success','failed','pending') DEFAULT 'pending',
            error_message text,
            attempt int DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY event (event),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }

    private function register_event_hooks() {
        $events = $this->get_available_events();
        foreach ( $events as $event => $label ) {
            add_action( $event, function( ...$args ) use ( $event ) {
                $this->trigger_webhooks( $event, $args );
            }, 100, 10 );
        }
    }

    private function get_available_events() {
        return array(
            'ict_project_created'       => 'Project Created',
            'ict_project_updated'       => 'Project Updated',
            'ict_project_status_changed' => 'Project Status Changed',
            'ict_milestone_created'     => 'Milestone Created',
            'ict_milestone_completed'   => 'Milestone Completed',
            'ict_time_entry_created'    => 'Time Entry Created',
            'ict_clock_in'              => 'Clock In',
            'ict_clock_out'             => 'Clock Out',
            'ict_expense_created'       => 'Expense Created',
            'ict_expense_approved'      => 'Expense Approved',
            'ict_po_created'            => 'Purchase Order Created',
            'ict_po_fully_approved'     => 'Purchase Order Approved',
            'ict_po_rejected'           => 'Purchase Order Rejected',
            'ict_inventory_alert'       => 'Inventory Alert',
            'ict_document_uploaded'     => 'Document Uploaded',
            'ict_client_request_submitted' => 'Client Request Submitted',
            'ict_message_sent'          => 'Message Sent',
            'ict_announcement_published' => 'Announcement Published',
        );
    }

    public function trigger_webhooks( $event, $args ) {
        global $wpdb;

        $webhooks = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->webhooks_table}
             WHERE is_active = 1 AND events LIKE %s",
            '%' . $wpdb->esc_like( $event ) . '%'
        ) );

        foreach ( $webhooks as $webhook ) {
            $events = json_decode( $webhook->events, true );
            if ( ! in_array( $event, $events ) ) continue;

            $payload = array(
                'event'     => $event,
                'timestamp' => current_time( 'c' ),
                'data'      => $args,
            );

            $this->send_webhook( $webhook, $payload );
        }
    }

    private function send_webhook( $webhook, $payload, $attempt = 1 ) {
        global $wpdb;

        $start_time = microtime( true );

        $headers = array(
            'Content-Type' => 'application/json',
            'X-ICT-Event'  => $payload['event'],
            'X-ICT-Delivery' => wp_generate_uuid4(),
        );

        if ( $webhook->secret ) {
            $signature = hash_hmac( 'sha256', wp_json_encode( $payload ), $webhook->secret );
            $headers['X-ICT-Signature'] = 'sha256=' . $signature;
        }

        $custom_headers = json_decode( $webhook->headers, true );
        if ( $custom_headers ) {
            $headers = array_merge( $headers, $custom_headers );
        }

        $response = wp_remote_post( $webhook->url, array(
            'timeout'  => $webhook->timeout ?: 30,
            'headers'  => $headers,
            'body'     => wp_json_encode( $payload ),
        ) );

        $response_time = round( ( microtime( true ) - $start_time ) * 1000 );

        $log_data = array(
            'webhook_id'       => $webhook->id,
            'event'            => $payload['event'],
            'payload'          => wp_json_encode( $payload ),
            'response_time_ms' => $response_time,
            'attempt'          => $attempt,
        );

        if ( is_wp_error( $response ) ) {
            $log_data['status'] = 'failed';
            $log_data['error_message'] = $response->get_error_message();

            // Retry
            if ( $attempt < $webhook->retry_count ) {
                wp_schedule_single_event(
                    time() + ( $attempt * 60 ),
                    'ict_webhook_retry',
                    array( $webhook->id, $payload, $attempt + 1 )
                );
            } else {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$this->webhooks_table} SET failure_count = failure_count + 1 WHERE id = %d",
                    $webhook->id
                ) );
            }
        } else {
            $log_data['response_code'] = wp_remote_retrieve_response_code( $response );
            $log_data['response_body'] = substr( wp_remote_retrieve_body( $response ), 0, 5000 );
            $log_data['status'] = $log_data['response_code'] >= 200 && $log_data['response_code'] < 300 ? 'success' : 'failed';

            if ( $log_data['status'] === 'failed' && $attempt < $webhook->retry_count ) {
                wp_schedule_single_event(
                    time() + ( $attempt * 60 ),
                    'ict_webhook_retry',
                    array( $webhook->id, $payload, $attempt + 1 )
                );
            }
        }

        $wpdb->insert( $this->logs_table, $log_data );

        $wpdb->update( $this->webhooks_table, array(
            'last_triggered' => current_time( 'mysql' ),
            'last_status'    => $log_data['status'],
        ), array( 'id' => $webhook->id ) );
    }

    public function get_webhooks( $request ) {
        global $wpdb;

        $webhooks = $wpdb->get_results(
            "SELECT * FROM {$this->webhooks_table} ORDER BY created_at DESC"
        );

        foreach ( $webhooks as &$wh ) {
            $wh->events = json_decode( $wh->events, true );
            $wh->headers = json_decode( $wh->headers, true );
        }

        return rest_ensure_response( $webhooks );
    }

    public function get_webhook( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $webhook = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->webhooks_table} WHERE id = %d", $id
        ) );

        if ( ! $webhook ) {
            return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
        }

        $webhook->events = json_decode( $webhook->events, true );
        $webhook->headers = json_decode( $webhook->headers, true );

        return rest_ensure_response( $webhook );
    }

    public function create_webhook( $request ) {
        global $wpdb;

        $events = $request->get_param( 'events' );
        $headers = $request->get_param( 'headers' );

        $data = array(
            'name'        => sanitize_text_field( $request->get_param( 'name' ) ),
            'url'         => esc_url_raw( $request->get_param( 'url' ) ),
            'secret'      => sanitize_text_field( $request->get_param( 'secret' ) ),
            'events'      => wp_json_encode( $events ?: array() ),
            'headers'     => $headers ? wp_json_encode( $headers ) : null,
            'retry_count' => (int) $request->get_param( 'retry_count' ) ?: 3,
            'timeout'     => (int) $request->get_param( 'timeout' ) ?: 30,
            'created_by'  => get_current_user_id(),
        );

        if ( empty( $data['url'] ) ) {
            return new WP_Error( 'invalid_url', 'URL is required', array( 'status' => 400 ) );
        }

        $wpdb->insert( $this->webhooks_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function update_webhook( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $data = array();

        $text_fields = array( 'name', 'secret' );
        foreach ( $text_fields as $f ) {
            $v = $request->get_param( $f );
            if ( null !== $v ) $data[ $f ] = sanitize_text_field( $v );
        }

        $url = $request->get_param( 'url' );
        if ( null !== $url ) $data['url'] = esc_url_raw( $url );

        $events = $request->get_param( 'events' );
        if ( null !== $events ) $data['events'] = wp_json_encode( $events );

        $headers = $request->get_param( 'headers' );
        if ( null !== $headers ) $data['headers'] = wp_json_encode( $headers );

        $int_fields = array( 'retry_count', 'timeout', 'is_active' );
        foreach ( $int_fields as $f ) {
            $v = $request->get_param( $f );
            if ( null !== $v ) $data[ $f ] = (int) $v;
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->webhooks_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_webhook( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $wpdb->delete( $this->logs_table, array( 'webhook_id' => $id ) );
        $wpdb->delete( $this->webhooks_table, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function test_webhook( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $webhook = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->webhooks_table} WHERE id = %d", $id
        ) );

        if ( ! $webhook ) {
            return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
        }

        $payload = array(
            'event'     => 'test',
            'timestamp' => current_time( 'c' ),
            'data'      => array( 'message' => 'This is a test webhook delivery' ),
        );

        $this->send_webhook( $webhook, $payload );

        return rest_ensure_response( array( 'success' => true, 'message' => 'Test webhook sent' ) );
    }

    public function get_logs( $request ) {
        global $wpdb;
        $webhook_id = (int) $request->get_param( 'id' );
        $limit = (int) $request->get_param( 'limit' ) ?: 50;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->logs_table}
             WHERE webhook_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $webhook_id, $limit
        ) );

        foreach ( $logs as &$log ) {
            $log->payload = json_decode( $log->payload, true );
        }

        return rest_ensure_response( $logs );
    }

    public function get_events( $request ) {
        return rest_ensure_response( $this->get_available_events() );
    }

    public function toggle_webhook( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $webhook = $wpdb->get_row( $wpdb->prepare(
            "SELECT is_active FROM {$this->webhooks_table} WHERE id = %d", $id
        ) );

        if ( ! $webhook ) {
            return new WP_Error( 'not_found', 'Not found', array( 'status' => 404 ) );
        }

        $new_status = $webhook->is_active ? 0 : 1;
        $wpdb->update( $this->webhooks_table, array( 'is_active' => $new_status ), array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true, 'is_active' => $new_status ) );
    }
}
