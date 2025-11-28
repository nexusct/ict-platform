<?php
/**
 * Supplier Management
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Supplier_Manager {

    private static $instance = null;
    private $table_name;
    private $contacts_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ict_suppliers';
        $this->contacts_table = $wpdb->prefix . 'ict_supplier_contacts';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/suppliers', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_suppliers' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_supplier' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/suppliers/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_supplier' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_supplier' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_supplier' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/suppliers/(?P<id>\d+)/contacts', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_contacts' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_contact' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/suppliers/(?P<id>\d+)/items', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_supplier_items' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/suppliers/(?P<id>\d+)/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_supplier_orders' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/suppliers/(?P<id>\d+)/performance', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_performance' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'edit_ict_inventory' ) || current_user_can( 'manage_options' );
    }

    public function check_edit_permission() {
        return current_user_can( 'manage_ict_inventory' ) || current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            code varchar(50),
            type enum('manufacturer','distributor','wholesaler','retailer') DEFAULT 'distributor',
            email varchar(255),
            phone varchar(50),
            website varchar(255),
            address_line1 varchar(255),
            address_line2 varchar(255),
            city varchar(100),
            state varchar(100),
            postal_code varchar(20),
            country varchar(100),
            tax_id varchar(50),
            payment_terms varchar(100),
            credit_limit decimal(15,2),
            lead_time_days int DEFAULT 0,
            minimum_order decimal(15,2) DEFAULT 0,
            rating decimal(3,2) DEFAULT 0,
            notes text,
            is_active tinyint(1) DEFAULT 1,
            is_preferred tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY is_active (is_active),
            KEY is_preferred (is_preferred)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->contacts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            supplier_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            title varchar(100),
            email varchar(255),
            phone varchar(50),
            mobile varchar(50),
            is_primary tinyint(1) DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY supplier_id (supplier_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }

    public function get_suppliers( $request ) {
        global $wpdb;

        $is_active = $request->get_param( 'is_active' );
        $type = $request->get_param( 'type' );
        $search = $request->get_param( 'search' );

        $where = array( '1=1' );
        $values = array();

        if ( null !== $is_active ) {
            $where[] = 'is_active = %d';
            $values[] = (int) $is_active;
        }
        if ( $type ) {
            $where[] = 'type = %s';
            $values[] = $type;
        }
        if ( $search ) {
            $where[] = '(name LIKE %s OR code LIKE %s OR email LIKE %s)';
            $s = '%' . $wpdb->esc_like( $search ) . '%';
            $values[] = $s;
            $values[] = $s;
            $values[] = $s;
        }

        $where_clause = implode( ' AND ', $where );
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY name";

        $suppliers = ! empty( $values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
            : $wpdb->get_results( $query );

        return rest_ensure_response( $suppliers );
    }

    public function get_supplier( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $supplier = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d", $id
        ) );

        if ( ! $supplier ) {
            return new WP_Error( 'not_found', 'Supplier not found', array( 'status' => 404 ) );
        }

        $supplier->contacts = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->contacts_table} WHERE supplier_id = %d ORDER BY is_primary DESC, name",
            $id
        ) );

        return rest_ensure_response( $supplier );
    }

    public function create_supplier( $request ) {
        global $wpdb;

        $data = array(
            'name'          => sanitize_text_field( $request->get_param( 'name' ) ),
            'code'          => sanitize_text_field( $request->get_param( 'code' ) ),
            'type'          => sanitize_text_field( $request->get_param( 'type' ) ?: 'distributor' ),
            'email'         => sanitize_email( $request->get_param( 'email' ) ),
            'phone'         => sanitize_text_field( $request->get_param( 'phone' ) ),
            'website'       => esc_url_raw( $request->get_param( 'website' ) ),
            'address_line1' => sanitize_text_field( $request->get_param( 'address_line1' ) ),
            'address_line2' => sanitize_text_field( $request->get_param( 'address_line2' ) ),
            'city'          => sanitize_text_field( $request->get_param( 'city' ) ),
            'state'         => sanitize_text_field( $request->get_param( 'state' ) ),
            'postal_code'   => sanitize_text_field( $request->get_param( 'postal_code' ) ),
            'country'       => sanitize_text_field( $request->get_param( 'country' ) ),
            'tax_id'        => sanitize_text_field( $request->get_param( 'tax_id' ) ),
            'payment_terms' => sanitize_text_field( $request->get_param( 'payment_terms' ) ),
            'credit_limit'  => (float) $request->get_param( 'credit_limit' ),
            'lead_time_days' => (int) $request->get_param( 'lead_time_days' ),
            'minimum_order' => (float) $request->get_param( 'minimum_order' ),
            'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'is_preferred'  => (int) $request->get_param( 'is_preferred' ),
            'created_by'    => get_current_user_id(),
        );

        $wpdb->insert( $this->table_name, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function update_supplier( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $fields = array( 'name', 'code', 'type', 'email', 'phone', 'website', 'address_line1',
            'address_line2', 'city', 'state', 'postal_code', 'country', 'tax_id',
            'payment_terms', 'notes' );

        $data = array();
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $numeric = array( 'credit_limit', 'lead_time_days', 'minimum_order', 'rating', 'is_active', 'is_preferred' );
        foreach ( $numeric as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = is_float( $value + 0 ) ? (float) $value : (int) $value;
            }
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->table_name, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_supplier( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $wpdb->delete( $this->contacts_table, array( 'supplier_id' => $id ) );
        $wpdb->delete( $this->table_name, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_contacts( $request ) {
        global $wpdb;
        $supplier_id = (int) $request->get_param( 'id' );

        $contacts = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->contacts_table} WHERE supplier_id = %d ORDER BY is_primary DESC, name",
            $supplier_id
        ) );

        return rest_ensure_response( $contacts );
    }

    public function add_contact( $request ) {
        global $wpdb;
        $supplier_id = (int) $request->get_param( 'id' );

        $data = array(
            'supplier_id' => $supplier_id,
            'name'        => sanitize_text_field( $request->get_param( 'name' ) ),
            'title'       => sanitize_text_field( $request->get_param( 'title' ) ),
            'email'       => sanitize_email( $request->get_param( 'email' ) ),
            'phone'       => sanitize_text_field( $request->get_param( 'phone' ) ),
            'mobile'      => sanitize_text_field( $request->get_param( 'mobile' ) ),
            'is_primary'  => (int) $request->get_param( 'is_primary' ),
            'notes'       => sanitize_textarea_field( $request->get_param( 'notes' ) ),
        );

        if ( $data['is_primary'] ) {
            $wpdb->update( $this->contacts_table, array( 'is_primary' => 0 ), array( 'supplier_id' => $supplier_id ) );
        }

        $wpdb->insert( $this->contacts_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function get_supplier_items( $request ) {
        global $wpdb;
        $supplier_id = (int) $request->get_param( 'id' );

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ict_inventory_items WHERE supplier_id = %d ORDER BY name",
            $supplier_id
        ) );

        return rest_ensure_response( $items );
    }

    public function get_supplier_orders( $request ) {
        global $wpdb;
        $supplier_id = (int) $request->get_param( 'id' );

        $orders = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ict_purchase_orders WHERE supplier_id = %d ORDER BY created_at DESC",
            $supplier_id
        ) );

        return rest_ensure_response( $orders );
    }

    public function get_performance( $request ) {
        global $wpdb;
        $supplier_id = (int) $request->get_param( 'id' );

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as total_orders,
                    SUM(total_amount) as total_spent,
                    AVG(DATEDIFF(received_date, order_date)) as avg_delivery_days,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN received_date <= expected_date THEN 1 ELSE 0 END) as on_time
             FROM {$wpdb->prefix}ict_purchase_orders
             WHERE supplier_id = %d AND status != 'cancelled'",
            $supplier_id
        ) );

        $stats->on_time_rate = $stats->total_orders > 0
            ? round( ( $stats->on_time / $stats->total_orders ) * 100, 1 )
            : 0;

        return rest_ensure_response( $stats );
    }
}
