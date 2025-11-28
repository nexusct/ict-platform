<?php
/**
 * Data Export & Backup
 *
 * Export platform data and create backups.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Data_Export {

    private static $instance = null;
    private $backups_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->backups_table = $wpdb->prefix . 'ict_backups';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
        add_action( 'ict_scheduled_backup', array( $this, 'run_scheduled_backup' ) );
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/export/(?P<type>[a-z_]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'export_data' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/export/all', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'export_all' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/backups', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_backups' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_backup' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/backups/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_backup' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_backup' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/backups/(?P<id>\d+)/download', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'download_backup' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/backups/(?P<id>\d+)/restore', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'restore_backup' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/backups/schedule', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_schedule' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_schedule' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'manage_ict_settings' ) || current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->backups_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            backup_type enum('full','partial','scheduled') DEFAULT 'full',
            tables_included text,
            record_counts text,
            is_encrypted tinyint(1) DEFAULT 0,
            status enum('pending','in_progress','completed','failed') DEFAULT 'pending',
            error_message text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY backup_type (backup_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function export_data( $request ) {
        global $wpdb;

        $type = sanitize_text_field( $request->get_param( 'type' ) );
        $format = $request->get_param( 'format' ) ?: 'json';
        $filters = $request->get_param( 'filters' ) ?: array();

        $exporters = array(
            'projects'        => array( $this, 'export_projects' ),
            'time_entries'    => array( $this, 'export_time_entries' ),
            'inventory'       => array( $this, 'export_inventory' ),
            'purchase_orders' => array( $this, 'export_purchase_orders' ),
            'suppliers'       => array( $this, 'export_suppliers' ),
            'users'           => array( $this, 'export_users' ),
            'documents'       => array( $this, 'export_documents' ),
        );

        if ( ! isset( $exporters[ $type ] ) ) {
            return new WP_Error( 'invalid_type', 'Invalid export type', array( 'status' => 400 ) );
        }

        $data = call_user_func( $exporters[ $type ], $filters );

        if ( $format === 'csv' ) {
            return $this->format_csv( $data, $type );
        }

        return rest_ensure_response( array(
            'type'       => $type,
            'count'      => count( $data ),
            'exported_at' => current_time( 'mysql' ),
            'data'       => $data,
        ) );
    }

    public function export_all( $request ) {
        $types = $request->get_param( 'types' ) ?: array(
            'projects', 'time_entries', 'inventory', 'purchase_orders', 'suppliers'
        );

        $export = array(
            'version'     => ICT_VERSION,
            'exported_at' => current_time( 'mysql' ),
            'site_url'    => get_site_url(),
            'data'        => array(),
        );

        foreach ( $types as $type ) {
            $method = 'export_' . $type;
            if ( method_exists( $this, $method ) ) {
                $export['data'][ $type ] = $this->$method( array() );
            }
        }

        return rest_ensure_response( $export );
    }

    public function get_backups( $request ) {
        global $wpdb;

        $backups = $wpdb->get_results(
            "SELECT b.*, u.display_name as created_by_name
             FROM {$this->backups_table} b
             LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
             ORDER BY b.created_at DESC"
        );

        foreach ( $backups as $backup ) {
            $backup->tables_included = json_decode( $backup->tables_included, true );
            $backup->record_counts = json_decode( $backup->record_counts, true );
            $backup->file_size_formatted = size_format( $backup->file_size );
        }

        return rest_ensure_response( $backups );
    }

    public function get_backup( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->backups_table} WHERE id = %d", $id
        ) );

        if ( ! $backup ) {
            return new WP_Error( 'not_found', 'Backup not found', array( 'status' => 404 ) );
        }

        $backup->tables_included = json_decode( $backup->tables_included, true );
        $backup->record_counts = json_decode( $backup->record_counts, true );
        $backup->file_size_formatted = size_format( $backup->file_size );

        return rest_ensure_response( $backup );
    }

    public function create_backup( $request ) {
        global $wpdb;

        $name = sanitize_text_field( $request->get_param( 'name' ) ) ?: 'Backup ' . date( 'Y-m-d H:i' );
        $backup_type = $request->get_param( 'backup_type' ) ?: 'full';
        $tables = $request->get_param( 'tables' );
        $encrypt = (bool) $request->get_param( 'encrypt' );

        // Determine tables to backup
        $ict_tables = $this->get_ict_tables();
        if ( $backup_type === 'partial' && $tables ) {
            $tables_to_backup = array_intersect( $tables, $ict_tables );
        } else {
            $tables_to_backup = $ict_tables;
        }

        // Create backup record
        $file_name = 'ict-backup-' . date( 'Y-m-d-His' ) . '.json';
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/ict-backups/';

        if ( ! file_exists( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
            file_put_contents( $backup_dir . '.htaccess', 'deny from all' );
        }

        $file_path = $backup_dir . $file_name;

        $wpdb->insert( $this->backups_table, array(
            'name'            => $name,
            'file_name'       => $file_name,
            'file_path'       => $file_path,
            'backup_type'     => $backup_type,
            'tables_included' => wp_json_encode( $tables_to_backup ),
            'is_encrypted'    => $encrypt ? 1 : 0,
            'status'          => 'in_progress',
            'created_by'      => get_current_user_id(),
        ) );

        $backup_id = $wpdb->insert_id;

        // Create backup data
        try {
            $backup_data = array(
                'version'     => ICT_VERSION,
                'created_at'  => current_time( 'mysql' ),
                'tables'      => array(),
            );

            $record_counts = array();

            foreach ( $tables_to_backup as $table ) {
                $rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
                $backup_data['tables'][ $table ] = $rows;
                $record_counts[ $table ] = count( $rows );
            }

            $json = wp_json_encode( $backup_data, JSON_PRETTY_PRINT );

            if ( $encrypt ) {
                $json = $this->encrypt_data( $json );
            }

            file_put_contents( $file_path, $json );
            $file_size = filesize( $file_path );

            $wpdb->update( $this->backups_table, array(
                'file_size'     => $file_size,
                'record_counts' => wp_json_encode( $record_counts ),
                'status'        => 'completed',
            ), array( 'id' => $backup_id ) );

            return rest_ensure_response( array(
                'success' => true,
                'id'      => $backup_id,
                'size'    => size_format( $file_size ),
            ) );

        } catch ( Exception $e ) {
            $wpdb->update( $this->backups_table, array(
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ), array( 'id' => $backup_id ) );

            return new WP_Error( 'backup_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    public function delete_backup( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT file_path FROM {$this->backups_table} WHERE id = %d", $id
        ) );

        if ( ! $backup ) {
            return new WP_Error( 'not_found', 'Backup not found', array( 'status' => 404 ) );
        }

        // Delete file
        if ( file_exists( $backup->file_path ) ) {
            unlink( $backup->file_path );
        }

        $wpdb->delete( $this->backups_table, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function download_backup( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->backups_table} WHERE id = %d", $id
        ) );

        if ( ! $backup || ! file_exists( $backup->file_path ) ) {
            return new WP_Error( 'not_found', 'Backup file not found', array( 'status' => 404 ) );
        }

        $content = file_get_contents( $backup->file_path );

        return new WP_REST_Response( $content, 200, array(
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $backup->file_name . '"',
            'Content-Length' => strlen( $content ),
        ) );
    }

    public function restore_backup( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $confirm = $request->get_param( 'confirm' );

        if ( $confirm !== 'RESTORE' ) {
            return new WP_Error( 'confirmation_required',
                'Type RESTORE to confirm restoration',
                array( 'status' => 400 )
            );
        }

        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->backups_table} WHERE id = %d", $id
        ) );

        if ( ! $backup || ! file_exists( $backup->file_path ) ) {
            return new WP_Error( 'not_found', 'Backup file not found', array( 'status' => 404 ) );
        }

        try {
            $content = file_get_contents( $backup->file_path );

            if ( $backup->is_encrypted ) {
                $content = $this->decrypt_data( $content );
            }

            $data = json_decode( $content, true );

            if ( ! $data || ! isset( $data['tables'] ) ) {
                throw new Exception( 'Invalid backup format' );
            }

            // Restore each table
            $restored = array();
            foreach ( $data['tables'] as $table => $rows ) {
                // Verify table exists
                $table_exists = $wpdb->get_var( $wpdb->prepare(
                    "SHOW TABLES LIKE %s", $table
                ) );

                if ( ! $table_exists ) {
                    continue;
                }

                // Truncate and restore
                $wpdb->query( "TRUNCATE TABLE {$table}" );

                foreach ( $rows as $row ) {
                    $wpdb->insert( $table, $row );
                }

                $restored[ $table ] = count( $rows );
            }

            do_action( 'ict_backup_restored', $id, $restored );

            return rest_ensure_response( array(
                'success'  => true,
                'restored' => $restored,
            ) );

        } catch ( Exception $e ) {
            return new WP_Error( 'restore_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    public function get_schedule( $request ) {
        $schedule = get_option( 'ict_backup_schedule', array(
            'enabled'   => false,
            'frequency' => 'daily',
            'time'      => '03:00',
            'keep'      => 7,
            'encrypt'   => false,
        ) );

        $schedule['next_run'] = wp_next_scheduled( 'ict_scheduled_backup' );

        return rest_ensure_response( $schedule );
    }

    public function save_schedule( $request ) {
        $schedule = array(
            'enabled'   => (bool) $request->get_param( 'enabled' ),
            'frequency' => sanitize_text_field( $request->get_param( 'frequency' ) ) ?: 'daily',
            'time'      => sanitize_text_field( $request->get_param( 'time' ) ) ?: '03:00',
            'keep'      => (int) $request->get_param( 'keep' ) ?: 7,
            'encrypt'   => (bool) $request->get_param( 'encrypt' ),
        );

        update_option( 'ict_backup_schedule', $schedule );

        // Clear existing schedule
        wp_clear_scheduled_hook( 'ict_scheduled_backup' );

        // Set new schedule if enabled
        if ( $schedule['enabled'] ) {
            $time_parts = explode( ':', $schedule['time'] );
            $timestamp = strtotime( 'today ' . $schedule['time'] );

            if ( $timestamp < time() ) {
                $timestamp = strtotime( 'tomorrow ' . $schedule['time'] );
            }

            wp_schedule_event( $timestamp, $schedule['frequency'], 'ict_scheduled_backup' );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function run_scheduled_backup() {
        $schedule = get_option( 'ict_backup_schedule' );

        if ( ! $schedule || ! $schedule['enabled'] ) {
            return;
        }

        // Create backup
        $this->create_backup( new WP_REST_Request( 'POST', '/ict/v1/backups' ) );

        // Cleanup old backups
        $this->cleanup_old_backups( $schedule['keep'] );
    }

    // Export methods
    private function export_projects( $filters ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ict_projects ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    private function export_time_entries( $filters ) {
        global $wpdb;

        $where = '1=1';
        $values = array();

        if ( ! empty( $filters['start_date'] ) ) {
            $where .= ' AND start_time >= %s';
            $values[] = $filters['start_date'];
        }
        if ( ! empty( $filters['end_date'] ) ) {
            $where .= ' AND start_time <= %s';
            $values[] = $filters['end_date'] . ' 23:59:59';
        }

        $query = "SELECT t.*, u.display_name, p.name as project_name
                  FROM {$wpdb->prefix}ict_time_entries t
                  LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}ict_projects p ON t.project_id = p.id
                  WHERE {$where} ORDER BY t.start_time DESC";

        return ! empty( $values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A )
            : $wpdb->get_results( $query, ARRAY_A );
    }

    private function export_inventory( $filters ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ict_inventory_items ORDER BY name",
            ARRAY_A
        );
    }

    private function export_purchase_orders( $filters ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT po.*, s.name as supplier_name
             FROM {$wpdb->prefix}ict_purchase_orders po
             LEFT JOIN {$wpdb->prefix}ict_suppliers s ON po.supplier_id = s.id
             ORDER BY po.created_at DESC",
            ARRAY_A
        );
    }

    private function export_suppliers( $filters ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ict_suppliers ORDER BY name",
            ARRAY_A
        );
    }

    private function export_users( $filters ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT ID, user_login, user_email, display_name, user_registered
             FROM {$wpdb->users} ORDER BY display_name",
            ARRAY_A
        );
    }

    private function export_documents( $filters ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ict_documents ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    private function format_csv( $data, $type ) {
        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to export', array( 'status' => 404 ) );
        }

        $output = fopen( 'php://temp', 'r+' );
        fputcsv( $output, array_keys( $data[0] ) );

        foreach ( $data as $row ) {
            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return new WP_REST_Response( $csv, 200, array(
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$type}-export-" . date( 'Y-m-d' ) . '.csv"',
        ) );
    }

    private function get_ict_tables() {
        global $wpdb;

        $tables = $wpdb->get_col(
            "SHOW TABLES LIKE '{$wpdb->prefix}ict_%'"
        );

        return $tables;
    }

    private function encrypt_data( $data ) {
        $key = wp_salt( 'auth' );
        $iv = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
        return base64_encode( $iv . $encrypted );
    }

    private function decrypt_data( $data ) {
        $key = wp_salt( 'auth' );
        $data = base64_decode( $data );
        $iv = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );
        return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
    }

    private function cleanup_old_backups( $keep ) {
        global $wpdb;

        $old_backups = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, file_path FROM {$this->backups_table}
             WHERE backup_type = 'scheduled'
             ORDER BY created_at DESC
             LIMIT 1000 OFFSET %d",
            $keep
        ) );

        foreach ( $old_backups as $backup ) {
            if ( file_exists( $backup->file_path ) ) {
                unlink( $backup->file_path );
            }
            $wpdb->delete( $this->backups_table, array( 'id' => $backup->id ) );
        }
    }
}
