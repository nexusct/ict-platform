<?php
/**
 * Audit Logging System
 *
 * Comprehensive audit trail for all platform actions.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Audit_Log {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ict_audit_log';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );

        // Hook into various actions
        add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
        add_action( 'wp_logout', array( $this, 'log_logout' ) );
        add_action( 'wp_login_failed', array( $this, 'log_login_failed' ) );

        // Project actions
        add_action( 'ict_project_created', array( $this, 'log_project_created' ), 10, 2 );
        add_action( 'ict_project_updated', array( $this, 'log_project_updated' ), 10, 3 );
        add_action( 'ict_project_deleted', array( $this, 'log_project_deleted' ), 10, 2 );

        // Time entry actions
        add_action( 'ict_time_entry_created', array( $this, 'log_time_entry' ), 10, 2 );
        add_action( 'ict_clock_in', array( $this, 'log_clock_in' ), 10, 2 );
        add_action( 'ict_clock_out', array( $this, 'log_clock_out' ), 10, 2 );

        // Inventory actions
        add_action( 'ict_inventory_adjusted', array( $this, 'log_inventory_adjustment' ), 10, 3 );
        add_action( 'ict_po_created', array( $this, 'log_po_created' ), 10, 2 );
        add_action( 'ict_po_approved', array( $this, 'log_po_approved' ), 10, 2 );

        // Settings changes
        add_action( 'update_option', array( $this, 'log_option_change' ), 10, 3 );

        // User changes
        add_action( 'profile_update', array( $this, 'log_profile_update' ), 10, 2 );
        add_action( 'set_user_role', array( $this, 'log_role_change' ), 10, 3 );
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/audit-log', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_logs' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/audit-log/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_log' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/audit-log/summary', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_summary' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/audit-log/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'export_logs' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/audit-log/user/(?P<user_id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_user_logs' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/audit-log/entity/(?P<type>[a-z_]+)/(?P<entity_id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_entity_logs' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'view_ict_audit_log' ) || current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            user_name varchar(100),
            action varchar(100) NOT NULL,
            action_group varchar(50),
            entity_type varchar(50),
            entity_id bigint(20) unsigned,
            entity_name varchar(255),
            old_values longtext,
            new_values longtext,
            description text,
            ip_address varchar(45),
            user_agent varchar(500),
            request_uri varchar(500),
            severity enum('info','warning','error','critical') DEFAULT 'info',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY action_group (action_group),
            KEY entity_type_id (entity_type, entity_id),
            KEY created_at (created_at),
            KEY severity (severity)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function get_logs( $request ) {
        global $wpdb;

        $filters = array(
            'user_id'      => $request->get_param( 'user_id' ),
            'action'       => $request->get_param( 'action' ),
            'action_group' => $request->get_param( 'action_group' ),
            'entity_type'  => $request->get_param( 'entity_type' ),
            'severity'     => $request->get_param( 'severity' ),
            'start_date'   => $request->get_param( 'start_date' ),
            'end_date'     => $request->get_param( 'end_date' ),
            'search'       => $request->get_param( 'search' ),
        );

        $limit = (int) $request->get_param( 'limit' ) ?: 50;
        $offset = (int) $request->get_param( 'offset' ) ?: 0;

        $where = array( '1=1' );
        $values = array();

        if ( $filters['user_id'] ) {
            $where[] = 'user_id = %d';
            $values[] = (int) $filters['user_id'];
        }
        if ( $filters['action'] ) {
            $where[] = 'action = %s';
            $values[] = $filters['action'];
        }
        if ( $filters['action_group'] ) {
            $where[] = 'action_group = %s';
            $values[] = $filters['action_group'];
        }
        if ( $filters['entity_type'] ) {
            $where[] = 'entity_type = %s';
            $values[] = $filters['entity_type'];
        }
        if ( $filters['severity'] ) {
            $where[] = 'severity = %s';
            $values[] = $filters['severity'];
        }
        if ( $filters['start_date'] ) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['start_date'];
        }
        if ( $filters['end_date'] ) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['end_date'] . ' 23:59:59';
        }
        if ( $filters['search'] ) {
            $where[] = '(description LIKE %s OR entity_name LIKE %s OR user_name LIKE %s)';
            $s = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $values[] = $s;
            $values[] = $s;
            $values[] = $s;
        }

        $where_clause = implode( ' AND ', $where );
        $values[] = $limit;
        $values[] = $offset;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE {$where_clause}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $values
        ) );

        // Get total count
        array_pop( $values ); // Remove offset
        array_pop( $values ); // Remove limit

        $total = ! empty( $values )
            ? $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}",
                $values
            ) )
            : $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}" );

        foreach ( $logs as $log ) {
            $log->old_values = json_decode( $log->old_values, true );
            $log->new_values = json_decode( $log->new_values, true );
        }

        return rest_ensure_response( array(
            'logs'  => $logs,
            'total' => (int) $total,
        ) );
    }

    public function get_log( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $log = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d", $id
        ) );

        if ( ! $log ) {
            return new WP_Error( 'not_found', 'Log entry not found', array( 'status' => 404 ) );
        }

        $log->old_values = json_decode( $log->old_values, true );
        $log->new_values = json_decode( $log->new_values, true );

        return rest_ensure_response( $log );
    }

    public function get_summary( $request ) {
        global $wpdb;

        $days = (int) $request->get_param( 'days' ) ?: 7;
        $start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        // Actions by group
        $by_group = $wpdb->get_results( $wpdb->prepare(
            "SELECT action_group, COUNT(*) as count
             FROM {$this->table_name}
             WHERE created_at >= %s
             GROUP BY action_group ORDER BY count DESC",
            $start_date
        ) );

        // Actions by user
        $by_user = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, user_name, COUNT(*) as count
             FROM {$this->table_name}
             WHERE created_at >= %s AND user_id IS NOT NULL
             GROUP BY user_id, user_name ORDER BY count DESC LIMIT 10",
            $start_date
        ) );

        // By severity
        $by_severity = $wpdb->get_results( $wpdb->prepare(
            "SELECT severity, COUNT(*) as count
             FROM {$this->table_name}
             WHERE created_at >= %s
             GROUP BY severity",
            $start_date
        ) );

        // Daily activity
        $daily = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$this->table_name}
             WHERE created_at >= %s
             GROUP BY DATE(created_at) ORDER BY date",
            $start_date
        ) );

        // Recent critical/warning events
        $alerts = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE severity IN ('warning', 'error', 'critical')
             AND created_at >= %s
             ORDER BY created_at DESC LIMIT 10",
            $start_date
        ) );

        return rest_ensure_response( array(
            'by_group'    => $by_group,
            'by_user'     => $by_user,
            'by_severity' => $by_severity,
            'daily'       => $daily,
            'alerts'      => $alerts,
            'total'       => $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
                $start_date
            ) ),
        ) );
    }

    public function export_logs( $request ) {
        global $wpdb;

        $start_date = $request->get_param( 'start_date' ) ?: date( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date = $request->get_param( 'end_date' ) ?: date( 'Y-m-d' );
        $format = $request->get_param( 'format' ) ?: 'csv';

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, user_name, action, action_group, entity_type, entity_name,
                    description, ip_address, severity, created_at
             FROM {$this->table_name}
             WHERE created_at BETWEEN %s AND %s
             ORDER BY created_at DESC",
            $start_date, $end_date . ' 23:59:59'
        ), ARRAY_A );

        if ( $format === 'csv' ) {
            $output = fopen( 'php://temp', 'r+' );
            fputcsv( $output, array_keys( $logs[0] ?? array() ) );
            foreach ( $logs as $log ) {
                fputcsv( $output, $log );
            }
            rewind( $output );
            $csv = stream_get_contents( $output );
            fclose( $output );

            return new WP_REST_Response( $csv, 200, array(
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="audit-log-' . date( 'Y-m-d' ) . '.csv"',
            ) );
        }

        return rest_ensure_response( $logs );
    }

    public function get_user_logs( $request ) {
        global $wpdb;
        $user_id = (int) $request->get_param( 'user_id' );
        $limit = (int) $request->get_param( 'limit' ) ?: 50;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ) );

        return rest_ensure_response( $logs );
    }

    public function get_entity_logs( $request ) {
        global $wpdb;
        $entity_type = sanitize_text_field( $request->get_param( 'type' ) );
        $entity_id = (int) $request->get_param( 'entity_id' );
        $limit = (int) $request->get_param( 'limit' ) ?: 50;

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE entity_type = %s AND entity_id = %d
             ORDER BY created_at DESC LIMIT %d",
            $entity_type, $entity_id, $limit
        ) );

        return rest_ensure_response( $logs );
    }

    // Logging methods
    public function log( $action, $data = array() ) {
        global $wpdb;

        $user = wp_get_current_user();

        $entry = array(
            'user_id'      => $user->ID ?: null,
            'user_name'    => $user->display_name ?: 'System',
            'action'       => $action,
            'action_group' => $data['action_group'] ?? $this->get_action_group( $action ),
            'entity_type'  => $data['entity_type'] ?? null,
            'entity_id'    => $data['entity_id'] ?? null,
            'entity_name'  => $data['entity_name'] ?? null,
            'old_values'   => isset( $data['old_values'] ) ? wp_json_encode( $data['old_values'] ) : null,
            'new_values'   => isset( $data['new_values'] ) ? wp_json_encode( $data['new_values'] ) : null,
            'description'  => $data['description'] ?? $this->generate_description( $action, $data ),
            'ip_address'   => $this->get_client_ip(),
            'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : null,
            'request_uri'  => isset( $_SERVER['REQUEST_URI'] ) ? substr( $_SERVER['REQUEST_URI'], 0, 500 ) : null,
            'severity'     => $data['severity'] ?? 'info',
        );

        $wpdb->insert( $this->table_name, $entry );

        do_action( 'ict_audit_logged', $action, $entry );
    }

    public function log_login( $user_login, $user ) {
        $this->log( 'user_login', array(
            'action_group' => 'authentication',
            'entity_type'  => 'user',
            'entity_id'    => $user->ID,
            'entity_name'  => $user->display_name,
            'description'  => "User {$user->display_name} logged in",
        ) );
    }

    public function log_logout() {
        $user = wp_get_current_user();
        $this->log( 'user_logout', array(
            'action_group' => 'authentication',
            'entity_type'  => 'user',
            'entity_id'    => $user->ID,
            'entity_name'  => $user->display_name,
            'description'  => "User {$user->display_name} logged out",
        ) );
    }

    public function log_login_failed( $username ) {
        $this->log( 'login_failed', array(
            'action_group' => 'authentication',
            'severity'     => 'warning',
            'description'  => "Failed login attempt for username: {$username}",
            'new_values'   => array( 'username' => $username ),
        ) );
    }

    public function log_project_created( $project_id, $data ) {
        $this->log( 'project_created', array(
            'action_group' => 'projects',
            'entity_type'  => 'project',
            'entity_id'    => $project_id,
            'entity_name'  => $data['name'],
            'new_values'   => $data,
        ) );
    }

    public function log_project_updated( $project_id, $new_data, $old_data ) {
        $this->log( 'project_updated', array(
            'action_group' => 'projects',
            'entity_type'  => 'project',
            'entity_id'    => $project_id,
            'entity_name'  => $new_data['name'] ?? $old_data['name'],
            'old_values'   => $old_data,
            'new_values'   => $new_data,
        ) );
    }

    public function log_project_deleted( $project_id, $data ) {
        $this->log( 'project_deleted', array(
            'action_group' => 'projects',
            'entity_type'  => 'project',
            'entity_id'    => $project_id,
            'entity_name'  => $data['name'],
            'old_values'   => $data,
            'severity'     => 'warning',
        ) );
    }

    public function log_time_entry( $entry_id, $data ) {
        $this->log( 'time_entry_created', array(
            'action_group' => 'time_tracking',
            'entity_type'  => 'time_entry',
            'entity_id'    => $entry_id,
            'new_values'   => $data,
        ) );
    }

    public function log_clock_in( $user_id, $entry_id ) {
        $user = get_userdata( $user_id );
        $this->log( 'clock_in', array(
            'action_group' => 'time_tracking',
            'entity_type'  => 'time_entry',
            'entity_id'    => $entry_id,
            'description'  => "User {$user->display_name} clocked in",
        ) );
    }

    public function log_clock_out( $user_id, $entry_id ) {
        $user = get_userdata( $user_id );
        $this->log( 'clock_out', array(
            'action_group' => 'time_tracking',
            'entity_type'  => 'time_entry',
            'entity_id'    => $entry_id,
            'description'  => "User {$user->display_name} clocked out",
        ) );
    }

    public function log_inventory_adjustment( $item_id, $old_qty, $new_qty ) {
        $this->log( 'inventory_adjusted', array(
            'action_group' => 'inventory',
            'entity_type'  => 'inventory_item',
            'entity_id'    => $item_id,
            'old_values'   => array( 'quantity' => $old_qty ),
            'new_values'   => array( 'quantity' => $new_qty ),
            'description'  => "Inventory adjusted from {$old_qty} to {$new_qty}",
        ) );
    }

    public function log_po_created( $po_id, $data ) {
        $this->log( 'po_created', array(
            'action_group' => 'procurement',
            'entity_type'  => 'purchase_order',
            'entity_id'    => $po_id,
            'entity_name'  => $data['po_number'],
            'new_values'   => $data,
        ) );
    }

    public function log_po_approved( $po_id, $data ) {
        $this->log( 'po_approved', array(
            'action_group' => 'procurement',
            'entity_type'  => 'purchase_order',
            'entity_id'    => $po_id,
            'entity_name'  => $data['po_number'],
        ) );
    }

    public function log_option_change( $option, $old_value, $new_value ) {
        if ( strpos( $option, 'ict_' ) !== 0 ) {
            return;
        }

        $this->log( 'setting_changed', array(
            'action_group' => 'settings',
            'entity_type'  => 'option',
            'entity_name'  => $option,
            'old_values'   => array( 'value' => $old_value ),
            'new_values'   => array( 'value' => $new_value ),
        ) );
    }

    public function log_profile_update( $user_id, $old_user_data ) {
        $user = get_userdata( $user_id );
        $this->log( 'profile_updated', array(
            'action_group' => 'users',
            'entity_type'  => 'user',
            'entity_id'    => $user_id,
            'entity_name'  => $user->display_name,
        ) );
    }

    public function log_role_change( $user_id, $role, $old_roles ) {
        $user = get_userdata( $user_id );
        $this->log( 'role_changed', array(
            'action_group' => 'users',
            'entity_type'  => 'user',
            'entity_id'    => $user_id,
            'entity_name'  => $user->display_name,
            'old_values'   => array( 'roles' => $old_roles ),
            'new_values'   => array( 'role' => $role ),
            'severity'     => 'warning',
        ) );
    }

    private function get_action_group( $action ) {
        $groups = array(
            'login'     => 'authentication',
            'logout'    => 'authentication',
            'project'   => 'projects',
            'time'      => 'time_tracking',
            'clock'     => 'time_tracking',
            'inventory' => 'inventory',
            'po_'       => 'procurement',
            'setting'   => 'settings',
            'user'      => 'users',
            'role'      => 'users',
        );

        foreach ( $groups as $prefix => $group ) {
            if ( strpos( $action, $prefix ) !== false ) {
                return $group;
            }
        }

        return 'general';
    }

    private function generate_description( $action, $data ) {
        $user = wp_get_current_user();
        $user_name = $user->display_name ?: 'System';
        $entity_name = $data['entity_name'] ?? '';

        $templates = array(
            'project_created'   => "{$user_name} created project '{$entity_name}'",
            'project_updated'   => "{$user_name} updated project '{$entity_name}'",
            'project_deleted'   => "{$user_name} deleted project '{$entity_name}'",
            'time_entry_created' => "{$user_name} logged time entry",
            'clock_in'          => "{$user_name} clocked in",
            'clock_out'         => "{$user_name} clocked out",
            'inventory_adjusted' => "{$user_name} adjusted inventory for '{$entity_name}'",
            'po_created'        => "{$user_name} created purchase order '{$entity_name}'",
            'po_approved'       => "{$user_name} approved purchase order '{$entity_name}'",
        );

        return $templates[ $action ] ?? "{$user_name} performed action: {$action}";
    }

    private function get_client_ip() {
        $ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = $_SERVER[ $key ];
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = explode( ',', $ip )[0];
                }
                return trim( $ip );
            }
        }

        return '';
    }
}
