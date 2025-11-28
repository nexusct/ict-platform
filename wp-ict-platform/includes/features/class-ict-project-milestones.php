<?php
/**
 * Project Milestones Tracking
 *
 * Manages project milestones and progress tracking.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Project_Milestones
 *
 * Handles project milestone management and tracking.
 */
class ICT_Project_Milestones {

    /**
     * Singleton instance.
     *
     * @var ICT_Project_Milestones|null
     */
    private static $instance = null;

    /**
     * Table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Get singleton instance.
     *
     * @return ICT_Project_Milestones
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
        $this->table_name = $wpdb->prefix . 'ict_project_milestones';
    }

    /**
     * Initialize the feature.
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'ict/v1', '/projects/(?P<project_id>\d+)/milestones', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_milestones' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_milestone' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/milestones/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_milestone' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_milestone' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_milestone' ),
                'permission_callback' => array( $this, 'check_edit_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/milestones/(?P<id>\d+)/complete', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'complete_milestone' ),
            'permission_callback' => array( $this, 'check_edit_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/milestones/reorder', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'reorder_milestones' ),
            'permission_callback' => array( $this, 'check_edit_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/milestones/upcoming', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_upcoming_milestones' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/milestones/overdue', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_overdue_milestones' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    /**
     * Check read permission.
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'edit_ict_projects' ) || current_user_can( 'manage_options' );
    }

    /**
     * Check edit permission.
     *
     * @return bool
     */
    public function check_edit_permission() {
        return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
    }

    /**
     * Create database table if needed.
     */
    public function maybe_create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            due_date date,
            completed_date datetime,
            status enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
            priority enum('low','medium','high','critical') DEFAULT 'medium',
            progress_percent tinyint(3) unsigned DEFAULT 0,
            assigned_to bigint(20) unsigned,
            dependencies text,
            deliverables text,
            notes text,
            sort_order int DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY status (status),
            KEY due_date (due_date),
            KEY assigned_to (assigned_to)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get milestones for a project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_milestones( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );
        $status = $request->get_param( 'status' );

        $where = array( 'project_id = %d' );
        $values = array( $project_id );

        if ( $status ) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $where_clause = implode( ' AND ', $where );

        $milestones = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, u.display_name as assigned_name
                 FROM {$this->table_name} m
                 LEFT JOIN {$wpdb->users} u ON m.assigned_to = u.ID
                 WHERE {$where_clause}
                 ORDER BY m.sort_order ASC, m.due_date ASC",
                $values
            )
        );

        foreach ( $milestones as &$milestone ) {
            $milestone->dependencies = json_decode( $milestone->dependencies, true );
            $milestone->deliverables = json_decode( $milestone->deliverables, true );
            $milestone->is_overdue = $this->is_overdue( $milestone );
        }

        return rest_ensure_response( $milestones );
    }

    /**
     * Get single milestone.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_milestone( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $milestone = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*, u.display_name as assigned_name,
                        p.name as project_name
                 FROM {$this->table_name} m
                 LEFT JOIN {$wpdb->users} u ON m.assigned_to = u.ID
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON m.project_id = p.id
                 WHERE m.id = %d",
                $id
            )
        );

        if ( ! $milestone ) {
            return new WP_Error( 'not_found', 'Milestone not found', array( 'status' => 404 ) );
        }

        $milestone->dependencies = json_decode( $milestone->dependencies, true );
        $milestone->deliverables = json_decode( $milestone->deliverables, true );
        $milestone->is_overdue = $this->is_overdue( $milestone );

        return rest_ensure_response( $milestone );
    }

    /**
     * Create a milestone.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function create_milestone( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );

        // Get next sort order
        $max_order = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(sort_order) FROM {$this->table_name} WHERE project_id = %d",
                $project_id
            )
        );

        $data = array(
            'project_id'   => $project_id,
            'name'         => sanitize_text_field( $request->get_param( 'name' ) ),
            'description'  => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'due_date'     => sanitize_text_field( $request->get_param( 'due_date' ) ),
            'status'       => sanitize_text_field( $request->get_param( 'status' ) ?: 'pending' ),
            'priority'     => sanitize_text_field( $request->get_param( 'priority' ) ?: 'medium' ),
            'assigned_to'  => (int) $request->get_param( 'assigned_to' ) ?: null,
            'dependencies' => wp_json_encode( $request->get_param( 'dependencies' ) ?: array() ),
            'deliverables' => wp_json_encode( $request->get_param( 'deliverables' ) ?: array() ),
            'notes'        => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'sort_order'   => ( $max_order ?? 0 ) + 1,
            'created_by'   => get_current_user_id(),
        );

        $wpdb->insert( $this->table_name, $data );
        $milestone_id = $wpdb->insert_id;

        // Update project progress
        $this->update_project_progress( $project_id );

        // Log activity
        do_action( 'ict_milestone_created', $milestone_id, $project_id, $data );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $milestone_id,
            'message' => 'Milestone created successfully',
        ) );
    }

    /**
     * Update a milestone.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_milestone( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $milestone = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
        );

        if ( ! $milestone ) {
            return new WP_Error( 'not_found', 'Milestone not found', array( 'status' => 404 ) );
        }

        $data = array();
        $fields = array(
            'name'            => 'sanitize_text_field',
            'description'     => 'sanitize_textarea_field',
            'due_date'        => 'sanitize_text_field',
            'status'          => 'sanitize_text_field',
            'priority'        => 'sanitize_text_field',
            'progress_percent' => 'intval',
            'assigned_to'     => 'intval',
            'notes'           => 'sanitize_textarea_field',
        );

        foreach ( $fields as $field => $sanitizer ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = call_user_func( $sanitizer, $value );
            }
        }

        $json_fields = array( 'dependencies', 'deliverables' );
        foreach ( $json_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = wp_json_encode( $value );
            }
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->table_name, $data, array( 'id' => $id ) );

        // Update project progress
        $this->update_project_progress( $milestone->project_id );

        do_action( 'ict_milestone_updated', $id, $milestone->project_id, $data );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Milestone updated successfully',
        ) );
    }

    /**
     * Delete a milestone.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function delete_milestone( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $milestone = $wpdb->get_row(
            $wpdb->prepare( "SELECT project_id FROM {$this->table_name} WHERE id = %d", $id )
        );

        if ( ! $milestone ) {
            return new WP_Error( 'not_found', 'Milestone not found', array( 'status' => 404 ) );
        }

        $wpdb->delete( $this->table_name, array( 'id' => $id ) );

        // Update project progress
        $this->update_project_progress( $milestone->project_id );

        do_action( 'ict_milestone_deleted', $id, $milestone->project_id );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Milestone deleted successfully',
        ) );
    }

    /**
     * Mark milestone as complete.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function complete_milestone( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );

        $milestone = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
        );

        if ( ! $milestone ) {
            return new WP_Error( 'not_found', 'Milestone not found', array( 'status' => 404 ) );
        }

        // Check dependencies
        $dependencies = json_decode( $milestone->dependencies, true );
        if ( ! empty( $dependencies ) ) {
            $incomplete = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name}
                     WHERE id IN (" . implode( ',', array_map( 'intval', $dependencies ) ) . ")
                     AND status != 'completed'"
                )
            );

            if ( $incomplete > 0 ) {
                return new WP_Error(
                    'dependencies_incomplete',
                    'Cannot complete milestone: dependencies not yet completed',
                    array( 'status' => 400 )
                );
            }
        }

        $wpdb->update(
            $this->table_name,
            array(
                'status'           => 'completed',
                'progress_percent' => 100,
                'completed_date'   => current_time( 'mysql' ),
            ),
            array( 'id' => $id )
        );

        // Update project progress
        $this->update_project_progress( $milestone->project_id );

        // Send notification
        do_action( 'ict_milestone_completed', $id, $milestone->project_id );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Milestone marked as complete',
        ) );
    }

    /**
     * Reorder milestones.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function reorder_milestones( $request ) {
        global $wpdb;

        $order = $request->get_param( 'order' );

        if ( ! is_array( $order ) ) {
            return new WP_Error( 'invalid_order', 'Invalid order data', array( 'status' => 400 ) );
        }

        foreach ( $order as $index => $milestone_id ) {
            $wpdb->update(
                $this->table_name,
                array( 'sort_order' => $index ),
                array( 'id' => (int) $milestone_id )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Milestones reordered successfully',
        ) );
    }

    /**
     * Get upcoming milestones.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_upcoming_milestones( $request ) {
        global $wpdb;

        $days = (int) $request->get_param( 'days' ) ?: 7;
        $limit = (int) $request->get_param( 'limit' ) ?: 10;

        $milestones = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, p.name as project_name, u.display_name as assigned_name
                 FROM {$this->table_name} m
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON m.project_id = p.id
                 LEFT JOIN {$wpdb->users} u ON m.assigned_to = u.ID
                 WHERE m.status NOT IN ('completed', 'cancelled')
                 AND m.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
                 ORDER BY m.due_date ASC
                 LIMIT %d",
                $days,
                $limit
            )
        );

        return rest_ensure_response( $milestones );
    }

    /**
     * Get overdue milestones.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_overdue_milestones( $request ) {
        global $wpdb;

        $limit = (int) $request->get_param( 'limit' ) ?: 20;

        $milestones = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, p.name as project_name, u.display_name as assigned_name,
                        DATEDIFF(CURDATE(), m.due_date) as days_overdue
                 FROM {$this->table_name} m
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON m.project_id = p.id
                 LEFT JOIN {$wpdb->users} u ON m.assigned_to = u.ID
                 WHERE m.status NOT IN ('completed', 'cancelled')
                 AND m.due_date < CURDATE()
                 ORDER BY m.due_date ASC
                 LIMIT %d",
                $limit
            )
        );

        return rest_ensure_response( $milestones );
    }

    /**
     * Check if milestone is overdue.
     *
     * @param object $milestone Milestone object.
     * @return bool
     */
    private function is_overdue( $milestone ) {
        if ( in_array( $milestone->status, array( 'completed', 'cancelled' ), true ) ) {
            return false;
        }

        if ( ! $milestone->due_date ) {
            return false;
        }

        return strtotime( $milestone->due_date ) < strtotime( 'today' );
    }

    /**
     * Update project overall progress.
     *
     * @param int $project_id Project ID.
     */
    private function update_project_progress( $project_id ) {
        global $wpdb;

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        AVG(progress_percent) as avg_progress
                 FROM {$this->table_name}
                 WHERE project_id = %d",
                $project_id
            )
        );

        $progress = 0;
        if ( $stats->total > 0 ) {
            $progress = round( ( $stats->completed / $stats->total ) * 100 );
        }

        $wpdb->update(
            $wpdb->prefix . 'ict_projects',
            array( 'progress_percent' => $progress ),
            array( 'id' => $project_id )
        );
    }

    /**
     * Get milestone statistics for a project.
     *
     * @param int $project_id Project ID.
     * @return array
     */
    public function get_project_stats( $project_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status NOT IN ('completed', 'cancelled') AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                 FROM {$this->table_name}
                 WHERE project_id = %d",
                $project_id
            ),
            ARRAY_A
        );
    }
}
