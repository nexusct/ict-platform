<?php
/**
 * Fired during plugin activation
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Activator
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class ICT_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables, sets default options, and registers capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		// Create database tables
		self::create_tables();

		// Set default options
		self::set_default_options();

		// Create custom roles and capabilities
		self::create_roles_and_capabilities();

		// Schedule cron jobs
		self::schedule_cron_jobs();

		// Create upload directories
		self::create_upload_directories();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation timestamp
		update_option( 'ict_platform_activated', current_time( 'timestamp' ) );
		update_option( 'ict_platform_version', ICT_PLATFORM_VERSION );
	}

	/**
	 * Create custom database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Projects table
		$projects_table = "CREATE TABLE " . ICT_PROJECTS_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zoho_crm_id varchar(100) DEFAULT NULL,
			client_id bigint(20) UNSIGNED DEFAULT NULL,
			project_name varchar(255) NOT NULL,
			project_number varchar(50) DEFAULT NULL,
			site_address text DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			priority varchar(20) DEFAULT 'medium',
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			estimated_hours decimal(10,2) DEFAULT 0.00,
			actual_hours decimal(10,2) DEFAULT 0.00,
			budget_amount decimal(15,2) DEFAULT 0.00,
			actual_cost decimal(15,2) DEFAULT 0.00,
			progress_percentage int(3) DEFAULT 0,
			project_manager_id bigint(20) UNSIGNED DEFAULT NULL,
			notes text DEFAULT NULL,
			sync_status varchar(20) DEFAULT 'pending',
			last_synced datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY zoho_crm_id (zoho_crm_id),
			KEY client_id (client_id),
			KEY status (status),
			KEY project_manager_id (project_manager_id)
		) $charset_collate;";

		// Time entries table
		$time_entries_table = "CREATE TABLE " . ICT_TIME_ENTRIES_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			technician_id bigint(20) UNSIGNED NOT NULL,
			project_id bigint(20) UNSIGNED NOT NULL,
			task_id bigint(20) UNSIGNED DEFAULT NULL,
			clock_in datetime NOT NULL,
			clock_out datetime DEFAULT NULL,
			break_duration int(11) DEFAULT 0,
			total_hours decimal(10,2) DEFAULT 0.00,
			hourly_rate decimal(10,2) DEFAULT 0.00,
			total_cost decimal(10,2) DEFAULT 0.00,
			is_overtime tinyint(1) DEFAULT 0,
			status varchar(20) DEFAULT 'pending',
			approved_by bigint(20) UNSIGNED DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			notes text DEFAULT NULL,
			location_in text DEFAULT NULL,
			location_out text DEFAULT NULL,
			zoho_people_id varchar(100) DEFAULT NULL,
			sync_status varchar(20) DEFAULT 'pending',
			last_synced datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY technician_id (technician_id),
			KEY project_id (project_id),
			KEY task_id (task_id),
			KEY clock_in (clock_in),
			KEY status (status),
			KEY zoho_people_id (zoho_people_id)
		) $charset_collate;";

		// Inventory items table
		$inventory_table = "CREATE TABLE " . ICT_INVENTORY_ITEMS_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zoho_books_item_id varchar(100) DEFAULT NULL,
			sku varchar(100) NOT NULL,
			item_name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			category varchar(100) DEFAULT NULL,
			unit_of_measure varchar(50) DEFAULT 'unit',
			quantity_on_hand decimal(10,2) DEFAULT 0.00,
			quantity_allocated decimal(10,2) DEFAULT 0.00,
			quantity_available decimal(10,2) DEFAULT 0.00,
			reorder_level decimal(10,2) DEFAULT 0.00,
			reorder_quantity decimal(10,2) DEFAULT 0.00,
			unit_cost decimal(10,2) DEFAULT 0.00,
			unit_price decimal(10,2) DEFAULT 0.00,
			supplier_id bigint(20) UNSIGNED DEFAULT NULL,
			location varchar(100) DEFAULT NULL,
			barcode varchar(100) DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			sync_status varchar(20) DEFAULT 'pending',
			last_synced datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY sku (sku),
			KEY zoho_books_item_id (zoho_books_item_id),
			KEY category (category),
			KEY supplier_id (supplier_id),
			KEY barcode (barcode)
		) $charset_collate;";

		// Purchase orders table
		$purchase_orders_table = "CREATE TABLE " . ICT_PURCHASE_ORDERS_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zoho_books_po_id varchar(100) DEFAULT NULL,
			po_number varchar(50) NOT NULL,
			project_id bigint(20) UNSIGNED DEFAULT NULL,
			supplier_id bigint(20) UNSIGNED NOT NULL,
			po_date datetime NOT NULL,
			delivery_date datetime DEFAULT NULL,
			status varchar(50) DEFAULT 'draft',
			subtotal decimal(15,2) DEFAULT 0.00,
			tax_amount decimal(15,2) DEFAULT 0.00,
			shipping_cost decimal(15,2) DEFAULT 0.00,
			total_amount decimal(15,2) DEFAULT 0.00,
			notes text DEFAULT NULL,
			created_by bigint(20) UNSIGNED DEFAULT NULL,
			approved_by bigint(20) UNSIGNED DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			received_date datetime DEFAULT NULL,
			sync_status varchar(20) DEFAULT 'pending',
			last_synced datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY po_number (po_number),
			KEY zoho_books_po_id (zoho_books_po_id),
			KEY project_id (project_id),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) $charset_collate;";

		// Project resources table
		$project_resources_table = "CREATE TABLE " . ICT_PROJECT_RESOURCES_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id bigint(20) UNSIGNED NOT NULL,
			resource_type varchar(50) NOT NULL,
			resource_id bigint(20) UNSIGNED NOT NULL,
			allocation_start datetime NOT NULL,
			allocation_end datetime NOT NULL,
			allocation_percentage int(3) DEFAULT 100,
			estimated_hours decimal(10,2) DEFAULT 0.00,
			actual_hours decimal(10,2) DEFAULT 0.00,
			status varchar(20) DEFAULT 'scheduled',
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY resource_type (resource_type),
			KEY resource_id (resource_id),
			KEY allocation_dates (allocation_start, allocation_end)
		) $charset_collate;";

		// Sync queue table
		$sync_queue_table = "CREATE TABLE " . ICT_SYNC_QUEUE_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) UNSIGNED NOT NULL,
			action varchar(20) NOT NULL,
			zoho_service varchar(50) NOT NULL,
			priority int(3) DEFAULT 5,
			status varchar(20) DEFAULT 'pending',
			attempts int(3) DEFAULT 0,
			max_attempts int(3) DEFAULT 3,
			last_error text DEFAULT NULL,
			payload longtext DEFAULT NULL,
			scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY status (status),
			KEY zoho_service (zoho_service),
			KEY scheduled_at (scheduled_at)
		) $charset_collate;";

		// Sync log table
		$sync_log_table = "CREATE TABLE " . ICT_SYNC_LOG_TABLE . " (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) UNSIGNED DEFAULT NULL,
			direction varchar(20) NOT NULL,
			zoho_service varchar(50) NOT NULL,
			action varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			request_data longtext DEFAULT NULL,
			response_data longtext DEFAULT NULL,
			error_message text DEFAULT NULL,
			duration_ms int(11) DEFAULT 0,
			synced_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY zoho_service (zoho_service),
			KEY status (status),
			KEY synced_at (synced_at)
		) $charset_collate;";

		// Execute table creation
		dbDelta( $projects_table );
		dbDelta( $time_entries_table );
		dbDelta( $inventory_table );
		dbDelta( $purchase_orders_table );
		dbDelta( $project_resources_table );
		dbDelta( $sync_queue_table );
		dbDelta( $sync_log_table );

		// Save database version
		update_option( 'ict_platform_db_version', '1.0.0' );

		// Create additional tables for new features
		self::create_additional_tables();
	}

	/**
	 * Create additional database tables for new features.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function create_additional_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Custom fields definition table
		$custom_fields_table = "CREATE TABLE {$wpdb->prefix}ict_custom_fields (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			field_key varchar(100) NOT NULL,
			field_name varchar(255) NOT NULL,
			field_label varchar(255) NOT NULL,
			field_type varchar(50) NOT NULL,
			description text DEFAULT NULL,
			placeholder varchar(255) DEFAULT NULL,
			default_value text DEFAULT NULL,
			options longtext DEFAULT NULL,
			settings longtext DEFAULT NULL,
			validation_rules longtext DEFAULT NULL,
			field_group varchar(100) DEFAULT 'default',
			sort_order int(11) DEFAULT 0,
			is_required tinyint(1) DEFAULT 0,
			is_active tinyint(1) DEFAULT 1,
			show_in_list tinyint(1) DEFAULT 0,
			show_in_form tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY entity_field (entity_type, field_key),
			KEY entity_type (entity_type),
			KEY field_group (field_group)
		) $charset_collate;";

		// Custom field values table
		$custom_field_values_table = "CREATE TABLE {$wpdb->prefix}ict_custom_field_values (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) UNSIGNED NOT NULL,
			field_id bigint(20) UNSIGNED NOT NULL,
			field_value longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY entity_field (entity_type, entity_id, field_id),
			KEY entity (entity_type, entity_id),
			KEY field_id (field_id)
		) $charset_collate;";

		// Notifications log table
		$notifications_log_table = "CREATE TABLE {$wpdb->prefix}ict_notifications_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			notification_type varchar(100) NOT NULL,
			recipients longtext DEFAULT NULL,
			data longtext DEFAULT NULL,
			results longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY notification_type (notification_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// SMS log table
		$sms_log_table = "CREATE TABLE {$wpdb->prefix}ict_sms_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient varchar(50) NOT NULL,
			message text NOT NULL,
			status varchar(20) NOT NULL,
			error text DEFAULT NULL,
			twilio_sid varchar(100) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Email queue table
		$email_queue_table = "CREATE TABLE {$wpdb->prefix}ict_email_queue (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			notification_type varchar(100) NOT NULL,
			recipients longtext NOT NULL,
			data longtext NOT NULL,
			status varchar(20) DEFAULT 'pending',
			result longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Sync conflicts table
		$sync_conflicts_table = "CREATE TABLE {$wpdb->prefix}ict_sync_conflicts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) UNSIGNED NOT NULL,
			client_data longtext NOT NULL,
			client_time datetime NOT NULL,
			server_data longtext NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) DEFAULT 'pending',
			resolution varchar(20) DEFAULT NULL,
			resolved_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY entity (entity_type, entity_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";

		// Offline queue table
		$offline_queue_table = "CREATE TABLE {$wpdb->prefix}ict_offline_queue (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) UNSIGNED DEFAULT NULL,
			action varchar(20) NOT NULL,
			client_id varchar(100) DEFAULT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			data longtext NOT NULL,
			status varchar(20) DEFAULT 'pending',
			result longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY entity (entity_type, entity_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";

		// Tasks table
		$tasks_table = "CREATE TABLE {$wpdb->prefix}ict_tasks (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id bigint(20) UNSIGNED NOT NULL,
			parent_id bigint(20) UNSIGNED DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			priority varchar(20) DEFAULT 'medium',
			assigned_to bigint(20) UNSIGNED DEFAULT NULL,
			due_date datetime DEFAULT NULL,
			estimated_hours decimal(10,2) DEFAULT 0.00,
			actual_hours decimal(10,2) DEFAULT 0.00,
			completed_at datetime DEFAULT NULL,
			completed_by bigint(20) UNSIGNED DEFAULT NULL,
			sort_order int(11) DEFAULT 0,
			created_by bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY parent_id (parent_id),
			KEY assigned_to (assigned_to),
			KEY status (status),
			KEY due_date (due_date)
		) $charset_collate;";

		// Expenses table
		$expenses_table = "CREATE TABLE {$wpdb->prefix}ict_expenses (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id bigint(20) UNSIGNED DEFAULT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			expense_date date NOT NULL,
			category varchar(100) NOT NULL,
			description text DEFAULT NULL,
			amount decimal(15,2) NOT NULL,
			receipt_url varchar(500) DEFAULT NULL,
			status varchar(20) DEFAULT 'pending',
			approved_by bigint(20) UNSIGNED DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			zoho_expense_id varchar(100) DEFAULT NULL,
			sync_status varchar(20) DEFAULT 'pending',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY expense_date (expense_date)
		) $charset_collate;";

		// Execute table creation
		dbDelta( $custom_fields_table );
		dbDelta( $custom_field_values_table );
		dbDelta( $notifications_log_table );
		dbDelta( $sms_log_table );
		dbDelta( $email_queue_table );
		dbDelta( $sync_conflicts_table );
		dbDelta( $offline_queue_table );
		dbDelta( $tasks_table );
		dbDelta( $expenses_table );

		// Update database version
		update_option( 'ict_platform_db_version', '1.1.0' );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			// Core settings
			'ict_sync_interval'          => 15, // minutes
			'ict_sync_rate_limit'        => 60, // requests per minute
			'ict_time_rounding'          => 15, // minutes
			'ict_overtime_threshold'     => 8,  // hours per day
			'ict_low_stock_threshold'    => 10, // units
			'ict_default_tax_rate'       => 0,  // percentage
			'ict_currency'               => 'USD',
			'ict_date_format'            => 'Y-m-d',
			'ict_time_format'            => 'H:i:s',
			'ict_working_hours_per_day'  => 8,

			// Feature flags
			'ict_enable_offline_mode'    => true,
			'ict_enable_gps_tracking'    => true,
			'ict_enable_notifications'   => true,
			'ict_enable_biometric_auth'  => true,
			'ict_notification_types'     => array( 'low_stock', 'overdue_tasks', 'time_approval' ),

			// Email notification settings
			'ict_enable_email_notifications' => true,
			'ict_email_from_name'        => get_bloginfo( 'name' ),
			'ict_email_from_address'     => get_option( 'admin_email' ),
			'ict_email_primary_color'    => '#0073aa',

			// SMS notification settings
			'ict_enable_sms_notifications' => false,

			// Microsoft Teams settings
			'ict_enable_teams_notifications' => false,

			// Push notification settings
			'ict_enable_push_notifications' => true,
		);

		foreach ( $defaults as $key => $value ) {
			add_option( $key, $value );
		}
	}

	/**
	 * Create custom roles and capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_roles_and_capabilities() {
		// Get administrator role
		$admin = get_role( 'administrator' );

		// Add all capabilities to administrator
		$capabilities = array(
			// Platform management
			'manage_ict_platform',
			'manage_ict_integrations',
			'manage_ict_sync',

			// Projects
			'manage_ict_projects',
			'edit_ict_projects',
			'delete_ict_projects',
			'view_ict_projects',
			'create_ict_projects',
			'assign_ict_projects',

			// Time tracking
			'manage_ict_time_entries',
			'edit_ict_time_entry',
			'approve_ict_time_entries',
			'view_all_time_entries',

			// Inventory
			'manage_ict_inventory',
			'edit_ict_inventory',
			'view_ict_inventory',

			// Purchase orders
			'manage_ict_purchase_orders',
			'create_ict_purchase_orders',
			'approve_ict_purchase_orders',
			'view_ict_purchase_orders',

			// Resources
			'manage_ict_resources',
			'view_ict_resources',

			// Reports
			'view_ict_reports',
			'export_ict_reports',
			'manage_ict_reports',

			// Tasks
			'manage_ict_tasks',
			'view_ict_tasks',
			'edit_ict_tasks',

			// Users
			'manage_ict_users',
			'view_ict_users',

			// Custom fields
			'manage_custom_fields',

			// Notifications
			'manage_notifications',
		);

		foreach ( $capabilities as $cap ) {
			$admin->add_cap( $cap );
		}

		// Create Project Manager role
		add_role(
			'ict_project_manager',
			__( 'ICT Project Manager', 'ict-platform' ),
			array(
				'read'                       => true,
				'manage_ict_projects'        => true,
				'edit_ict_projects'          => true,
				'manage_ict_time_entries'    => true,
				'approve_ict_time_entries'   => true,
				'manage_ict_purchase_orders' => true,
				'view_ict_reports'           => true,
			)
		);

		// Create Technician role
		add_role(
			'ict_technician',
			__( 'ICT Technician', 'ict-platform' ),
			array(
				'read'                 => true,
				'edit_ict_time_entry'  => true,
				'view_ict_projects'    => true,
				'view_ict_tasks'       => true,
			)
		);

		// Create Inventory Manager role
		add_role(
			'ict_inventory_manager',
			__( 'ICT Inventory Manager', 'ict-platform' ),
			array(
				'read'                       => true,
				'manage_ict_inventory'       => true,
				'manage_ict_purchase_orders' => true,
				'view_ict_reports'           => true,
			)
		);
	}

	/**
	 * Schedule cron jobs for sync and maintenance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function schedule_cron_jobs() {
		// Schedule sync job every 15 minutes
		if ( ! wp_next_scheduled( 'ict_platform_sync_job' ) ) {
			wp_schedule_event( time(), 'ict_fifteen_minutes', 'ict_platform_sync_job' );
		}

		// Schedule cleanup job daily
		if ( ! wp_next_scheduled( 'ict_platform_cleanup_job' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_platform_cleanup_job' );
		}

		// Schedule low stock check hourly
		if ( ! wp_next_scheduled( 'ict_platform_stock_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'ict_platform_stock_check' );
		}
	}

	/**
	 * Create upload directories for documents and attachments.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_upload_directories() {
		$upload_dir = wp_upload_dir();
		$ict_dir    = $upload_dir['basedir'] . '/ict-platform';

		$directories = array(
			$ict_dir,
			$ict_dir . '/projects',
			$ict_dir . '/documents',
			$ict_dir . '/invoices',
			$ict_dir . '/receipts',
			$ict_dir . '/photos',
		);

		foreach ( $directories as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );

				// Create .htaccess for security
				$htaccess_content = "Options -Indexes\n<Files *.php>\ndeny from all\n</Files>";
				file_put_contents( $dir . '/.htaccess', $htaccess_content );

				// Create index.php for security
				file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
			}
		}
	}
}
