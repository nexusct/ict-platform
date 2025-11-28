<?php
/**
 * KPI Tracking System
 *
 * Define, track, and visualize key performance indicators.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_KPI_Tracker {

    private static $instance = null;
    private $kpis_table;
    private $values_table;
    private $targets_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->kpis_table = $wpdb->prefix . 'ict_kpis';
        $this->values_table = $wpdb->prefix . 'ict_kpi_values';
        $this->targets_table = $wpdb->prefix . 'ict_kpi_targets';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
        add_action( 'ict_calculate_kpis', array( $this, 'calculate_all_kpis' ) );

        if ( ! wp_next_scheduled( 'ict_calculate_kpis' ) ) {
            wp_schedule_event( time(), 'daily', 'ict_calculate_kpis' );
        }
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/kpis', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_kpis' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_kpi' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_kpi' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_kpi' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_kpi' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/(?P<id>\d+)/values', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_values' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_value' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/(?P<id>\d+)/targets', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_targets' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'set_target' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/(?P<id>\d+)/calculate', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'calculate_kpi' ),
            'permission_callback' => array( $this, 'check_edit_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/dashboard', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_dashboard' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/trends', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_trends' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/kpis/comparison', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_comparison' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'view_ict_reports' ) || current_user_can( 'manage_options' );
    }

    public function check_edit_permission() {
        return current_user_can( 'manage_ict_settings' ) || current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->kpis_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            category enum('financial','operational','customer','employee') DEFAULT 'operational',
            data_type enum('number','percentage','currency','duration') DEFAULT 'number',
            calculation_type enum('manual','automatic','formula') DEFAULT 'automatic',
            calculation_formula text,
            data_source varchar(100),
            aggregation enum('sum','average','count','min','max','latest') DEFAULT 'sum',
            frequency enum('daily','weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
            unit varchar(20),
            decimal_places int DEFAULT 2,
            is_higher_better tinyint(1) DEFAULT 1,
            warning_threshold decimal(15,4),
            critical_threshold decimal(15,4),
            display_order int DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            show_on_dashboard tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category (category),
            KEY is_active (is_active)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->values_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kpi_id bigint(20) unsigned NOT NULL,
            value decimal(20,4) NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            notes text,
            is_calculated tinyint(1) DEFAULT 0,
            entered_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY kpi_period (kpi_id, period_start, period_end),
            KEY kpi_id (kpi_id),
            KEY period_start (period_start)
        ) {$charset_collate};";

        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->targets_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kpi_id bigint(20) unsigned NOT NULL,
            target_value decimal(20,4) NOT NULL,
            period_type enum('monthly','quarterly','yearly') DEFAULT 'monthly',
            period_year int NOT NULL,
            period_number int NOT NULL,
            stretch_target decimal(20,4),
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY kpi_target_period (kpi_id, period_type, period_year, period_number),
            KEY kpi_id (kpi_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );

        $this->seed_default_kpis();
    }

    private function seed_default_kpis() {
        global $wpdb;

        $existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->kpis_table}" );
        if ( $existing > 0 ) {
            return;
        }

        $defaults = array(
            array(
                'name'              => 'Revenue',
                'slug'              => 'revenue',
                'category'          => 'financial',
                'data_type'         => 'currency',
                'calculation_type'  => 'automatic',
                'data_source'       => 'projects',
                'aggregation'       => 'sum',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
                'show_on_dashboard' => 1,
            ),
            array(
                'name'              => 'Projects Completed',
                'slug'              => 'projects_completed',
                'category'          => 'operational',
                'data_type'         => 'number',
                'calculation_type'  => 'automatic',
                'data_source'       => 'projects',
                'aggregation'       => 'count',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
                'show_on_dashboard' => 1,
            ),
            array(
                'name'              => 'Billable Hours',
                'slug'              => 'billable_hours',
                'category'          => 'operational',
                'data_type'         => 'duration',
                'calculation_type'  => 'automatic',
                'data_source'       => 'time_entries',
                'aggregation'       => 'sum',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
                'show_on_dashboard' => 1,
            ),
            array(
                'name'              => 'Utilization Rate',
                'slug'              => 'utilization_rate',
                'category'          => 'employee',
                'data_type'         => 'percentage',
                'calculation_type'  => 'formula',
                'calculation_formula' => 'billable_hours / total_hours * 100',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
                'show_on_dashboard' => 1,
            ),
            array(
                'name'              => 'On-Time Delivery Rate',
                'slug'              => 'on_time_delivery',
                'category'          => 'customer',
                'data_type'         => 'percentage',
                'calculation_type'  => 'automatic',
                'data_source'       => 'projects',
                'aggregation'       => 'average',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
                'show_on_dashboard' => 1,
            ),
            array(
                'name'              => 'Average Project Duration',
                'slug'              => 'avg_project_duration',
                'category'          => 'operational',
                'data_type'         => 'number',
                'unit'              => 'days',
                'calculation_type'  => 'automatic',
                'data_source'       => 'projects',
                'aggregation'       => 'average',
                'frequency'         => 'monthly',
                'is_higher_better'  => 0,
            ),
            array(
                'name'              => 'Customer Satisfaction',
                'slug'              => 'customer_satisfaction',
                'category'          => 'customer',
                'data_type'         => 'percentage',
                'calculation_type'  => 'automatic',
                'data_source'       => 'surveys',
                'aggregation'       => 'average',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
            ),
            array(
                'name'              => 'Inventory Turnover',
                'slug'              => 'inventory_turnover',
                'category'          => 'operational',
                'data_type'         => 'number',
                'calculation_type'  => 'automatic',
                'data_source'       => 'inventory',
                'aggregation'       => 'average',
                'frequency'         => 'monthly',
                'is_higher_better'  => 1,
            ),
        );

        foreach ( $defaults as $kpi ) {
            $kpi['created_by'] = get_current_user_id() ?: 1;
            $wpdb->insert( $this->kpis_table, $kpi );
        }
    }

    public function get_kpis( $request ) {
        global $wpdb;

        $category = $request->get_param( 'category' );
        $active_only = $request->get_param( 'active_only' ) !== 'false';
        $dashboard_only = $request->get_param( 'dashboard_only' ) === 'true';

        $where = array();
        $values = array();

        if ( $active_only ) {
            $where[] = 'is_active = 1';
        }
        if ( $category ) {
            $where[] = 'category = %s';
            $values[] = $category;
        }
        if ( $dashboard_only ) {
            $where[] = 'show_on_dashboard = 1';
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $query = "SELECT * FROM {$this->kpis_table} {$where_clause} ORDER BY display_order, name";

        $kpis = ! empty( $values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
            : $wpdb->get_results( $query );

        // Get latest values
        foreach ( $kpis as $kpi ) {
            $kpi->current_value = $wpdb->get_var( $wpdb->prepare(
                "SELECT value FROM {$this->values_table}
                 WHERE kpi_id = %d ORDER BY period_end DESC LIMIT 1",
                $kpi->id
            ) );

            $kpi->current_target = $wpdb->get_var( $wpdb->prepare(
                "SELECT target_value FROM {$this->targets_table}
                 WHERE kpi_id = %d AND period_year = %d AND period_number = %d",
                $kpi->id, date( 'Y' ), date( 'n' )
            ) );

            if ( $kpi->current_target ) {
                $kpi->achievement = round( ( $kpi->current_value / $kpi->current_target ) * 100, 1 );
            }
        }

        return rest_ensure_response( $kpis );
    }

    public function get_kpi( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $kpi = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->kpis_table} WHERE id = %d", $id
        ) );

        if ( ! $kpi ) {
            return new WP_Error( 'not_found', 'KPI not found', array( 'status' => 404 ) );
        }

        // Get recent values
        $kpi->values = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->values_table}
             WHERE kpi_id = %d ORDER BY period_end DESC LIMIT 12",
            $id
        ) );

        // Get current year targets
        $kpi->targets = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->targets_table}
             WHERE kpi_id = %d AND period_year = %d ORDER BY period_number",
            $id, date( 'Y' )
        ) );

        return rest_ensure_response( $kpi );
    }

    public function create_kpi( $request ) {
        global $wpdb;

        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $slug = sanitize_title( $request->get_param( 'slug' ) ?: $name );

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->kpis_table} WHERE slug = %s", $slug
        ) );

        if ( $existing ) {
            return new WP_Error( 'duplicate', 'KPI with this slug already exists', array( 'status' => 400 ) );
        }

        $data = array(
            'name'                => $name,
            'slug'                => $slug,
            'description'         => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'category'            => sanitize_text_field( $request->get_param( 'category' ) ?: 'operational' ),
            'data_type'           => sanitize_text_field( $request->get_param( 'data_type' ) ?: 'number' ),
            'calculation_type'    => sanitize_text_field( $request->get_param( 'calculation_type' ) ?: 'manual' ),
            'calculation_formula' => sanitize_text_field( $request->get_param( 'calculation_formula' ) ),
            'data_source'         => sanitize_text_field( $request->get_param( 'data_source' ) ),
            'aggregation'         => sanitize_text_field( $request->get_param( 'aggregation' ) ?: 'sum' ),
            'frequency'           => sanitize_text_field( $request->get_param( 'frequency' ) ?: 'monthly' ),
            'unit'                => sanitize_text_field( $request->get_param( 'unit' ) ),
            'decimal_places'      => (int) $request->get_param( 'decimal_places' ) ?: 2,
            'is_higher_better'    => (int) $request->get_param( 'is_higher_better' ),
            'warning_threshold'   => (float) $request->get_param( 'warning_threshold' ) ?: null,
            'critical_threshold'  => (float) $request->get_param( 'critical_threshold' ) ?: null,
            'show_on_dashboard'   => (int) $request->get_param( 'show_on_dashboard' ),
            'created_by'          => get_current_user_id(),
        );

        $wpdb->insert( $this->kpis_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function update_kpi( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $fields = array( 'name', 'description', 'category', 'data_type', 'calculation_type',
            'calculation_formula', 'data_source', 'aggregation', 'frequency', 'unit' );

        $data = array();
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $numeric = array( 'decimal_places', 'is_higher_better', 'display_order', 'is_active', 'show_on_dashboard' );
        foreach ( $numeric as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = (int) $value;
            }
        }

        $float_fields = array( 'warning_threshold', 'critical_threshold' );
        foreach ( $float_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = (float) $value ?: null;
            }
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->kpis_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_kpi( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $wpdb->delete( $this->values_table, array( 'kpi_id' => $id ) );
        $wpdb->delete( $this->targets_table, array( 'kpi_id' => $id ) );
        $wpdb->delete( $this->kpis_table, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_values( $request ) {
        global $wpdb;
        $kpi_id = (int) $request->get_param( 'id' );
        $start = $request->get_param( 'start' );
        $end = $request->get_param( 'end' );

        $where = 'kpi_id = %d';
        $values = array( $kpi_id );

        if ( $start ) {
            $where .= ' AND period_start >= %s';
            $values[] = $start;
        }
        if ( $end ) {
            $where .= ' AND period_end <= %s';
            $values[] = $end;
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->values_table}
             WHERE {$where} ORDER BY period_start",
            $values
        ) );

        return rest_ensure_response( $results );
    }

    public function add_value( $request ) {
        global $wpdb;
        $kpi_id = (int) $request->get_param( 'id' );

        $data = array(
            'kpi_id'       => $kpi_id,
            'value'        => (float) $request->get_param( 'value' ),
            'period_start' => sanitize_text_field( $request->get_param( 'period_start' ) ),
            'period_end'   => sanitize_text_field( $request->get_param( 'period_end' ) ),
            'notes'        => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'is_calculated' => 0,
            'entered_by'   => get_current_user_id(),
        );

        // Check for existing value in same period
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->values_table}
             WHERE kpi_id = %d AND period_start = %s AND period_end = %s",
            $kpi_id, $data['period_start'], $data['period_end']
        ) );

        if ( $existing ) {
            $wpdb->update( $this->values_table, $data, array( 'id' => $existing ) );
            return rest_ensure_response( array( 'success' => true, 'id' => $existing, 'updated' => true ) );
        }

        $wpdb->insert( $this->values_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function get_targets( $request ) {
        global $wpdb;
        $kpi_id = (int) $request->get_param( 'id' );
        $year = $request->get_param( 'year' ) ?: date( 'Y' );

        $targets = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->targets_table}
             WHERE kpi_id = %d AND period_year = %d ORDER BY period_number",
            $kpi_id, $year
        ) );

        return rest_ensure_response( $targets );
    }

    public function set_target( $request ) {
        global $wpdb;
        $kpi_id = (int) $request->get_param( 'id' );

        $data = array(
            'kpi_id'         => $kpi_id,
            'target_value'   => (float) $request->get_param( 'target_value' ),
            'period_type'    => sanitize_text_field( $request->get_param( 'period_type' ) ?: 'monthly' ),
            'period_year'    => (int) $request->get_param( 'period_year' ) ?: date( 'Y' ),
            'period_number'  => (int) $request->get_param( 'period_number' ),
            'stretch_target' => (float) $request->get_param( 'stretch_target' ) ?: null,
            'notes'          => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'created_by'     => get_current_user_id(),
        );

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->targets_table}
             WHERE kpi_id = %d AND period_type = %s AND period_year = %d AND period_number = %d",
            $kpi_id, $data['period_type'], $data['period_year'], $data['period_number']
        ) );

        if ( $existing ) {
            unset( $data['created_by'] );
            $wpdb->update( $this->targets_table, $data, array( 'id' => $existing ) );
            return rest_ensure_response( array( 'success' => true, 'id' => $existing ) );
        }

        $wpdb->insert( $this->targets_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function calculate_kpi( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $period_start = sanitize_text_field( $request->get_param( 'period_start' ) );
        $period_end = sanitize_text_field( $request->get_param( 'period_end' ) );

        $kpi = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->kpis_table} WHERE id = %d", $id
        ) );

        if ( ! $kpi ) {
            return new WP_Error( 'not_found', 'KPI not found', array( 'status' => 404 ) );
        }

        if ( $kpi->calculation_type === 'manual' ) {
            return new WP_Error( 'manual_kpi', 'This KPI requires manual entry', array( 'status' => 400 ) );
        }

        $value = $this->calculate_kpi_value( $kpi, $period_start, $period_end );

        // Store the calculated value
        $wpdb->insert( $this->values_table, array(
            'kpi_id'        => $id,
            'value'         => $value,
            'period_start'  => $period_start,
            'period_end'    => $period_end,
            'is_calculated' => 1,
        ) );

        return rest_ensure_response( array(
            'success' => true,
            'value'   => $value,
            'id'      => $wpdb->insert_id,
        ) );
    }

    public function get_dashboard( $request ) {
        global $wpdb;

        $kpis = $wpdb->get_results(
            "SELECT * FROM {$this->kpis_table}
             WHERE is_active = 1 AND show_on_dashboard = 1
             ORDER BY display_order, name"
        );

        $dashboard = array();

        foreach ( $kpis as $kpi ) {
            $current = $wpdb->get_row( $wpdb->prepare(
                "SELECT value, period_start, period_end FROM {$this->values_table}
                 WHERE kpi_id = %d ORDER BY period_end DESC LIMIT 1",
                $kpi->id
            ) );

            $previous = $wpdb->get_var( $wpdb->prepare(
                "SELECT value FROM {$this->values_table}
                 WHERE kpi_id = %d ORDER BY period_end DESC LIMIT 1 OFFSET 1",
                $kpi->id
            ) );

            $target = $wpdb->get_var( $wpdb->prepare(
                "SELECT target_value FROM {$this->targets_table}
                 WHERE kpi_id = %d AND period_year = %d AND period_number = %d",
                $kpi->id, date( 'Y' ), date( 'n' )
            ) );

            $change = null;
            $change_pct = null;
            if ( $current && $previous ) {
                $change = $current->value - $previous;
                $change_pct = $previous != 0 ? round( ( $change / $previous ) * 100, 1 ) : null;
            }

            $status = 'normal';
            if ( $kpi->critical_threshold && $current ) {
                if ( $kpi->is_higher_better ) {
                    if ( $current->value <= $kpi->critical_threshold ) {
                        $status = 'critical';
                    } elseif ( $kpi->warning_threshold && $current->value <= $kpi->warning_threshold ) {
                        $status = 'warning';
                    }
                } else {
                    if ( $current->value >= $kpi->critical_threshold ) {
                        $status = 'critical';
                    } elseif ( $kpi->warning_threshold && $current->value >= $kpi->warning_threshold ) {
                        $status = 'warning';
                    }
                }
            }

            $dashboard[] = array(
                'id'              => $kpi->id,
                'name'            => $kpi->name,
                'category'        => $kpi->category,
                'data_type'       => $kpi->data_type,
                'unit'            => $kpi->unit,
                'current_value'   => $current ? $current->value : null,
                'previous_value'  => $previous,
                'change'          => $change,
                'change_percent'  => $change_pct,
                'target'          => $target,
                'achievement'     => $target ? round( ( $current->value / $target ) * 100, 1 ) : null,
                'status'          => $status,
                'is_higher_better' => (bool) $kpi->is_higher_better,
            );
        }

        return rest_ensure_response( $dashboard );
    }

    public function get_trends( $request ) {
        global $wpdb;

        $kpi_ids = $request->get_param( 'kpi_ids' );
        $months = (int) $request->get_param( 'months' ) ?: 12;

        $start_date = date( 'Y-m-01', strtotime( "-{$months} months" ) );

        if ( $kpi_ids ) {
            $ids = array_map( 'intval', explode( ',', $kpi_ids ) );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $where = "kpi_id IN ({$placeholders})";
            $values = $ids;
        } else {
            $where = '1=1';
            $values = array();
        }

        $values[] = $start_date;

        $trends = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.kpi_id, k.name, k.data_type, k.unit,
                    DATE_FORMAT(v.period_start, '%%Y-%%m') as period,
                    v.value
             FROM {$this->values_table} v
             JOIN {$this->kpis_table} k ON v.kpi_id = k.id
             WHERE {$where} AND v.period_start >= %s
             ORDER BY v.kpi_id, v.period_start",
            $values
        ) );

        // Group by KPI
        $grouped = array();
        foreach ( $trends as $row ) {
            if ( ! isset( $grouped[ $row->kpi_id ] ) ) {
                $grouped[ $row->kpi_id ] = array(
                    'id'        => $row->kpi_id,
                    'name'      => $row->name,
                    'data_type' => $row->data_type,
                    'unit'      => $row->unit,
                    'data'      => array(),
                );
            }
            $grouped[ $row->kpi_id ]['data'][] = array(
                'period' => $row->period,
                'value'  => (float) $row->value,
            );
        }

        return rest_ensure_response( array_values( $grouped ) );
    }

    public function get_comparison( $request ) {
        global $wpdb;

        $period1_start = sanitize_text_field( $request->get_param( 'period1_start' ) );
        $period1_end = sanitize_text_field( $request->get_param( 'period1_end' ) );
        $period2_start = sanitize_text_field( $request->get_param( 'period2_start' ) );
        $period2_end = sanitize_text_field( $request->get_param( 'period2_end' ) );

        $kpis = $wpdb->get_results(
            "SELECT * FROM {$this->kpis_table} WHERE is_active = 1 ORDER BY display_order"
        );

        $comparison = array();

        foreach ( $kpis as $kpi ) {
            $period1 = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(value) FROM {$this->values_table}
                 WHERE kpi_id = %d AND period_start >= %s AND period_end <= %s",
                $kpi->id, $period1_start, $period1_end
            ) );

            $period2 = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(value) FROM {$this->values_table}
                 WHERE kpi_id = %d AND period_start >= %s AND period_end <= %s",
                $kpi->id, $period2_start, $period2_end
            ) );

            $change = $period1 && $period2 ? $period2 - $period1 : null;
            $change_pct = $period1 ? round( ( $change / $period1 ) * 100, 1 ) : null;

            $comparison[] = array(
                'kpi_id'           => $kpi->id,
                'name'             => $kpi->name,
                'period1_value'    => $period1,
                'period2_value'    => $period2,
                'change'           => $change,
                'change_percent'   => $change_pct,
                'is_higher_better' => (bool) $kpi->is_higher_better,
            );
        }

        return rest_ensure_response( $comparison );
    }

    public function calculate_all_kpis() {
        global $wpdb;

        $kpis = $wpdb->get_results(
            "SELECT * FROM {$this->kpis_table}
             WHERE is_active = 1 AND calculation_type = 'automatic'"
        );

        $period_start = date( 'Y-m-01' );
        $period_end = date( 'Y-m-t' );

        foreach ( $kpis as $kpi ) {
            $value = $this->calculate_kpi_value( $kpi, $period_start, $period_end );

            if ( $value !== null ) {
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$this->values_table}
                     WHERE kpi_id = %d AND period_start = %s AND period_end = %s",
                    $kpi->id, $period_start, $period_end
                ) );

                $data = array(
                    'kpi_id'        => $kpi->id,
                    'value'         => $value,
                    'period_start'  => $period_start,
                    'period_end'    => $period_end,
                    'is_calculated' => 1,
                );

                if ( $existing ) {
                    $wpdb->update( $this->values_table, $data, array( 'id' => $existing ) );
                } else {
                    $wpdb->insert( $this->values_table, $data );
                }
            }
        }
    }

    private function calculate_kpi_value( $kpi, $period_start, $period_end ) {
        global $wpdb;

        switch ( $kpi->data_source ) {
            case 'projects':
                return $this->calculate_projects_kpi( $kpi, $period_start, $period_end );

            case 'time_entries':
                return $this->calculate_time_kpi( $kpi, $period_start, $period_end );

            case 'inventory':
                return $this->calculate_inventory_kpi( $kpi, $period_start, $period_end );

            default:
                return null;
        }
    }

    private function calculate_projects_kpi( $kpi, $start, $end ) {
        global $wpdb;

        switch ( $kpi->slug ) {
            case 'revenue':
                return $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM {$wpdb->prefix}ict_projects
                     WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
                    $start, $end
                ) );

            case 'projects_completed':
                return $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects
                     WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
                    $start, $end
                ) );

            case 'on_time_delivery':
                $total = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects
                     WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
                    $start, $end
                ) );
                $on_time = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects
                     WHERE status = 'completed' AND end_date BETWEEN %s AND %s
                     AND end_date <= due_date",
                    $start, $end
                ) );
                return $total > 0 ? round( ( $on_time / $total ) * 100, 1 ) : 0;

            case 'avg_project_duration':
                return $wpdb->get_var( $wpdb->prepare(
                    "SELECT AVG(DATEDIFF(end_date, start_date)) FROM {$wpdb->prefix}ict_projects
                     WHERE status = 'completed' AND end_date BETWEEN %s AND %s",
                    $start, $end
                ) );

            default:
                return null;
        }
    }

    private function calculate_time_kpi( $kpi, $start, $end ) {
        global $wpdb;

        switch ( $kpi->slug ) {
            case 'billable_hours':
                return $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) / 60
                     FROM {$wpdb->prefix}ict_time_entries
                     WHERE is_billable = 1 AND start_time BETWEEN %s AND %s",
                    $start, $end
                ) );

            default:
                return null;
        }
    }

    private function calculate_inventory_kpi( $kpi, $start, $end ) {
        global $wpdb;

        switch ( $kpi->slug ) {
            case 'inventory_turnover':
                $movements = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(ABS(quantity)), 0) FROM {$wpdb->prefix}ict_stock_movements
                     WHERE created_at BETWEEN %s AND %s",
                    $start, $end
                ) );
                $avg_inventory = $wpdb->get_var(
                    "SELECT COALESCE(AVG(quantity), 1) FROM {$wpdb->prefix}ict_inventory_items"
                );
                return $avg_inventory > 0 ? round( $movements / $avg_inventory, 2 ) : 0;

            default:
                return null;
        }
    }
}
