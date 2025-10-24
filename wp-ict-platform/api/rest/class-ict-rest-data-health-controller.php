<?php
/**
 * REST API Data Health Controller
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_REST_Data_Health_Controller extends WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'ict/v1';
        $this->rest_base = 'reports';
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/data-health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_report' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }

    public function permission_check() {
        return current_user_can( 'view_ict_reports' );
    }

    public function get_report( $request ) {
        global $wpdb;

        // Duplicate projects by name
        $duplicate_projects = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT project_name, COUNT(*) c FROM " . ICT_PROJECTS_TABLE . " GROUP BY project_name HAVING c > 1) t" );
        // Orphan time entries
        $orphan_time_entries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . ICT_TIME_ENTRIES_TABLE . " te LEFT JOIN " . ICT_PROJECTS_TABLE . " p ON te.project_id = p.id WHERE p.id IS NULL" );
        // Negative total hours
        $negative_hours = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE total_hours < 0" );
        // Low stock items (<= threshold)
        $threshold = (int) get_option( 'ict_low_stock_threshold', 10 );
        $low_stock = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE quantity_available <= %d AND is_active = 1", $threshold ) );
        // Sync errors last 24h
        $sync_errors_24h = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . ICT_SYNC_LOG_TABLE . " WHERE status = 'error' AND synced_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)" );

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'duplicates' => array(
                    'projects_by_name' => $duplicate_projects,
                ),
                'integrity' => array(
                    'orphan_time_entries' => $orphan_time_entries,
                ),
                'anomalies' => array(
                    'negative_hours' => $negative_hours,
                ),
                'inventory' => array(
                    'low_stock' => $low_stock,
                    'threshold' => $threshold,
                ),
                'sync' => array(
                    'errors_last_24h' => $sync_errors_24h,
                ),
            ),
        ), 200 );
    }
}

