<?php
/**
 * Dashboard Widgets
 *
 * Customizable dashboard with drag-and-drop widgets.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Dashboard_Widgets {

	private static $instance = null;
	private $layouts_table;
	private $widgets_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->layouts_table = $wpdb->prefix . 'ict_dashboard_layouts';
		$this->widgets_table = $wpdb->prefix . 'ict_dashboard_widgets';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_wp_dashboard_widgets' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/dashboard/widgets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_available_widgets' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/dashboard/layout',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_layout' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_layout' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/dashboard/layout/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_layout' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/dashboard/widget/(?P<widget_id>[a-z_]+)/data',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_widget_data' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/dashboard/widget/(?P<widget_id>[a-z_]+)/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_widget_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_widget_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/dashboard/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard_summary' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return is_user_logged_in();
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->layouts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            layout_data longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->widgets_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            widget_id varchar(50) NOT NULL,
            settings text,
            is_visible tinyint(1) DEFAULT 1,
            position int DEFAULT 0,
            width enum('small','medium','large','full') DEFAULT 'medium',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_widget (user_id, widget_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	public function get_available_widgets( $request ) {
		$widgets = array(
			array(
				'id'           => 'project_summary',
				'name'         => 'Project Summary',
				'description'  => 'Overview of active projects and their status',
				'icon'         => 'folder',
				'category'     => 'projects',
				'sizes'        => array( 'small', 'medium', 'large' ),
				'default_size' => 'medium',
				'refreshable'  => true,
			),
			array(
				'id'           => 'time_tracking',
				'name'         => 'Time Tracking',
				'description'  => 'Today\'s time entries and clock status',
				'icon'         => 'clock',
				'category'     => 'time',
				'sizes'        => array( 'small', 'medium' ),
				'default_size' => 'small',
				'refreshable'  => true,
			),
			array(
				'id'           => 'weekly_hours',
				'name'         => 'Weekly Hours',
				'description'  => 'Hours worked this week with chart',
				'icon'         => 'chart-bar',
				'category'     => 'time',
				'sizes'        => array( 'medium', 'large' ),
				'default_size' => 'medium',
				'refreshable'  => true,
			),
			array(
				'id'           => 'my_tasks',
				'name'         => 'My Tasks',
				'description'  => 'Tasks assigned to you',
				'icon'         => 'check-circle',
				'category'     => 'tasks',
				'sizes'        => array( 'medium', 'large' ),
				'default_size' => 'medium',
				'refreshable'  => true,
				'configurable' => true,
			),
			array(
				'id'           => 'upcoming_deadlines',
				'name'         => 'Upcoming Deadlines',
				'description'  => 'Projects and tasks due soon',
				'icon'         => 'calendar',
				'category'     => 'projects',
				'sizes'        => array( 'small', 'medium' ),
				'default_size' => 'medium',
				'refreshable'  => true,
			),
			array(
				'id'           => 'inventory_alerts',
				'name'         => 'Inventory Alerts',
				'description'  => 'Low stock and critical inventory items',
				'icon'         => 'exclamation-triangle',
				'category'     => 'inventory',
				'sizes'        => array( 'small', 'medium' ),
				'default_size' => 'small',
				'refreshable'  => true,
			),
			array(
				'id'           => 'recent_activity',
				'name'         => 'Recent Activity',
				'description'  => 'Latest activity across the platform',
				'icon'         => 'stream',
				'category'     => 'general',
				'sizes'        => array( 'medium', 'large' ),
				'default_size' => 'large',
				'refreshable'  => true,
			),
			array(
				'id'           => 'team_availability',
				'name'         => 'Team Availability',
				'description'  => 'Who\'s available today',
				'icon'         => 'users',
				'category'     => 'team',
				'sizes'        => array( 'small', 'medium' ),
				'default_size' => 'medium',
				'refreshable'  => true,
			),
			array(
				'id'           => 'revenue_chart',
				'name'         => 'Revenue Chart',
				'description'  => 'Monthly revenue trends',
				'icon'         => 'chart-line',
				'category'     => 'finance',
				'sizes'        => array( 'medium', 'large', 'full' ),
				'default_size' => 'large',
				'refreshable'  => true,
				'permission'   => 'manage_ict_finance',
			),
			array(
				'id'           => 'pending_approvals',
				'name'         => 'Pending Approvals',
				'description'  => 'Items waiting for your approval',
				'icon'         => 'clipboard-check',
				'category'     => 'approvals',
				'sizes'        => array( 'small', 'medium' ),
				'default_size' => 'small',
				'refreshable'  => true,
			),
			array(
				'id'           => 'quick_actions',
				'name'         => 'Quick Actions',
				'description'  => 'Shortcuts to common tasks',
				'icon'         => 'bolt',
				'category'     => 'general',
				'sizes'        => array( 'small' ),
				'default_size' => 'small',
				'refreshable'  => false,
			),
			array(
				'id'           => 'announcements',
				'name'         => 'Announcements',
				'description'  => 'Latest company announcements',
				'icon'         => 'bullhorn',
				'category'     => 'general',
				'sizes'        => array( 'medium', 'large' ),
				'default_size' => 'medium',
				'refreshable'  => true,
			),
			array(
				'id'           => 'messages',
				'name'         => 'Messages',
				'description'  => 'Recent messages and conversations',
				'icon'         => 'envelope',
				'category'     => 'communication',
				'sizes'        => array( 'small', 'medium' ),
				'default_size' => 'small',
				'refreshable'  => true,
			),
			array(
				'id'           => 'kpi_summary',
				'name'         => 'KPI Summary',
				'description'  => 'Key performance indicators',
				'icon'         => 'tachometer-alt',
				'category'     => 'analytics',
				'sizes'        => array( 'medium', 'large', 'full' ),
				'default_size' => 'large',
				'refreshable'  => true,
				'permission'   => 'view_ict_reports',
			),
			array(
				'id'           => 'weather',
				'name'         => 'Weather',
				'description'  => 'Current weather for field work planning',
				'icon'         => 'cloud-sun',
				'category'     => 'general',
				'sizes'        => array( 'small' ),
				'default_size' => 'small',
				'refreshable'  => true,
				'configurable' => true,
			),
		);

		// Filter by user permissions
		$user_widgets = array_filter(
			$widgets,
			function ( $widget ) {
				if ( ! isset( $widget['permission'] ) ) {
					return true;
				}
				return current_user_can( $widget['permission'] );
			}
		);

		return rest_ensure_response( array_values( $user_widgets ) );
	}

	public function get_layout( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$layout = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->layouts_table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( $layout ) {
			$layout->layout_data = json_decode( $layout->layout_data, true );
			return rest_ensure_response( $layout );
		}

		// Return default layout
		return rest_ensure_response(
			array(
				'user_id'     => $user_id,
				'is_default'  => true,
				'layout_data' => $this->get_default_layout(),
			)
		);
	}

	public function save_layout( $request ) {
		global $wpdb;
		$user_id     = get_current_user_id();
		$layout_data = $request->get_param( 'layout_data' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->layouts_table} WHERE user_id = %d",
				$user_id
			)
		);

		$data = array(
			'user_id'     => $user_id,
			'layout_data' => wp_json_encode( $layout_data ),
			'is_default'  => 0,
		);

		if ( $existing ) {
			$wpdb->update( $this->layouts_table, $data, array( 'id' => $existing ) );
		} else {
			$wpdb->insert( $this->layouts_table, $data );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function reset_layout( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$wpdb->delete( $this->layouts_table, array( 'user_id' => $user_id ) );
		$wpdb->delete( $this->widgets_table, array( 'user_id' => $user_id ) );

		return rest_ensure_response(
			array(
				'success'     => true,
				'layout_data' => $this->get_default_layout(),
			)
		);
	}

	public function get_widget_data( $request ) {
		$widget_id = sanitize_text_field( $request->get_param( 'widget_id' ) );
		$params    = $request->get_params();

		$method = 'get_' . $widget_id . '_data';

		if ( method_exists( $this, $method ) ) {
			return rest_ensure_response( $this->$method( $params ) );
		}

		return new WP_Error( 'unknown_widget', 'Unknown widget', array( 'status' => 404 ) );
	}

	public function get_widget_settings( $request ) {
		global $wpdb;
		$user_id   = get_current_user_id();
		$widget_id = sanitize_text_field( $request->get_param( 'widget_id' ) );

		$widget = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->widgets_table} WHERE user_id = %d AND widget_id = %s",
				$user_id,
				$widget_id
			)
		);

		if ( $widget ) {
			$widget->settings = json_decode( $widget->settings, true );
			return rest_ensure_response( $widget );
		}

		return rest_ensure_response(
			array(
				'widget_id' => $widget_id,
				'settings'  => $this->get_default_widget_settings( $widget_id ),
			)
		);
	}

	public function save_widget_settings( $request ) {
		global $wpdb;
		$user_id   = get_current_user_id();
		$widget_id = sanitize_text_field( $request->get_param( 'widget_id' ) );
		$settings  = $request->get_param( 'settings' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->widgets_table} WHERE user_id = %d AND widget_id = %s",
				$user_id,
				$widget_id
			)
		);

		$data = array(
			'user_id'   => $user_id,
			'widget_id' => $widget_id,
			'settings'  => wp_json_encode( $settings ),
		);

		if ( $existing ) {
			$wpdb->update( $this->widgets_table, $data, array( 'id' => $existing ) );
		} else {
			$wpdb->insert( $this->widgets_table, $data );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_dashboard_summary( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$summary = array();

		// Active projects count
		$summary['active_projects'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects WHERE status = 'in_progress'"
		);

		// Today's hours
		$summary['today_hours'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))), 0) / 60
             FROM {$wpdb->prefix}ict_time_entries
             WHERE user_id = %d AND DATE(start_time) = CURDATE()",
				$user_id
			)
		);

		// Pending tasks
		$summary['pending_tasks'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}posts
             WHERE post_type = 'ict_task' AND post_status = 'pending'
             AND post_author = %d",
				$user_id
			)
		);

		// Low stock items
		$summary['low_stock_items'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ict_inventory_items
             WHERE quantity <= reorder_point AND reorder_point > 0"
		);

		// Unread messages
		$summary['unread_messages'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ict_messages m
             JOIN {$wpdb->prefix}ict_conversation_participants cp ON m.conversation_id = cp.conversation_id
             WHERE cp.user_id = %d AND m.created_at > cp.last_read_at",
				$user_id
			)
		);

		// Pending approvals
		$summary['pending_approvals'] = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ict_time_approvals
             WHERE approver_id = %d AND status = 'pending'",
				$user_id
			)
		);

		return rest_ensure_response( $summary );
	}

	public function add_wp_dashboard_widgets() {
		wp_add_dashboard_widget(
			'ict_quick_summary',
			'ICT Platform Summary',
			array( $this, 'render_wp_summary_widget' )
		);
	}

	public function render_wp_summary_widget() {
		global $wpdb;
		$user_id = get_current_user_id();

		$active_projects = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects WHERE status = 'in_progress'"
		);

		$today_hours = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))), 0) / 60
             FROM {$wpdb->prefix}ict_time_entries
             WHERE user_id = %d AND DATE(start_time) = CURDATE()",
				$user_id
			)
		);

		echo '<div class="ict-wp-dashboard-widget">';
		echo '<p><strong>Active Projects:</strong> ' . esc_html( $active_projects ) . '</p>';
		echo '<p><strong>Today\'s Hours:</strong> ' . esc_html( number_format( $today_hours, 1 ) ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=ict-dashboard' ) ) . '" class="button">Open Full Dashboard</a></p>';
		echo '</div>';
	}

	private function get_default_layout() {
		return array(
			array(
				'i' => 'project_summary',
				'x' => 0,
				'y' => 0,
				'w' => 6,
				'h' => 4,
			),
			array(
				'i' => 'time_tracking',
				'x' => 6,
				'y' => 0,
				'w' => 3,
				'h' => 2,
			),
			array(
				'i' => 'quick_actions',
				'x' => 9,
				'y' => 0,
				'w' => 3,
				'h' => 2,
			),
			array(
				'i' => 'my_tasks',
				'x' => 6,
				'y' => 2,
				'w' => 6,
				'h' => 4,
			),
			array(
				'i' => 'recent_activity',
				'x' => 0,
				'y' => 4,
				'w' => 6,
				'h' => 4,
			),
		);
	}

	private function get_default_widget_settings( $widget_id ) {
		$defaults = array(
			'my_tasks'        => array(
				'show_completed' => false,
				'limit'          => 10,
				'sort_by'        => 'due_date',
			),
			'weather'         => array(
				'location' => '',
				'unit'     => 'fahrenheit',
			),
			'recent_activity' => array(
				'limit'       => 20,
				'show_system' => false,
			),
		);

		return $defaults[ $widget_id ] ?? array();
	}

	// Widget data methods
	private function get_project_summary_data( $params ) {
		global $wpdb;

		$projects = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
             FROM {$wpdb->prefix}ict_projects
             GROUP BY status"
		);

		$by_status = array();
		foreach ( $projects as $p ) {
			$by_status[ $p->status ] = (int) $p->count;
		}

		$recent = $wpdb->get_results(
			"SELECT id, project_number, name, status, progress
             FROM {$wpdb->prefix}ict_projects
             WHERE status = 'in_progress'
             ORDER BY updated_at DESC LIMIT 5"
		);

		return array(
			'by_status' => $by_status,
			'recent'    => $recent,
			'total'     => array_sum( $by_status ),
		);
	}

	private function get_time_tracking_data( $params ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$active = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_time_entries
             WHERE user_id = %d AND end_time IS NULL
             ORDER BY start_time DESC LIMIT 1",
				$user_id
			)
		);

		$today_total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))), 0)
             FROM {$wpdb->prefix}ict_time_entries
             WHERE user_id = %d AND DATE(start_time) = CURDATE()",
				$user_id
			)
		);

		return array(
			'is_clocked_in'   => ! empty( $active ),
			'current_entry'   => $active,
			'today_minutes'   => (int) $today_total,
			'today_formatted' => sprintf( '%d:%02d', floor( $today_total / 60 ), $today_total % 60 ),
		);
	}

	private function get_weekly_hours_data( $params ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );

		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(start_time) as date,
                    SUM(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))) as minutes
             FROM {$wpdb->prefix}ict_time_entries
             WHERE user_id = %d AND start_time >= %s
             GROUP BY DATE(start_time)
             ORDER BY date",
				$user_id,
				$week_start
			)
		);

		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$date          = date( 'Y-m-d', strtotime( $week_start . " +{$i} days" ) );
			$days[ $date ] = 0;
		}

		foreach ( $daily as $d ) {
			$days[ $d->date ] = round( $d->minutes / 60, 1 );
		}

		return array(
			'days'  => $days,
			'total' => array_sum( $days ),
		);
	}

	private function get_my_tasks_data( $params ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$limit   = $params['limit'] ?? 10;

		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title as title, p.post_status as status,
                    pm1.meta_value as due_date, pm2.meta_value as priority
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_ict_due_date'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_ict_priority'
             WHERE p.post_type = 'ict_task' AND p.post_status != 'trash'
             AND p.post_author = %d
             ORDER BY pm1.meta_value ASC LIMIT %d",
				$user_id,
				$limit
			)
		);

		return array( 'tasks' => $tasks );
	}

	private function get_upcoming_deadlines_data( $params ) {
		global $wpdb;
		$days = $params['days'] ?? 14;

		$end_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, end_date, status
             FROM {$wpdb->prefix}ict_projects
             WHERE end_date <= %s AND status = 'in_progress'
             ORDER BY end_date",
				$end_date
			)
		);

		return array( 'items' => $projects );
	}

	private function get_inventory_alerts_data( $params ) {
		global $wpdb;

		$alerts = $wpdb->get_results(
			"SELECT i.*, a.alert_type, a.severity
             FROM {$wpdb->prefix}ict_inventory_alerts a
             JOIN {$wpdb->prefix}ict_inventory_items i ON a.item_id = i.id
             WHERE a.is_acknowledged = 0
             ORDER BY FIELD(a.severity, 'critical', 'warning', 'info')
             LIMIT 10"
		);

		return array( 'alerts' => $alerts );
	}

	private function get_recent_activity_data( $params ) {
		global $wpdb;
		$limit = $params['limit'] ?? 20;

		$activities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.display_name as user_name
             FROM {$wpdb->prefix}ict_activity_log a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             ORDER BY a.created_at DESC LIMIT %d",
				$limit
			)
		);

		return array( 'activities' => $activities );
	}

	private function get_team_availability_data( $params ) {
		global $wpdb;

		$today = date( 'l' );

		$available = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, ua.start_time, ua.end_time
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->prefix}ict_user_availability ua
                 ON u.ID = ua.user_id AND ua.day_of_week = %s
             WHERE ua.is_available = 1
             ORDER BY u.display_name",
				$today
			)
		);

		$clocked_in = $wpdb->get_results(
			"SELECT DISTINCT user_id
             FROM {$wpdb->prefix}ict_time_entries
             WHERE DATE(start_time) = CURDATE() AND end_time IS NULL"
		);

		$clocked_ids = wp_list_pluck( $clocked_in, 'user_id' );

		foreach ( $available as $user ) {
			$user->is_clocked_in = in_array( $user->ID, $clocked_ids, true );
		}

		return array( 'team' => $available );
	}

	private function get_pending_approvals_data( $params ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$time_approvals = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ict_time_approvals
             WHERE approver_id = %d AND status = 'pending'",
				$user_id
			)
		);

		$po_approvals = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ict_po_approvals
             WHERE approver_id = %d AND status = 'pending'",
				$user_id
			)
		);

		return array(
			'time_entries'    => (int) $time_approvals,
			'purchase_orders' => (int) $po_approvals,
			'total'           => (int) $time_approvals + (int) $po_approvals,
		);
	}

	private function get_quick_actions_data( $params ) {
		return array(
			'actions' => array(
				array(
					'id'    => 'clock_in',
					'label' => 'Clock In',
					'icon'  => 'play',
				),
				array(
					'id'    => 'new_task',
					'label' => 'New Task',
					'icon'  => 'plus',
				),
				array(
					'id'    => 'log_time',
					'label' => 'Log Time',
					'icon'  => 'clock',
				),
				array(
					'id'    => 'scan_item',
					'label' => 'Scan Item',
					'icon'  => 'barcode',
				),
			),
		);
	}

	private function get_announcements_data( $params ) {
		global $wpdb;

		$announcements = $wpdb->get_results(
			"SELECT id, title, content, priority, created_at
             FROM {$wpdb->prefix}ict_announcements
             WHERE status = 'published'
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY priority DESC, created_at DESC LIMIT 5"
		);

		return array( 'announcements' => $announcements );
	}

	private function get_messages_data( $params ) {
		global $wpdb;
		$user_id = get_current_user_id();

		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, m.content as last_message, m.created_at as last_message_at
             FROM {$wpdb->prefix}ict_conversations c
             JOIN {$wpdb->prefix}ict_conversation_participants cp ON c.id = cp.conversation_id
             LEFT JOIN {$wpdb->prefix}ict_messages m ON c.last_message_id = m.id
             WHERE cp.user_id = %d
             ORDER BY c.updated_at DESC LIMIT 5",
				$user_id
			)
		);

		return array( 'conversations' => $conversations );
	}

	private function get_kpi_summary_data( $params ) {
		global $wpdb;

		$this_month = date( 'Y-m-01' );
		$last_month = date( 'Y-m-01', strtotime( '-1 month' ) );

		return array(
			'projects_completed' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ict_projects
                 WHERE status = 'completed' AND updated_at >= %s",
					$this_month
				)
			),
			'total_hours'        => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) / 60
                 FROM {$wpdb->prefix}ict_time_entries WHERE start_time >= %s",
					$this_month
				)
			),
			'revenue'            => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(total_amount), 0) FROM {$wpdb->prefix}ict_projects
                 WHERE status = 'completed' AND updated_at >= %s",
					$this_month
				)
			),
		);
	}

	private function get_weather_data( $params ) {
		// Would integrate with weather API
		return array(
			'temperature' => 72,
			'condition'   => 'Partly Cloudy',
			'icon'        => 'cloud-sun',
		);
	}
}
