<?php
/**
 * Purchase Order Approval Workflow
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_PO_Approval {

    private static $instance = null;
    private $approvals_table;
    private $rules_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->approvals_table = $wpdb->prefix . 'ict_po_approvals';
        $this->rules_table = $wpdb->prefix . 'ict_po_approval_rules';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
        add_action( 'ict_po_created', array( $this, 'initiate_approval' ), 10, 2 );
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/po/pending-approval', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_pending' ),
            'permission_callback' => array( $this, 'check_approval_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/po/(?P<po_id>\d+)/approve', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'approve' ),
            'permission_callback' => array( $this, 'check_approval_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/po/(?P<po_id>\d+)/reject', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'reject' ),
            'permission_callback' => array( $this, 'check_approval_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/po/(?P<po_id>\d+)/approval-history', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_history' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/po/approval-rules', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_rules' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_rule' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/po/approval-rules/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_rule' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_rule' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/po/my-approvals', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_my_approvals' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return current_user_can( 'edit_ict_inventory' ) || current_user_can( 'manage_options' );
    }

    public function check_approval_permission() {
        return current_user_can( 'approve_ict_po' ) || current_user_can( 'manage_options' );
    }

    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->approvals_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            po_id bigint(20) unsigned NOT NULL,
            approval_level int DEFAULT 1,
            approver_id bigint(20) unsigned,
            approver_role varchar(100),
            status enum('pending','approved','rejected') DEFAULT 'pending',
            comments text,
            approved_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY po_id (po_id),
            KEY approver_id (approver_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->rules_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            min_amount decimal(15,2) DEFAULT 0,
            max_amount decimal(15,2),
            approval_levels int DEFAULT 1,
            level1_role varchar(100),
            level1_user bigint(20) unsigned,
            level2_role varchar(100),
            level2_user bigint(20) unsigned,
            level3_role varchar(100),
            level3_user bigint(20) unsigned,
            auto_approve_below decimal(15,2) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );

        $this->insert_default_rules();
    }

    private function insert_default_rules() {
        global $wpdb;

        if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$this->rules_table}" ) > 0 ) {
            return;
        }

        $wpdb->insert( $this->rules_table, array(
            'name'              => 'Small Orders',
            'min_amount'        => 0,
            'max_amount'        => 500,
            'approval_levels'   => 1,
            'level1_role'       => 'ict_inventory_manager',
            'auto_approve_below' => 100,
        ) );

        $wpdb->insert( $this->rules_table, array(
            'name'            => 'Medium Orders',
            'min_amount'      => 500,
            'max_amount'      => 5000,
            'approval_levels' => 2,
            'level1_role'     => 'ict_inventory_manager',
            'level2_role'     => 'ict_project_manager',
        ) );

        $wpdb->insert( $this->rules_table, array(
            'name'            => 'Large Orders',
            'min_amount'      => 5000,
            'max_amount'      => null,
            'approval_levels' => 3,
            'level1_role'     => 'ict_inventory_manager',
            'level2_role'     => 'ict_project_manager',
            'level3_role'     => 'administrator',
        ) );
    }

    public function initiate_approval( $po_id, $po_data ) {
        global $wpdb;

        $amount = $po_data['total_amount'] ?? 0;

        // Check for auto-approve
        $rule = $this->get_applicable_rule( $amount );

        if ( ! $rule ) {
            $wpdb->update( $wpdb->prefix . 'ict_purchase_orders',
                array( 'status' => 'approved' ),
                array( 'id' => $po_id )
            );
            return;
        }

        if ( $rule->auto_approve_below > 0 && $amount < $rule->auto_approve_below ) {
            $wpdb->update( $wpdb->prefix . 'ict_purchase_orders',
                array( 'status' => 'approved' ),
                array( 'id' => $po_id )
            );
            return;
        }

        // Create approval records
        for ( $level = 1; $level <= $rule->approval_levels; $level++ ) {
            $role_field = "level{$level}_role";
            $user_field = "level{$level}_user";

            $wpdb->insert( $this->approvals_table, array(
                'po_id'          => $po_id,
                'approval_level' => $level,
                'approver_id'    => $rule->$user_field ?: null,
                'approver_role'  => $rule->$role_field,
                'status'         => $level === 1 ? 'pending' : 'pending',
            ) );
        }

        $wpdb->update( $wpdb->prefix . 'ict_purchase_orders',
            array( 'status' => 'pending_approval' ),
            array( 'id' => $po_id )
        );

        do_action( 'ict_po_approval_required', $po_id, $rule );
    }

    private function get_applicable_rule( $amount ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->rules_table}
             WHERE is_active = 1
             AND min_amount <= %f
             AND (max_amount IS NULL OR max_amount >= %f)
             ORDER BY min_amount DESC
             LIMIT 1",
            $amount, $amount
        ) );
    }

    public function get_pending( $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );
        $user_roles = $user ? $user->roles : array();

        $role_placeholders = implode( ',', array_fill( 0, count( $user_roles ), '%s' ) );
        $values = array_merge( array( $user_id ), $user_roles );

        $approvals = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, po.po_number, po.total_amount, po.supplier_id,
                    s.name as supplier_name, u.display_name as created_by_name
             FROM {$this->approvals_table} a
             JOIN {$wpdb->prefix}ict_purchase_orders po ON a.po_id = po.id
             LEFT JOIN {$wpdb->prefix}ict_suppliers s ON po.supplier_id = s.id
             LEFT JOIN {$wpdb->users} u ON po.created_by = u.ID
             WHERE a.status = 'pending'
             AND (a.approver_id = %d OR a.approver_role IN ({$role_placeholders}))
             AND NOT EXISTS (
                 SELECT 1 FROM {$this->approvals_table} prev
                 WHERE prev.po_id = a.po_id
                 AND prev.approval_level < a.approval_level
                 AND prev.status != 'approved'
             )
             ORDER BY a.created_at",
            $values
        ) );

        return rest_ensure_response( $approvals );
    }

    public function approve( $request ) {
        global $wpdb;

        $po_id = (int) $request->get_param( 'po_id' );
        $comments = sanitize_textarea_field( $request->get_param( 'comments' ) );
        $user_id = get_current_user_id();

        $approval = $this->get_pending_approval_for_user( $po_id, $user_id );

        if ( ! $approval ) {
            return new WP_Error( 'not_authorized', 'Not authorized to approve', array( 'status' => 403 ) );
        }

        $wpdb->update( $this->approvals_table, array(
            'status'      => 'approved',
            'approver_id' => $user_id,
            'comments'    => $comments,
            'approved_at' => current_time( 'mysql' ),
        ), array( 'id' => $approval->id ) );

        // Check if all levels approved
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->approvals_table}
             WHERE po_id = %d AND status = 'pending'",
            $po_id
        ) );

        if ( $pending == 0 ) {
            $wpdb->update( $wpdb->prefix . 'ict_purchase_orders',
                array( 'status' => 'approved' ),
                array( 'id' => $po_id )
            );
            do_action( 'ict_po_fully_approved', $po_id );
        } else {
            do_action( 'ict_po_level_approved', $po_id, $approval->approval_level );
        }

        return rest_ensure_response( array( 'success' => true, 'fully_approved' => $pending == 0 ) );
    }

    public function reject( $request ) {
        global $wpdb;

        $po_id = (int) $request->get_param( 'po_id' );
        $reason = sanitize_textarea_field( $request->get_param( 'reason' ) );
        $user_id = get_current_user_id();

        if ( empty( $reason ) ) {
            return new WP_Error( 'reason_required', 'Rejection reason required', array( 'status' => 400 ) );
        }

        $approval = $this->get_pending_approval_for_user( $po_id, $user_id );

        if ( ! $approval ) {
            return new WP_Error( 'not_authorized', 'Not authorized', array( 'status' => 403 ) );
        }

        $wpdb->update( $this->approvals_table, array(
            'status'      => 'rejected',
            'approver_id' => $user_id,
            'comments'    => $reason,
            'approved_at' => current_time( 'mysql' ),
        ), array( 'id' => $approval->id ) );

        $wpdb->update( $wpdb->prefix . 'ict_purchase_orders',
            array( 'status' => 'rejected' ),
            array( 'id' => $po_id )
        );

        do_action( 'ict_po_rejected', $po_id, $reason );

        return rest_ensure_response( array( 'success' => true ) );
    }

    private function get_pending_approval_for_user( $po_id, $user_id ) {
        global $wpdb;

        $user = get_userdata( $user_id );
        $roles = $user ? $user->roles : array();

        $role_placeholders = implode( ',', array_fill( 0, count( $roles ), '%s' ) );
        $values = array_merge( array( $po_id, $user_id ), $roles );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->approvals_table}
             WHERE po_id = %d
             AND status = 'pending'
             AND (approver_id = %d OR approver_role IN ({$role_placeholders}))
             AND NOT EXISTS (
                 SELECT 1 FROM {$this->approvals_table} prev
                 WHERE prev.po_id = po_id
                 AND prev.approval_level < approval_level
                 AND prev.status != 'approved'
             )
             LIMIT 1",
            $values
        ) );
    }

    public function get_history( $request ) {
        global $wpdb;
        $po_id = (int) $request->get_param( 'po_id' );

        $history = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, u.display_name as approver_name
             FROM {$this->approvals_table} a
             LEFT JOIN {$wpdb->users} u ON a.approver_id = u.ID
             WHERE a.po_id = %d
             ORDER BY a.approval_level",
            $po_id
        ) );

        return rest_ensure_response( $history );
    }

    public function get_rules( $request ) {
        global $wpdb;
        $rules = $wpdb->get_results( "SELECT * FROM {$this->rules_table} ORDER BY min_amount" );
        return rest_ensure_response( $rules );
    }

    public function create_rule( $request ) {
        global $wpdb;

        $data = array(
            'name'              => sanitize_text_field( $request->get_param( 'name' ) ),
            'min_amount'        => (float) $request->get_param( 'min_amount' ),
            'max_amount'        => $request->get_param( 'max_amount' ) ? (float) $request->get_param( 'max_amount' ) : null,
            'approval_levels'   => (int) $request->get_param( 'approval_levels' ) ?: 1,
            'level1_role'       => sanitize_text_field( $request->get_param( 'level1_role' ) ),
            'level1_user'       => (int) $request->get_param( 'level1_user' ) ?: null,
            'level2_role'       => sanitize_text_field( $request->get_param( 'level2_role' ) ),
            'level2_user'       => (int) $request->get_param( 'level2_user' ) ?: null,
            'level3_role'       => sanitize_text_field( $request->get_param( 'level3_role' ) ),
            'level3_user'       => (int) $request->get_param( 'level3_user' ) ?: null,
            'auto_approve_below' => (float) $request->get_param( 'auto_approve_below' ),
        );

        $wpdb->insert( $this->rules_table, $data );

        return rest_ensure_response( array( 'success' => true, 'id' => $wpdb->insert_id ) );
    }

    public function update_rule( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $data = array();
        $fields = array( 'name', 'level1_role', 'level2_role', 'level3_role' );
        foreach ( $fields as $f ) {
            $v = $request->get_param( $f );
            if ( null !== $v ) $data[ $f ] = sanitize_text_field( $v );
        }

        $numeric = array( 'min_amount', 'max_amount', 'approval_levels', 'level1_user',
            'level2_user', 'level3_user', 'auto_approve_below', 'is_active' );
        foreach ( $numeric as $f ) {
            $v = $request->get_param( $f );
            if ( null !== $v ) $data[ $f ] = is_float( $v + 0 ) ? (float) $v : (int) $v;
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->rules_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_rule( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $wpdb->delete( $this->rules_table, array( 'id' => $id ) );
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_my_approvals( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();

        $approvals = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, po.po_number, po.total_amount
             FROM {$this->approvals_table} a
             JOIN {$wpdb->prefix}ict_purchase_orders po ON a.po_id = po.id
             WHERE a.approver_id = %d
             ORDER BY a.approved_at DESC
             LIMIT 50",
            $user_id
        ) );

        return rest_ensure_response( $approvals );
    }
}
