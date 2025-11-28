<?php
/**
 * Warranty Tracking System
 *
 * Comprehensive warranty management including:
 * - Warranty registration for equipment and installations
 * - Expiration tracking and alerts
 * - Warranty claim management
 * - Document storage for warranty certificates
 * - Manufacturer/vendor warranty contacts
 * - Automated renewal reminders
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Warranty_Tracker
 */
class ICT_Warranty_Tracker {

    /**
     * Singleton instance.
     *
     * @var ICT_Warranty_Tracker
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
     * @return ICT_Warranty_Tracker
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
            'warranties' => $wpdb->prefix . 'ict_warranties',
            'claims'     => $wpdb->prefix . 'ict_warranty_claims',
            'documents'  => $wpdb->prefix . 'ict_warranty_documents',
            'contacts'   => $wpdb->prefix . 'ict_warranty_contacts',
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
        add_action( 'ict_check_warranty_expirations', array( $this, 'check_expirations' ) );

        if ( ! wp_next_scheduled( 'ict_check_warranty_expirations' ) ) {
            wp_schedule_event( time(), 'daily', 'ict_check_warranty_expirations' );
        }
    }

    /**
     * Create database tables.
     */
    public function maybe_create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        // Warranties table.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['warranties']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            warranty_type enum('manufacturer','extended','installation','service') DEFAULT 'manufacturer',
            provider varchar(200) NOT NULL,
            warranty_number varchar(100) DEFAULT NULL,
            purchase_date date NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            coverage_details text,
            terms_conditions longtext,
            coverage_amount decimal(12,2) DEFAULT NULL,
            deductible decimal(12,2) DEFAULT NULL,
            status enum('active','expired','claimed','voided') DEFAULT 'active',
            reminder_days int(11) DEFAULT 30,
            last_reminder_sent datetime DEFAULT NULL,
            notes text,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY warranty_number (warranty_number),
            KEY entity (entity_type, entity_id),
            KEY end_date (end_date),
            KEY status (status)
        ) $charset_collate;";

        // Warranty claims.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['claims']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            warranty_id bigint(20) unsigned NOT NULL,
            claim_number varchar(100) DEFAULT NULL,
            claim_date date NOT NULL,
            issue_description text NOT NULL,
            resolution_description text,
            status enum('submitted','under_review','approved','denied','completed') DEFAULT 'submitted',
            claim_amount decimal(12,2) DEFAULT NULL,
            approved_amount decimal(12,2) DEFAULT NULL,
            submitted_by bigint(20) unsigned DEFAULT NULL,
            resolved_date date DEFAULT NULL,
            resolution_notes text,
            attachments longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY warranty_id (warranty_id),
            KEY status (status),
            KEY claim_date (claim_date)
        ) $charset_collate;";

        // Warranty documents.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['documents']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            warranty_id bigint(20) unsigned NOT NULL,
            document_type enum('certificate','receipt','terms','claim_form','other') DEFAULT 'certificate',
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) DEFAULT NULL,
            mime_type varchar(100) DEFAULT NULL,
            uploaded_by bigint(20) unsigned DEFAULT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY warranty_id (warranty_id)
        ) $charset_collate;";

        // Warranty contacts.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['contacts']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider varchar(200) NOT NULL,
            contact_name varchar(200) DEFAULT NULL,
            department varchar(100) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            email varchar(200) DEFAULT NULL,
            website varchar(500) DEFAULT NULL,
            address text,
            support_hours varchar(200) DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY provider (provider)
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

        // Warranties.
        register_rest_route( $namespace, '/warranties', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_warranties' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_create_warranty' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
        ) );

        register_rest_route( $namespace, '/warranties/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_warranty' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_update_warranty' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'rest_delete_warranty' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
        ) );

        // Expiring warranties.
        register_rest_route( $namespace, '/warranties/expiring', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_expiring' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
        ) );

        // Claims.
        register_rest_route( $namespace, '/warranties/(?P<warranty_id>\d+)/claims', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_claims' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_create_claim' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
        ) );

        register_rest_route( $namespace, '/warranty-claims/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_claim' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'rest_update_claim' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
        ) );

        // Documents.
        register_rest_route( $namespace, '/warranties/(?P<warranty_id>\d+)/documents', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_documents' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_upload_document' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
        ) );

        // Contacts.
        register_rest_route( $namespace, '/warranty-contacts', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_get_contacts' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_create_contact' ),
                'permission_callback' => array( $this, 'check_manage_permission' ),
            ),
        ) );

        // Dashboard stats.
        register_rest_route( $namespace, '/warranties/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rest_get_stats' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
        ) );
    }

    /**
     * Check read permission.
     */
    public function check_read_permission() {
        return current_user_can( 'read' );
    }

    /**
     * Check manage permission.
     */
    public function check_manage_permission() {
        return current_user_can( 'manage_ict_inventory' ) || current_user_can( 'manage_options' );
    }

    /**
     * Get warranties.
     */
    public function rest_get_warranties( $request ) {
        global $wpdb;

        $status = $request->get_param( 'status' );
        $entity_type = $request->get_param( 'entity_type' );
        $entity_id = $request->get_param( 'entity_id' );
        $provider = $request->get_param( 'provider' );
        $page = max( 1, intval( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ?: 20 ) ) );

        $sql = "SELECT * FROM {$this->tables['warranties']} WHERE 1=1";
        $args = array();

        if ( $status ) {
            $sql .= " AND status = %s";
            $args[] = $status;
        }

        if ( $entity_type ) {
            $sql .= " AND entity_type = %s";
            $args[] = $entity_type;
        }

        if ( $entity_id ) {
            $sql .= " AND entity_id = %d";
            $args[] = $entity_id;
        }

        if ( $provider ) {
            $sql .= " AND provider LIKE %s";
            $args[] = '%' . $wpdb->esc_like( $provider ) . '%';
        }

        $count_sql = str_replace( 'SELECT *', 'SELECT COUNT(*)', $sql );
        $total = ! empty( $args ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql );

        $sql .= " ORDER BY end_date ASC LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = ( $page - 1 ) * $per_page;

        $warranties = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        // Calculate days until expiration.
        foreach ( $warranties as &$warranty ) {
            $warranty->days_until_expiration = ( strtotime( $warranty->end_date ) - time() ) / 86400;
            $warranty->is_expiring_soon = $warranty->days_until_expiration <= $warranty->reminder_days && $warranty->days_until_expiration > 0;
        }

        return rest_ensure_response( array(
            'success'    => true,
            'warranties' => $warranties,
            'total'      => intval( $total ),
            'pages'      => ceil( $total / $per_page ),
        ) );
    }

    /**
     * Get single warranty.
     */
    public function rest_get_warranty( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $warranty = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['warranties']} WHERE id = %d",
            $id
        ) );

        if ( ! $warranty ) {
            return new WP_Error( 'not_found', 'Warranty not found', array( 'status' => 404 ) );
        }

        // Get claims.
        $warranty->claims = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables['claims']} WHERE warranty_id = %d ORDER BY claim_date DESC",
            $id
        ) );

        // Get documents.
        $warranty->documents = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables['documents']} WHERE warranty_id = %d ORDER BY uploaded_at DESC",
            $id
        ) );

        // Get contact info.
        $warranty->contact = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['contacts']} WHERE provider = %s",
            $warranty->provider
        ) );

        $warranty->days_until_expiration = ( strtotime( $warranty->end_date ) - time() ) / 86400;

        return rest_ensure_response( array(
            'success'  => true,
            'warranty' => $warranty,
        ) );
    }

    /**
     * Create warranty.
     */
    public function rest_create_warranty( $request ) {
        global $wpdb;

        $data = array(
            'entity_type'       => sanitize_text_field( $request->get_param( 'entity_type' ) ),
            'entity_id'         => intval( $request->get_param( 'entity_id' ) ),
            'warranty_type'     => sanitize_text_field( $request->get_param( 'warranty_type' ) ) ?: 'manufacturer',
            'provider'          => sanitize_text_field( $request->get_param( 'provider' ) ),
            'warranty_number'   => sanitize_text_field( $request->get_param( 'warranty_number' ) ),
            'purchase_date'     => sanitize_text_field( $request->get_param( 'purchase_date' ) ),
            'start_date'        => sanitize_text_field( $request->get_param( 'start_date' ) ),
            'end_date'          => sanitize_text_field( $request->get_param( 'end_date' ) ),
            'coverage_details'  => sanitize_textarea_field( $request->get_param( 'coverage_details' ) ),
            'terms_conditions'  => wp_kses_post( $request->get_param( 'terms_conditions' ) ),
            'coverage_amount'   => floatval( $request->get_param( 'coverage_amount' ) ),
            'deductible'        => floatval( $request->get_param( 'deductible' ) ),
            'reminder_days'     => intval( $request->get_param( 'reminder_days' ) ) ?: 30,
            'notes'             => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'created_by'        => get_current_user_id(),
        );

        $wpdb->insert( $this->tables['warranties'], $data );
        $warranty_id = $wpdb->insert_id;

        return rest_ensure_response( array(
            'success'     => true,
            'warranty_id' => $warranty_id,
            'message'     => 'Warranty created successfully',
        ) );
    }

    /**
     * Update warranty.
     */
    public function rest_update_warranty( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $warranty = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['warranties']} WHERE id = %d",
            $id
        ) );

        if ( ! $warranty ) {
            return new WP_Error( 'not_found', 'Warranty not found', array( 'status' => 404 ) );
        }

        $fields = array(
            'warranty_type', 'provider', 'warranty_number', 'purchase_date',
            'start_date', 'end_date', 'coverage_details', 'terms_conditions',
            'coverage_amount', 'deductible', 'status', 'reminder_days', 'notes'
        );

        $data = array();
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
            }
        }

        if ( ! empty( $data ) ) {
            $wpdb->update( $this->tables['warranties'], $data, array( 'id' => $id ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Warranty updated successfully',
        ) );
    }

    /**
     * Delete warranty.
     */
    public function rest_delete_warranty( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $wpdb->delete( $this->tables['warranties'], array( 'id' => $id ) );
        $wpdb->delete( $this->tables['claims'], array( 'warranty_id' => $id ) );
        $wpdb->delete( $this->tables['documents'], array( 'warranty_id' => $id ) );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Warranty deleted successfully',
        ) );
    }

    /**
     * Get expiring warranties.
     */
    public function rest_get_expiring( $request ) {
        global $wpdb;

        $days = intval( $request->get_param( 'days' ) ) ?: 30;

        $warranties = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->tables['warranties']}
            WHERE status = 'active'
            AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
            ORDER BY end_date ASC",
            $days
        ) );

        foreach ( $warranties as &$warranty ) {
            $warranty->days_until_expiration = floor( ( strtotime( $warranty->end_date ) - time() ) / 86400 );
        }

        return rest_ensure_response( array(
            'success'    => true,
            'warranties' => $warranties,
            'count'      => count( $warranties ),
        ) );
    }

    /**
     * Get claims for warranty.
     */
    public function rest_get_claims( $request ) {
        global $wpdb;

        $warranty_id = intval( $request->get_param( 'warranty_id' ) );

        $claims = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, u.display_name as submitted_by_name
            FROM {$this->tables['claims']} c
            LEFT JOIN {$wpdb->users} u ON c.submitted_by = u.ID
            WHERE c.warranty_id = %d
            ORDER BY c.claim_date DESC",
            $warranty_id
        ) );

        return rest_ensure_response( array(
            'success' => true,
            'claims'  => $claims,
        ) );
    }

    /**
     * Create claim.
     */
    public function rest_create_claim( $request ) {
        global $wpdb;

        $warranty_id = intval( $request->get_param( 'warranty_id' ) );

        $warranty = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['warranties']} WHERE id = %d",
            $warranty_id
        ) );

        if ( ! $warranty ) {
            return new WP_Error( 'not_found', 'Warranty not found', array( 'status' => 404 ) );
        }

        if ( $warranty->status !== 'active' ) {
            return new WP_Error( 'inactive', 'Warranty is not active', array( 'status' => 400 ) );
        }

        $claim_number = 'CLM-' . strtoupper( wp_generate_password( 8, false ) );

        $wpdb->insert(
            $this->tables['claims'],
            array(
                'warranty_id'        => $warranty_id,
                'claim_number'       => $claim_number,
                'claim_date'         => current_time( 'Y-m-d' ),
                'issue_description'  => sanitize_textarea_field( $request->get_param( 'issue_description' ) ),
                'claim_amount'       => floatval( $request->get_param( 'claim_amount' ) ),
                'submitted_by'       => get_current_user_id(),
                'attachments'        => wp_json_encode( $request->get_param( 'attachments' ) ),
            )
        );

        return rest_ensure_response( array(
            'success'      => true,
            'claim_id'     => $wpdb->insert_id,
            'claim_number' => $claim_number,
            'message'      => 'Claim submitted successfully',
        ) );
    }

    /**
     * Get single claim.
     */
    public function rest_get_claim( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $claim = $wpdb->get_row( $wpdb->prepare(
            "SELECT c.*, w.provider, w.warranty_number, u.display_name as submitted_by_name
            FROM {$this->tables['claims']} c
            LEFT JOIN {$this->tables['warranties']} w ON c.warranty_id = w.id
            LEFT JOIN {$wpdb->users} u ON c.submitted_by = u.ID
            WHERE c.id = %d",
            $id
        ) );

        if ( ! $claim ) {
            return new WP_Error( 'not_found', 'Claim not found', array( 'status' => 404 ) );
        }

        $claim->attachments = json_decode( $claim->attachments, true );

        return rest_ensure_response( array(
            'success' => true,
            'claim'   => $claim,
        ) );
    }

    /**
     * Update claim.
     */
    public function rest_update_claim( $request ) {
        global $wpdb;

        $id = intval( $request->get_param( 'id' ) );

        $fields = array(
            'status', 'resolution_description', 'approved_amount',
            'resolved_date', 'resolution_notes'
        );

        $data = array();
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
            }
        }

        if ( ! empty( $data ) ) {
            $wpdb->update( $this->tables['claims'], $data, array( 'id' => $id ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Claim updated successfully',
        ) );
    }

    /**
     * Get documents.
     */
    public function rest_get_documents( $request ) {
        global $wpdb;

        $warranty_id = intval( $request->get_param( 'warranty_id' ) );

        $documents = $wpdb->get_results( $wpdb->prepare(
            "SELECT d.*, u.display_name as uploaded_by_name
            FROM {$this->tables['documents']} d
            LEFT JOIN {$wpdb->users} u ON d.uploaded_by = u.ID
            WHERE d.warranty_id = %d
            ORDER BY d.uploaded_at DESC",
            $warranty_id
        ) );

        return rest_ensure_response( array(
            'success'   => true,
            'documents' => $documents,
        ) );
    }

    /**
     * Upload document.
     */
    public function rest_upload_document( $request ) {
        global $wpdb;

        $warranty_id = intval( $request->get_param( 'warranty_id' ) );
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload( $files['file'], array( 'test_form' => false ) );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'], array( 'status' => 400 ) );
        }

        $wpdb->insert(
            $this->tables['documents'],
            array(
                'warranty_id'   => $warranty_id,
                'document_type' => sanitize_text_field( $request->get_param( 'document_type' ) ) ?: 'other',
                'file_name'     => basename( $upload['file'] ),
                'file_path'     => $upload['url'],
                'file_size'     => filesize( $upload['file'] ),
                'mime_type'     => $upload['type'],
                'uploaded_by'   => get_current_user_id(),
            )
        );

        return rest_ensure_response( array(
            'success'     => true,
            'document_id' => $wpdb->insert_id,
            'message'     => 'Document uploaded successfully',
        ) );
    }

    /**
     * Get contacts.
     */
    public function rest_get_contacts( $request ) {
        global $wpdb;

        $contacts = $wpdb->get_results(
            "SELECT c.*, (SELECT COUNT(*) FROM {$this->tables['warranties']} WHERE provider = c.provider) as warranty_count
            FROM {$this->tables['contacts']} c
            ORDER BY c.provider ASC"
        );

        return rest_ensure_response( array(
            'success'  => true,
            'contacts' => $contacts,
        ) );
    }

    /**
     * Create contact.
     */
    public function rest_create_contact( $request ) {
        global $wpdb;

        $provider = sanitize_text_field( $request->get_param( 'provider' ) );

        // Check if exists.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->tables['contacts']} WHERE provider = %s",
            $provider
        ) );

        $data = array(
            'provider'      => $provider,
            'contact_name'  => sanitize_text_field( $request->get_param( 'contact_name' ) ),
            'department'    => sanitize_text_field( $request->get_param( 'department' ) ),
            'phone'         => sanitize_text_field( $request->get_param( 'phone' ) ),
            'email'         => sanitize_email( $request->get_param( 'email' ) ),
            'website'       => esc_url_raw( $request->get_param( 'website' ) ),
            'address'       => sanitize_textarea_field( $request->get_param( 'address' ) ),
            'support_hours' => sanitize_text_field( $request->get_param( 'support_hours' ) ),
            'notes'         => sanitize_textarea_field( $request->get_param( 'notes' ) ),
        );

        if ( $existing ) {
            $wpdb->update( $this->tables['contacts'], $data, array( 'id' => $existing ) );
            $contact_id = $existing;
        } else {
            $wpdb->insert( $this->tables['contacts'], $data );
            $contact_id = $wpdb->insert_id;
        }

        return rest_ensure_response( array(
            'success'    => true,
            'contact_id' => $contact_id,
            'message'    => 'Contact saved successfully',
        ) );
    }

    /**
     * Get stats.
     */
    public function rest_get_stats( $request ) {
        global $wpdb;

        $stats = array(
            'total_warranties' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['warranties']}"
            ),
            'active_warranties' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['warranties']} WHERE status = 'active'"
            ),
            'expiring_30_days' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['warranties']}
                WHERE status = 'active'
                AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            ),
            'expired_warranties' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['warranties']} WHERE status = 'expired'"
            ),
            'total_claims' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['claims']}"
            ),
            'pending_claims' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tables['claims']} WHERE status IN ('submitted', 'under_review')"
            ),
            'total_claim_value' => $wpdb->get_var(
                "SELECT COALESCE(SUM(approved_amount), 0) FROM {$this->tables['claims']} WHERE status = 'completed'"
            ),
            'coverage_value' => $wpdb->get_var(
                "SELECT COALESCE(SUM(coverage_amount), 0) FROM {$this->tables['warranties']} WHERE status = 'active'"
            ),
        );

        // By type breakdown.
        $stats['by_type'] = $wpdb->get_results(
            "SELECT warranty_type, COUNT(*) as count
            FROM {$this->tables['warranties']}
            WHERE status = 'active'
            GROUP BY warranty_type"
        );

        return rest_ensure_response( array(
            'success' => true,
            'stats'   => $stats,
        ) );
    }

    /**
     * Check warranty expirations and send reminders.
     */
    public function check_expirations() {
        global $wpdb;

        // Update expired warranties.
        $wpdb->query(
            "UPDATE {$this->tables['warranties']}
            SET status = 'expired'
            WHERE status = 'active' AND end_date < CURDATE()"
        );

        // Get warranties needing reminders.
        $expiring = $wpdb->get_results(
            "SELECT * FROM {$this->tables['warranties']}
            WHERE status = 'active'
            AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL reminder_days DAY)
            AND (last_reminder_sent IS NULL OR last_reminder_sent < DATE_SUB(NOW(), INTERVAL 7 DAY))"
        );

        foreach ( $expiring as $warranty ) {
            do_action( 'ict_warranty_expiring', $warranty );

            $wpdb->update(
                $this->tables['warranties'],
                array( 'last_reminder_sent' => current_time( 'mysql' ) ),
                array( 'id' => $warranty->id )
            );
        }
    }
}
