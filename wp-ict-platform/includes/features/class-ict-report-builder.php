<?php
/**
 * Custom Report Builder
 *
 * Create and save custom reports with drag-and-drop interface.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Report_Builder {

    private static $instance = null;
    private $reports_table;
    private $schedules_table;
    private $executions_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->reports_table = $wpdb->prefix . 'ict_custom_reports';
        $this->schedules_table = $wpdb->prefix . 'ict_report_schedules';
        $this->executions_table = $wpdb->prefix . 'ict_report_executions';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
        add_action( 'ict_run_scheduled_reports', array( $this, 'run_scheduled_reports' ) );

        if ( ! wp_next_scheduled( 'ict_run_scheduled_reports' ) ) {
            wp_schedule_event( time(), 'hourly', 'ict_run_scheduled_reports' );
        }
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/reports', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_reports' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_report' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/reports/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_report' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_report' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_report' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/reports/(?P<id>\d+)/run', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'run_report' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/reports/(?P<id>\d+)/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'export_report' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/reports/(?P<id>\d+)/schedule', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_schedule' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_schedule' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_schedule' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/reports/(?P<id>\d+)/history', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/reports/data-sources', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_data_sources' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/reports/templates', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_templates' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'view_ict_reports' ) || current_user_can( 'manage_options' );
    }

    public function check_edit_permission() {
        return current_user_can( 'manage_ict_reports' ) || current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->reports_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100),
            description text,
            report_type enum('table','chart','summary','combined') DEFAULT 'table',
            data_source varchar(100) NOT NULL,
            columns longtext,
            filters longtext,
            grouping longtext,
            sorting longtext,
            chart_config longtext,
            is_public tinyint(1) DEFAULT 0,
            is_favorite tinyint(1) DEFAULT 0,
            category varchar(100),
            access_roles text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY category (category)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->schedules_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_id bigint(20) unsigned NOT NULL,
            frequency enum('daily','weekly','monthly') NOT NULL,
            day_of_week int,
            day_of_month int,
            time_of_day time DEFAULT '08:00:00',
            export_format enum('pdf','csv','xlsx') DEFAULT 'pdf',
            recipients text,
            is_active tinyint(1) DEFAULT 1,
            last_run datetime,
            next_run datetime,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY next_run (next_run),
            KEY is_active (is_active)
        ) {$charset_collate};";

        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->executions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_id bigint(20) unsigned NOT NULL,
            schedule_id bigint(20) unsigned,
            parameters longtext,
            execution_time int,
            row_count int,
            file_path varchar(500),
            status enum('success','error') NOT NULL,
            error_message text,
            run_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
    }

    public function get_reports( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();

        $category = $request->get_param( 'category' );
        $favorites_only = $request->get_param( 'favorites' ) === 'true';

        $where = '(is_public = 1 OR created_by = %d)';
        $values = array( $user_id );

        if ( $category ) {
            $where .= ' AND category = %s';
            $values[] = $category;
        }
        if ( $favorites_only ) {
            $where .= ' AND is_favorite = 1';
        }

        $reports = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.display_name as created_by_name,
                    (SELECT COUNT(*) FROM {$this->executions_table} WHERE report_id = r.id) as run_count,
                    (SELECT created_at FROM {$this->executions_table} WHERE report_id = r.id ORDER BY created_at DESC LIMIT 1) as last_run
             FROM {$this->reports_table} r
             LEFT JOIN {$wpdb->users} u ON r.created_by = u.ID
             WHERE {$where}
             ORDER BY r.is_favorite DESC, r.name",
            $values
        ) );

        return rest_ensure_response( $reports );
    }

    public function get_report( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->reports_table} WHERE id = %d", $id
        ) );

        if ( ! $report ) {
            return new WP_Error( 'not_found', 'Report not found', array( 'status' => 404 ) );
        }

        $report->columns = json_decode( $report->columns, true );
        $report->filters = json_decode( $report->filters, true );
        $report->grouping = json_decode( $report->grouping, true );
        $report->sorting = json_decode( $report->sorting, true );
        $report->chart_config = json_decode( $report->chart_config, true );
        $report->access_roles = json_decode( $report->access_roles, true );

        return rest_ensure_response( $report );
    }

    public function create_report( $request ) {
        global $wpdb;

        $name = sanitize_text_field( $request->get_param( 'name' ) );

        $data = array(
            'name'         => $name,
            'slug'         => sanitize_title( $name ),
            'description'  => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'report_type'  => sanitize_text_field( $request->get_param( 'report_type' ) ?: 'table' ),
            'data_source'  => sanitize_text_field( $request->get_param( 'data_source' ) ),
            'columns'      => wp_json_encode( $request->get_param( 'columns' ) ?: array() ),
            'filters'      => wp_json_encode( $request->get_param( 'filters' ) ?: array() ),
            'grouping'     => wp_json_encode( $request->get_param( 'grouping' ) ?: array() ),
            'sorting'      => wp_json_encode( $request->get_param( 'sorting' ) ?: array() ),
            'chart_config' => wp_json_encode( $request->get_param( 'chart_config' ) ?: array() ),
            'is_public'    => (int) $request->get_param( 'is_public' ),
            'category'     => sanitize_text_field( $request->get_param( 'category' ) ),
            'access_roles' => wp_json_encode( $request->get_param( 'access_roles' ) ?: array() ),
            'created_by'   => get_current_user_id(),
        );

        $wpdb->insert( $this->reports_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function update_report( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $fields = array( 'name', 'description', 'report_type', 'data_source', 'category' );
        $data = array();

        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $json_fields = array( 'columns', 'filters', 'grouping', 'sorting', 'chart_config', 'access_roles' );
        foreach ( $json_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = wp_json_encode( $value );
            }
        }

        $bool_fields = array( 'is_public', 'is_favorite' );
        foreach ( $bool_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = (int) $value;
            }
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->reports_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_report( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $wpdb->delete( $this->schedules_table, array( 'report_id' => $id ) );
        $wpdb->delete( $this->executions_table, array( 'report_id' => $id ) );
        $wpdb->delete( $this->reports_table, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function run_report( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $parameters = $request->get_param( 'parameters' ) ?: array();

        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->reports_table} WHERE id = %d", $id
        ) );

        if ( ! $report ) {
            return new WP_Error( 'not_found', 'Report not found', array( 'status' => 404 ) );
        }

        $start_time = microtime( true );

        try {
            $data = $this->execute_report( $report, $parameters );
            $execution_time = round( ( microtime( true ) - $start_time ) * 1000 );

            // Log execution
            $wpdb->insert( $this->executions_table, array(
                'report_id'      => $id,
                'parameters'     => wp_json_encode( $parameters ),
                'execution_time' => $execution_time,
                'row_count'      => count( $data['rows'] ?? array() ),
                'status'         => 'success',
                'run_by'         => get_current_user_id(),
            ) );

            return rest_ensure_response( array(
                'success'        => true,
                'data'           => $data,
                'execution_time' => $execution_time,
            ) );

        } catch ( Exception $e ) {
            $wpdb->insert( $this->executions_table, array(
                'report_id'     => $id,
                'parameters'    => wp_json_encode( $parameters ),
                'status'        => 'error',
                'error_message' => $e->getMessage(),
                'run_by'        => get_current_user_id(),
            ) );

            return new WP_Error( 'execution_error', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    public function export_report( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $format = $request->get_param( 'format' ) ?: 'csv';
        $parameters = $request->get_param( 'parameters' ) ?: array();

        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->reports_table} WHERE id = %d", $id
        ) );

        if ( ! $report ) {
            return new WP_Error( 'not_found', 'Report not found', array( 'status' => 404 ) );
        }

        $data = $this->execute_report( $report, $parameters );

        switch ( $format ) {
            case 'csv':
                return $this->export_csv( $report, $data );
            case 'xlsx':
                return $this->export_xlsx( $report, $data );
            case 'pdf':
                return $this->export_pdf( $report, $data );
            default:
                return new WP_Error( 'invalid_format', 'Invalid export format', array( 'status' => 400 ) );
        }
    }

    public function get_schedule( $request ) {
        global $wpdb;
        $report_id = (int) $request->get_param( 'id' );

        $schedule = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->schedules_table} WHERE report_id = %d",
            $report_id
        ) );

        if ( $schedule ) {
            $schedule->recipients = json_decode( $schedule->recipients, true );
        }

        return rest_ensure_response( $schedule );
    }

    public function save_schedule( $request ) {
        global $wpdb;
        $report_id = (int) $request->get_param( 'id' );

        $data = array(
            'report_id'     => $report_id,
            'frequency'     => sanitize_text_field( $request->get_param( 'frequency' ) ),
            'day_of_week'   => (int) $request->get_param( 'day_of_week' ),
            'day_of_month'  => (int) $request->get_param( 'day_of_month' ),
            'time_of_day'   => sanitize_text_field( $request->get_param( 'time_of_day' ) ?: '08:00:00' ),
            'export_format' => sanitize_text_field( $request->get_param( 'export_format' ) ?: 'pdf' ),
            'recipients'    => wp_json_encode( $request->get_param( 'recipients' ) ?: array() ),
            'is_active'     => (int) $request->get_param( 'is_active' ),
            'created_by'    => get_current_user_id(),
        );

        $data['next_run'] = $this->calculate_next_run( $data );

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->schedules_table} WHERE report_id = %d",
            $report_id
        ) );

        if ( $existing ) {
            unset( $data['created_by'] );
            $wpdb->update( $this->schedules_table, $data, array( 'id' => $existing ) );
            return rest_ensure_response( array( 'success' => true, 'id' => $existing ) );
        }

        $wpdb->insert( $this->schedules_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function delete_schedule( $request ) {
        global $wpdb;
        $report_id = (int) $request->get_param( 'id' );

        $wpdb->delete( $this->schedules_table, array( 'report_id' => $report_id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_history( $request ) {
        global $wpdb;
        $report_id = (int) $request->get_param( 'id' );
        $limit = (int) $request->get_param( 'limit' ) ?: 20;

        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, u.display_name as run_by_name
             FROM {$this->executions_table} e
             LEFT JOIN {$wpdb->users} u ON e.run_by = u.ID
             WHERE e.report_id = %d
             ORDER BY e.created_at DESC LIMIT %d",
            $report_id, $limit
        ) );

        return rest_ensure_response( $history );
    }

    public function get_data_sources( $request ) {
        $sources = array(
            array(
                'id'          => 'projects',
                'name'        => 'Projects',
                'description' => 'Project data including status, dates, and financials',
                'fields'      => array(
                    array( 'id' => 'id', 'name' => 'ID', 'type' => 'number' ),
                    array( 'id' => 'project_number', 'name' => 'Project Number', 'type' => 'text' ),
                    array( 'id' => 'name', 'name' => 'Name', 'type' => 'text' ),
                    array( 'id' => 'status', 'name' => 'Status', 'type' => 'select' ),
                    array( 'id' => 'start_date', 'name' => 'Start Date', 'type' => 'date' ),
                    array( 'id' => 'end_date', 'name' => 'End Date', 'type' => 'date' ),
                    array( 'id' => 'total_amount', 'name' => 'Total Amount', 'type' => 'currency' ),
                    array( 'id' => 'budget', 'name' => 'Budget', 'type' => 'currency' ),
                    array( 'id' => 'progress', 'name' => 'Progress', 'type' => 'percentage' ),
                ),
            ),
            array(
                'id'          => 'time_entries',
                'name'        => 'Time Entries',
                'description' => 'Time tracking data with hours and billing',
                'fields'      => array(
                    array( 'id' => 'user_id', 'name' => 'User', 'type' => 'user' ),
                    array( 'id' => 'project_id', 'name' => 'Project', 'type' => 'project' ),
                    array( 'id' => 'start_time', 'name' => 'Start Time', 'type' => 'datetime' ),
                    array( 'id' => 'end_time', 'name' => 'End Time', 'type' => 'datetime' ),
                    array( 'id' => 'hours', 'name' => 'Hours', 'type' => 'number' ),
                    array( 'id' => 'is_billable', 'name' => 'Billable', 'type' => 'boolean' ),
                ),
            ),
            array(
                'id'          => 'inventory',
                'name'        => 'Inventory Items',
                'description' => 'Stock levels and inventory data',
                'fields'      => array(
                    array( 'id' => 'sku', 'name' => 'SKU', 'type' => 'text' ),
                    array( 'id' => 'name', 'name' => 'Name', 'type' => 'text' ),
                    array( 'id' => 'quantity', 'name' => 'Quantity', 'type' => 'number' ),
                    array( 'id' => 'unit_cost', 'name' => 'Unit Cost', 'type' => 'currency' ),
                    array( 'id' => 'reorder_point', 'name' => 'Reorder Point', 'type' => 'number' ),
                ),
            ),
            array(
                'id'          => 'purchase_orders',
                'name'        => 'Purchase Orders',
                'description' => 'PO data with suppliers and amounts',
                'fields'      => array(
                    array( 'id' => 'po_number', 'name' => 'PO Number', 'type' => 'text' ),
                    array( 'id' => 'supplier_id', 'name' => 'Supplier', 'type' => 'supplier' ),
                    array( 'id' => 'total_amount', 'name' => 'Total Amount', 'type' => 'currency' ),
                    array( 'id' => 'status', 'name' => 'Status', 'type' => 'select' ),
                    array( 'id' => 'order_date', 'name' => 'Order Date', 'type' => 'date' ),
                ),
            ),
        );

        return rest_ensure_response( $sources );
    }

    public function get_templates( $request ) {
        $templates = array(
            array(
                'id'          => 'project_summary',
                'name'        => 'Project Summary',
                'description' => 'Overview of all projects with status and financials',
                'data_source' => 'projects',
                'columns'     => array( 'project_number', 'name', 'status', 'total_amount', 'progress' ),
                'report_type' => 'table',
            ),
            array(
                'id'          => 'time_by_project',
                'name'        => 'Time by Project',
                'description' => 'Hours logged per project',
                'data_source' => 'time_entries',
                'grouping'    => array( 'project_id' ),
                'report_type' => 'chart',
                'chart_config' => array( 'type' => 'bar' ),
            ),
            array(
                'id'          => 'inventory_status',
                'name'        => 'Inventory Status',
                'description' => 'Current stock levels with reorder alerts',
                'data_source' => 'inventory',
                'columns'     => array( 'sku', 'name', 'quantity', 'reorder_point' ),
                'report_type' => 'table',
            ),
            array(
                'id'          => 'employee_utilization',
                'name'        => 'Employee Utilization',
                'description' => 'Billable vs non-billable hours by employee',
                'data_source' => 'time_entries',
                'grouping'    => array( 'user_id' ),
                'report_type' => 'combined',
            ),
        );

        return rest_ensure_response( $templates );
    }

    public function run_scheduled_reports() {
        global $wpdb;

        $schedules = $wpdb->get_results(
            "SELECT s.*, r.name as report_name
             FROM {$this->schedules_table} s
             JOIN {$this->reports_table} r ON s.report_id = r.id
             WHERE s.is_active = 1 AND s.next_run <= NOW()"
        );

        foreach ( $schedules as $schedule ) {
            $report = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$this->reports_table} WHERE id = %d",
                $schedule->report_id
            ) );

            if ( ! $report ) {
                continue;
            }

            try {
                $data = $this->execute_report( $report, array() );

                // Generate export file
                $file_path = $this->generate_export_file( $report, $data, $schedule->export_format );

                // Send to recipients
                $recipients = json_decode( $schedule->recipients, true );
                $this->send_report_email( $report, $file_path, $recipients );

                // Log execution
                $wpdb->insert( $this->executions_table, array(
                    'report_id'   => $report->id,
                    'schedule_id' => $schedule->id,
                    'row_count'   => count( $data['rows'] ?? array() ),
                    'file_path'   => $file_path,
                    'status'      => 'success',
                ) );

                // Update schedule
                $wpdb->update( $this->schedules_table, array(
                    'last_run' => current_time( 'mysql' ),
                    'next_run' => $this->calculate_next_run( (array) $schedule ),
                ), array( 'id' => $schedule->id ) );

            } catch ( Exception $e ) {
                $wpdb->insert( $this->executions_table, array(
                    'report_id'     => $report->id,
                    'schedule_id'   => $schedule->id,
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                ) );
            }
        }
    }

    private function execute_report( $report, $parameters ) {
        global $wpdb;

        $columns = json_decode( $report->columns, true ) ?: array( '*' );
        $filters = json_decode( $report->filters, true ) ?: array();
        $grouping = json_decode( $report->grouping, true ) ?: array();
        $sorting = json_decode( $report->sorting, true ) ?: array();

        // Merge runtime parameters with saved filters
        $filters = array_merge( $filters, $parameters );

        $table = $this->get_table_for_source( $report->data_source );
        if ( ! $table ) {
            throw new Exception( 'Invalid data source' );
        }

        // Build query
        $select = $columns === array( '*' ) ? '*' : implode( ', ', array_map( 'esc_sql', $columns ) );

        $where_clauses = array( '1=1' );
        $where_values = array();

        foreach ( $filters as $filter ) {
            if ( isset( $filter['field'] ) && isset( $filter['value'] ) ) {
                $operator = $filter['operator'] ?? '=';
                $field = esc_sql( $filter['field'] );

                switch ( $operator ) {
                    case 'like':
                        $where_clauses[] = "{$field} LIKE %s";
                        $where_values[] = '%' . $wpdb->esc_like( $filter['value'] ) . '%';
                        break;
                    case 'between':
                        $where_clauses[] = "{$field} BETWEEN %s AND %s";
                        $where_values[] = $filter['value'][0];
                        $where_values[] = $filter['value'][1];
                        break;
                    case 'in':
                        $placeholders = implode( ',', array_fill( 0, count( $filter['value'] ), '%s' ) );
                        $where_clauses[] = "{$field} IN ({$placeholders})";
                        $where_values = array_merge( $where_values, $filter['value'] );
                        break;
                    default:
                        $where_clauses[] = "{$field} {$operator} %s";
                        $where_values[] = $filter['value'];
                }
            }
        }

        $where_sql = implode( ' AND ', $where_clauses );

        $group_sql = '';
        if ( ! empty( $grouping ) ) {
            $group_sql = 'GROUP BY ' . implode( ', ', array_map( 'esc_sql', $grouping ) );
        }

        $order_sql = '';
        if ( ! empty( $sorting ) ) {
            $order_parts = array();
            foreach ( $sorting as $sort ) {
                $dir = strtoupper( $sort['direction'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
                $order_parts[] = esc_sql( $sort['field'] ) . ' ' . $dir;
            }
            $order_sql = 'ORDER BY ' . implode( ', ', $order_parts );
        }

        $query = "SELECT {$select} FROM {$table} WHERE {$where_sql} {$group_sql} {$order_sql}";

        $rows = ! empty( $where_values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $where_values ), ARRAY_A )
            : $wpdb->get_results( $query, ARRAY_A );

        // Calculate summary if needed
        $summary = array();
        if ( $report->report_type === 'summary' || $report->report_type === 'combined' ) {
            $summary = $this->calculate_summary( $rows, $columns );
        }

        return array(
            'rows'    => $rows,
            'summary' => $summary,
            'count'   => count( $rows ),
        );
    }

    private function get_table_for_source( $source ) {
        global $wpdb;

        $tables = array(
            'projects'        => $wpdb->prefix . 'ict_projects',
            'time_entries'    => $wpdb->prefix . 'ict_time_entries',
            'inventory'       => $wpdb->prefix . 'ict_inventory_items',
            'purchase_orders' => $wpdb->prefix . 'ict_purchase_orders',
            'suppliers'       => $wpdb->prefix . 'ict_suppliers',
        );

        return $tables[ $source ] ?? null;
    }

    private function calculate_summary( $rows, $columns ) {
        $summary = array();

        foreach ( $rows as $row ) {
            foreach ( $row as $key => $value ) {
                if ( is_numeric( $value ) ) {
                    if ( ! isset( $summary[ $key ] ) ) {
                        $summary[ $key ] = array( 'sum' => 0, 'count' => 0, 'min' => $value, 'max' => $value );
                    }
                    $summary[ $key ]['sum'] += $value;
                    $summary[ $key ]['count']++;
                    $summary[ $key ]['min'] = min( $summary[ $key ]['min'], $value );
                    $summary[ $key ]['max'] = max( $summary[ $key ]['max'], $value );
                }
            }
        }

        foreach ( $summary as $key => $stats ) {
            $summary[ $key ]['avg'] = $stats['count'] > 0 ? $stats['sum'] / $stats['count'] : 0;
        }

        return $summary;
    }

    private function export_csv( $report, $data ) {
        $output = fopen( 'php://temp', 'r+' );

        // Header row
        if ( ! empty( $data['rows'] ) ) {
            fputcsv( $output, array_keys( $data['rows'][0] ) );

            foreach ( $data['rows'] as $row ) {
                fputcsv( $output, $row );
            }
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return new WP_REST_Response( $csv, 200, array(
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . sanitize_file_name( $report->name ) . '.csv"',
        ) );
    }

    private function export_xlsx( $report, $data ) {
        // Would use PhpSpreadsheet library
        return new WP_Error( 'not_implemented', 'XLSX export requires PhpSpreadsheet', array( 'status' => 501 ) );
    }

    private function export_pdf( $report, $data ) {
        // Would use TCPDF or DOMPDF library
        return new WP_Error( 'not_implemented', 'PDF export requires PDF library', array( 'status' => 501 ) );
    }

    private function generate_export_file( $report, $data, $format ) {
        $upload_dir = wp_upload_dir();
        $reports_dir = $upload_dir['basedir'] . '/ict-reports/';

        if ( ! file_exists( $reports_dir ) ) {
            wp_mkdir_p( $reports_dir );
        }

        $filename = sanitize_file_name( $report->name ) . '-' . date( 'Y-m-d-His' ) . '.' . $format;
        $file_path = $reports_dir . $filename;

        // Generate file based on format
        if ( $format === 'csv' ) {
            $output = fopen( $file_path, 'w' );
            if ( ! empty( $data['rows'] ) ) {
                fputcsv( $output, array_keys( $data['rows'][0] ) );
                foreach ( $data['rows'] as $row ) {
                    fputcsv( $output, $row );
                }
            }
            fclose( $output );
        }

        return $file_path;
    }

    private function send_report_email( $report, $file_path, $recipients ) {
        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf( 'Scheduled Report: %s - %s', $report->name, date( 'Y-m-d' ) );
        $message = sprintf(
            "Your scheduled report '%s' is attached.\n\nGenerated: %s",
            $report->name,
            current_time( 'mysql' )
        );

        foreach ( $recipients as $email ) {
            wp_mail( $email, $subject, $message, array(), array( $file_path ) );
        }
    }

    private function calculate_next_run( $schedule ) {
        $now = time();

        switch ( $schedule['frequency'] ) {
            case 'daily':
                $next = strtotime( 'tomorrow ' . $schedule['time_of_day'] );
                break;

            case 'weekly':
                $day = $schedule['day_of_week'] ?? 1;
                $day_name = array( 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                                   4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' );
                $next = strtotime( 'next ' . $day_name[ $day ] . ' ' . $schedule['time_of_day'] );
                break;

            case 'monthly':
                $day = $schedule['day_of_month'] ?? 1;
                $next = strtotime( date( 'Y-m-' . sprintf( '%02d', $day ) . ' ' . $schedule['time_of_day'], strtotime( '+1 month' ) ) );
                break;

            default:
                $next = strtotime( '+1 day' );
        }

        return date( 'Y-m-d H:i:s', $next );
    }
}
