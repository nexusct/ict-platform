<?php
/**
 * Project Activity Feed
 *
 * Tracks and displays all project-related activities.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Activity_Feed
 *
 * Manages project activity tracking and feed display.
 */
class ICT_Activity_Feed {

    /**
     * Singleton instance.
     *
     * @var ICT_Activity_Feed|null
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
     * @return ICT_Activity_Feed
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
        $this->table_name = $wpdb->prefix . 'ict_activity_log';
    }

    /**
     * Initialize the feature.
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
        $this->register_activity_hooks();
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'ict/v1', '/activity', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_global_activity' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/projects/(?P<project_id>\d+)/activity', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_project_activity' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/users/(?P<user_id>\d+)/activity', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_user_activity' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/activity/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_activity' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/activity/types', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_activity_types' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    /**
     * Check permission.
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'edit_ict_projects' ) || current_user_can( 'manage_options' );
    }

    /**
     * Check admin permission.
     *
     * @return bool
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Create database table if needed.
     */
    public function maybe_create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned,
            action varchar(50) NOT NULL,
            description text NOT NULL,
            old_value longtext,
            new_value longtext,
            metadata longtext,
            user_id bigint(20) unsigned NOT NULL,
            ip_address varchar(45),
            user_agent varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entity_type (entity_type),
            KEY entity_id (entity_id),
            KEY project_id (project_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Register activity tracking hooks.
     */
    private function register_activity_hooks() {
        // Project activities
        add_action( 'ict_project_created', array( $this, 'log_project_created' ), 10, 2 );
        add_action( 'ict_project_updated', array( $this, 'log_project_updated' ), 10, 3 );
        add_action( 'ict_project_status_changed', array( $this, 'log_project_status_changed' ), 10, 3 );

        // Milestone activities
        add_action( 'ict_milestone_created', array( $this, 'log_milestone_created' ), 10, 3 );
        add_action( 'ict_milestone_completed', array( $this, 'log_milestone_completed' ), 10, 2 );
        add_action( 'ict_milestone_updated', array( $this, 'log_milestone_updated' ), 10, 3 );

        // Time entry activities
        add_action( 'ict_time_entry_created', array( $this, 'log_time_entry' ), 10, 2 );
        add_action( 'ict_clock_in', array( $this, 'log_clock_in' ), 10, 2 );
        add_action( 'ict_clock_out', array( $this, 'log_clock_out' ), 10, 2 );

        // Expense activities
        add_action( 'ict_expense_created', array( $this, 'log_expense_created' ), 10, 3 );
        add_action( 'ict_expense_approved', array( $this, 'log_expense_approved' ), 10, 2 );

        // Document activities
        add_action( 'ict_document_uploaded', array( $this, 'log_document_uploaded' ), 10, 3 );

        // Comment activities
        add_action( 'ict_comment_added', array( $this, 'log_comment_added' ), 10, 3 );

        // Resource activities
        add_action( 'ict_resource_assigned', array( $this, 'log_resource_assigned' ), 10, 3 );
        add_action( 'ict_resource_unassigned', array( $this, 'log_resource_unassigned' ), 10, 3 );
    }

    /**
     * Log an activity.
     *
     * @param array $data Activity data.
     * @return int|false Activity ID or false on failure.
     */
    public function log( $data ) {
        global $wpdb;

        $defaults = array(
            'entity_type' => '',
            'entity_id'   => 0,
            'project_id'  => null,
            'action'      => '',
            'description' => '',
            'old_value'   => null,
            'new_value'   => null,
            'metadata'    => null,
            'user_id'     => get_current_user_id(),
            'ip_address'  => $this->get_client_ip(),
            'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
        );

        $data = wp_parse_args( $data, $defaults );

        // JSON encode complex values
        if ( is_array( $data['old_value'] ) || is_object( $data['old_value'] ) ) {
            $data['old_value'] = wp_json_encode( $data['old_value'] );
        }
        if ( is_array( $data['new_value'] ) || is_object( $data['new_value'] ) ) {
            $data['new_value'] = wp_json_encode( $data['new_value'] );
        }
        if ( is_array( $data['metadata'] ) || is_object( $data['metadata'] ) ) {
            $data['metadata'] = wp_json_encode( $data['metadata'] );
        }

        $result = $wpdb->insert( $this->table_name, $data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get global activity feed.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_global_activity( $request ) {
        global $wpdb;

        $page = (int) $request->get_param( 'page' ) ?: 1;
        $per_page = (int) $request->get_param( 'per_page' ) ?: 20;
        $entity_type = $request->get_param( 'entity_type' );
        $action = $request->get_param( 'action' );
        $from_date = $request->get_param( 'from_date' );
        $to_date = $request->get_param( 'to_date' );

        $offset = ( $page - 1 ) * $per_page;

        $where = array( '1=1' );
        $values = array();

        if ( $entity_type ) {
            $where[] = 'a.entity_type = %s';
            $values[] = $entity_type;
        }

        if ( $action ) {
            $where[] = 'a.action = %s';
            $values[] = $action;
        }

        if ( $from_date ) {
            $where[] = 'a.created_at >= %s';
            $values[] = $from_date . ' 00:00:00';
        }

        if ( $to_date ) {
            $where[] = 'a.created_at <= %s';
            $values[] = $to_date . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} a WHERE {$where_clause}";
        if ( ! empty( $values ) ) {
            $total = $wpdb->get_var( $wpdb->prepare( $count_query, $values ) );
        } else {
            $total = $wpdb->get_var( $count_query );
        }

        // Get activities
        $query = "SELECT a.*, u.display_name as user_name, u.user_email,
                         p.name as project_name
                  FROM {$this->table_name} a
                  LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}ict_projects p ON a.project_id = p.id
                  WHERE {$where_clause}
                  ORDER BY a.created_at DESC
                  LIMIT %d OFFSET %d";

        $values[] = $per_page;
        $values[] = $offset;

        $activities = $wpdb->get_results( $wpdb->prepare( $query, $values ) );

        foreach ( $activities as &$activity ) {
            $activity->old_value = json_decode( $activity->old_value, true );
            $activity->new_value = json_decode( $activity->new_value, true );
            $activity->metadata = json_decode( $activity->metadata, true );
            $activity->icon = $this->get_activity_icon( $activity->entity_type, $activity->action );
            $activity->time_ago = human_time_diff( strtotime( $activity->created_at ), current_time( 'timestamp' ) ) . ' ago';
        }

        return rest_ensure_response( array(
            'activities' => $activities,
            'total'      => (int) $total,
            'pages'      => ceil( $total / $per_page ),
            'page'       => $page,
        ) );
    }

    /**
     * Get project activity feed.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_project_activity( $request ) {
        global $wpdb;

        $project_id = (int) $request->get_param( 'project_id' );
        $page = (int) $request->get_param( 'page' ) ?: 1;
        $per_page = (int) $request->get_param( 'per_page' ) ?: 20;
        $offset = ( $page - 1 ) * $per_page;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE project_id = %d",
                $project_id
            )
        );

        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name, u.user_email
                 FROM {$this->table_name} a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE a.project_id = %d
                 ORDER BY a.created_at DESC
                 LIMIT %d OFFSET %d",
                $project_id,
                $per_page,
                $offset
            )
        );

        foreach ( $activities as &$activity ) {
            $activity->old_value = json_decode( $activity->old_value, true );
            $activity->new_value = json_decode( $activity->new_value, true );
            $activity->metadata = json_decode( $activity->metadata, true );
            $activity->icon = $this->get_activity_icon( $activity->entity_type, $activity->action );
            $activity->time_ago = human_time_diff( strtotime( $activity->created_at ), current_time( 'timestamp' ) ) . ' ago';
        }

        return rest_ensure_response( array(
            'activities' => $activities,
            'total'      => (int) $total,
            'pages'      => ceil( $total / $per_page ),
            'page'       => $page,
        ) );
    }

    /**
     * Get user activity feed.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_user_activity( $request ) {
        global $wpdb;

        $user_id = (int) $request->get_param( 'user_id' );
        $page = (int) $request->get_param( 'page' ) ?: 1;
        $per_page = (int) $request->get_param( 'per_page' ) ?: 20;
        $offset = ( $page - 1 ) * $per_page;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );

        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, p.name as project_name
                 FROM {$this->table_name} a
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON a.project_id = p.id
                 WHERE a.user_id = %d
                 ORDER BY a.created_at DESC
                 LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            )
        );

        foreach ( $activities as &$activity ) {
            $activity->old_value = json_decode( $activity->old_value, true );
            $activity->new_value = json_decode( $activity->new_value, true );
            $activity->metadata = json_decode( $activity->metadata, true );
            $activity->icon = $this->get_activity_icon( $activity->entity_type, $activity->action );
            $activity->time_ago = human_time_diff( strtotime( $activity->created_at ), current_time( 'timestamp' ) ) . ' ago';
        }

        return rest_ensure_response( array(
            'activities' => $activities,
            'total'      => (int) $total,
            'pages'      => ceil( $total / $per_page ),
            'page'       => $page,
        ) );
    }

    /**
     * Delete an activity entry.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function delete_activity( $request ) {
        global $wpdb;

        $id = (int) $request->get_param( 'id' );
        $wpdb->delete( $this->table_name, array( 'id' => $id ) );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Activity deleted successfully',
        ) );
    }

    /**
     * Get activity types.
     *
     * @return WP_REST_Response
     */
    public function get_activity_types() {
        return rest_ensure_response( array(
            'entity_types' => array(
                'project'    => 'Projects',
                'milestone'  => 'Milestones',
                'time_entry' => 'Time Entries',
                'expense'    => 'Expenses',
                'document'   => 'Documents',
                'comment'    => 'Comments',
                'resource'   => 'Resources',
                'inventory'  => 'Inventory',
                'po'         => 'Purchase Orders',
            ),
            'actions' => array(
                'created'        => 'Created',
                'updated'        => 'Updated',
                'deleted'        => 'Deleted',
                'completed'      => 'Completed',
                'approved'       => 'Approved',
                'rejected'       => 'Rejected',
                'assigned'       => 'Assigned',
                'unassigned'     => 'Unassigned',
                'uploaded'       => 'Uploaded',
                'downloaded'     => 'Downloaded',
                'clock_in'       => 'Clock In',
                'clock_out'      => 'Clock Out',
                'status_changed' => 'Status Changed',
            ),
        ) );
    }

    // Activity logging methods

    /**
     * Log project created.
     *
     * @param int   $project_id Project ID.
     * @param array $data       Project data.
     */
    public function log_project_created( $project_id, $data ) {
        $this->log( array(
            'entity_type' => 'project',
            'entity_id'   => $project_id,
            'project_id'  => $project_id,
            'action'      => 'created',
            'description' => sprintf( 'Created project: %s', $data['name'] ?? 'Unknown' ),
            'new_value'   => $data,
        ) );
    }

    /**
     * Log project updated.
     *
     * @param int   $project_id Project ID.
     * @param array $old_data   Old data.
     * @param array $new_data   New data.
     */
    public function log_project_updated( $project_id, $old_data, $new_data ) {
        $changes = array_diff_assoc( (array) $new_data, (array) $old_data );
        if ( empty( $changes ) ) {
            return;
        }

        $this->log( array(
            'entity_type' => 'project',
            'entity_id'   => $project_id,
            'project_id'  => $project_id,
            'action'      => 'updated',
            'description' => 'Updated project details',
            'old_value'   => $old_data,
            'new_value'   => $new_data,
            'metadata'    => array( 'changed_fields' => array_keys( $changes ) ),
        ) );
    }

    /**
     * Log project status changed.
     *
     * @param int    $project_id Project ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     */
    public function log_project_status_changed( $project_id, $old_status, $new_status ) {
        $this->log( array(
            'entity_type' => 'project',
            'entity_id'   => $project_id,
            'project_id'  => $project_id,
            'action'      => 'status_changed',
            'description' => sprintf( 'Changed status from %s to %s', $old_status, $new_status ),
            'old_value'   => $old_status,
            'new_value'   => $new_status,
        ) );
    }

    /**
     * Log milestone created.
     *
     * @param int   $milestone_id Milestone ID.
     * @param int   $project_id   Project ID.
     * @param array $data         Milestone data.
     */
    public function log_milestone_created( $milestone_id, $project_id, $data ) {
        $this->log( array(
            'entity_type' => 'milestone',
            'entity_id'   => $milestone_id,
            'project_id'  => $project_id,
            'action'      => 'created',
            'description' => sprintf( 'Created milestone: %s', $data['name'] ?? 'Unknown' ),
            'new_value'   => $data,
        ) );
    }

    /**
     * Log milestone completed.
     *
     * @param int $milestone_id Milestone ID.
     * @param int $project_id   Project ID.
     */
    public function log_milestone_completed( $milestone_id, $project_id ) {
        global $wpdb;

        $milestone = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}ict_project_milestones WHERE id = %d",
                $milestone_id
            )
        );

        $this->log( array(
            'entity_type' => 'milestone',
            'entity_id'   => $milestone_id,
            'project_id'  => $project_id,
            'action'      => 'completed',
            'description' => sprintf( 'Completed milestone: %s', $milestone ? $milestone->name : 'Unknown' ),
        ) );
    }

    /**
     * Log milestone updated.
     *
     * @param int   $milestone_id Milestone ID.
     * @param int   $project_id   Project ID.
     * @param array $data         Updated data.
     */
    public function log_milestone_updated( $milestone_id, $project_id, $data ) {
        $this->log( array(
            'entity_type' => 'milestone',
            'entity_id'   => $milestone_id,
            'project_id'  => $project_id,
            'action'      => 'updated',
            'description' => 'Updated milestone',
            'new_value'   => $data,
        ) );
    }

    /**
     * Log time entry.
     *
     * @param int   $entry_id Entry ID.
     * @param array $data     Entry data.
     */
    public function log_time_entry( $entry_id, $data ) {
        $this->log( array(
            'entity_type' => 'time_entry',
            'entity_id'   => $entry_id,
            'project_id'  => $data['project_id'] ?? null,
            'action'      => 'created',
            'description' => sprintf( 'Logged %.2f hours', $data['hours'] ?? 0 ),
            'new_value'   => $data,
        ) );
    }

    /**
     * Log clock in.
     *
     * @param int   $entry_id Entry ID.
     * @param array $data     Entry data.
     */
    public function log_clock_in( $entry_id, $data ) {
        $this->log( array(
            'entity_type' => 'time_entry',
            'entity_id'   => $entry_id,
            'project_id'  => $data['project_id'] ?? null,
            'action'      => 'clock_in',
            'description' => 'Clocked in',
            'metadata'    => $data,
        ) );
    }

    /**
     * Log clock out.
     *
     * @param int   $entry_id Entry ID.
     * @param array $data     Entry data.
     */
    public function log_clock_out( $entry_id, $data ) {
        $this->log( array(
            'entity_type' => 'time_entry',
            'entity_id'   => $entry_id,
            'project_id'  => $data['project_id'] ?? null,
            'action'      => 'clock_out',
            'description' => sprintf( 'Clocked out (%.2f hours)', $data['hours'] ?? 0 ),
            'metadata'    => $data,
        ) );
    }

    /**
     * Log expense created.
     *
     * @param int   $expense_id Expense ID.
     * @param int   $project_id Project ID.
     * @param array $data       Expense data.
     */
    public function log_expense_created( $expense_id, $project_id, $data ) {
        $this->log( array(
            'entity_type' => 'expense',
            'entity_id'   => $expense_id,
            'project_id'  => $project_id,
            'action'      => 'created',
            'description' => sprintf( 'Added expense: %s (%.2f)', $data['description'] ?? '', $data['amount'] ?? 0 ),
            'new_value'   => $data,
        ) );
    }

    /**
     * Log expense approved.
     *
     * @param int $expense_id Expense ID.
     * @param int $project_id Project ID.
     */
    public function log_expense_approved( $expense_id, $project_id ) {
        $this->log( array(
            'entity_type' => 'expense',
            'entity_id'   => $expense_id,
            'project_id'  => $project_id,
            'action'      => 'approved',
            'description' => 'Approved expense',
        ) );
    }

    /**
     * Log document uploaded.
     *
     * @param int    $document_id Document ID.
     * @param int    $project_id  Project ID.
     * @param string $filename    File name.
     */
    public function log_document_uploaded( $document_id, $project_id, $filename ) {
        $this->log( array(
            'entity_type' => 'document',
            'entity_id'   => $document_id,
            'project_id'  => $project_id,
            'action'      => 'uploaded',
            'description' => sprintf( 'Uploaded document: %s', $filename ),
        ) );
    }

    /**
     * Log comment added.
     *
     * @param int    $comment_id Comment ID.
     * @param int    $project_id Project ID.
     * @param string $content    Comment content.
     */
    public function log_comment_added( $comment_id, $project_id, $content ) {
        $this->log( array(
            'entity_type' => 'comment',
            'entity_id'   => $comment_id,
            'project_id'  => $project_id,
            'action'      => 'created',
            'description' => 'Added a comment',
            'new_value'   => substr( $content, 0, 200 ),
        ) );
    }

    /**
     * Log resource assigned.
     *
     * @param int $resource_id Resource ID.
     * @param int $project_id  Project ID.
     * @param int $user_id     User ID.
     */
    public function log_resource_assigned( $resource_id, $project_id, $user_id ) {
        $user = get_userdata( $user_id );
        $this->log( array(
            'entity_type' => 'resource',
            'entity_id'   => $resource_id,
            'project_id'  => $project_id,
            'action'      => 'assigned',
            'description' => sprintf( 'Assigned %s to project', $user ? $user->display_name : 'User' ),
            'metadata'    => array( 'assigned_user_id' => $user_id ),
        ) );
    }

    /**
     * Log resource unassigned.
     *
     * @param int $resource_id Resource ID.
     * @param int $project_id  Project ID.
     * @param int $user_id     User ID.
     */
    public function log_resource_unassigned( $resource_id, $project_id, $user_id ) {
        $user = get_userdata( $user_id );
        $this->log( array(
            'entity_type' => 'resource',
            'entity_id'   => $resource_id,
            'project_id'  => $project_id,
            'action'      => 'unassigned',
            'description' => sprintf( 'Removed %s from project', $user ? $user->display_name : 'User' ),
            'metadata'    => array( 'unassigned_user_id' => $user_id ),
        ) );
    }

    /**
     * Get activity icon based on type and action.
     *
     * @param string $entity_type Entity type.
     * @param string $action      Action.
     * @return string
     */
    private function get_activity_icon( $entity_type, $action ) {
        $icons = array(
            'project'    => 'dashicons-portfolio',
            'milestone'  => 'dashicons-flag',
            'time_entry' => 'dashicons-clock',
            'expense'    => 'dashicons-money-alt',
            'document'   => 'dashicons-media-document',
            'comment'    => 'dashicons-format-chat',
            'resource'   => 'dashicons-admin-users',
            'inventory'  => 'dashicons-archive',
            'po'         => 'dashicons-cart',
        );

        return $icons[ $entity_type ] ?? 'dashicons-marker';
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Clean old activity logs.
     *
     * @param int $days Days to keep.
     */
    public function cleanup( $days = 90 ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
