<?php
/**
 * Fired during plugin uninstallation
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Uninstaller
 *
 * This class defines all code necessary to run during the plugin's uninstallation.
 * Note: Only runs if user explicitly wants to delete all data.
 */
class ICT_Uninstaller {

	/**
	 * Uninstall the plugin.
	 *
	 * Removes database tables, options, user roles, and uploaded files
	 * if the 'ict_delete_data_on_uninstall' option is set to true.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function uninstall() {
		// Check if user wants to delete data on uninstall
		$delete_data = get_option( 'ict_delete_data_on_uninstall', false );

		if ( ! $delete_data ) {
			return;
		}

		// Drop custom tables
		self::drop_tables();

		// Delete all plugin options
		self::delete_options();

		// Remove custom roles and capabilities
		self::remove_roles_and_capabilities();

		// Delete uploaded files
		self::delete_uploaded_files();

		// Clear any remaining scheduled hooks
		self::clear_all_hooks();
	}

	/**
	 * Drop all custom database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function drop_tables() {
		global $wpdb;

		$tables = array(
			ICT_PROJECTS_TABLE,
			ICT_TIME_ENTRIES_TABLE,
			ICT_INVENTORY_ITEMS_TABLE,
			ICT_PURCHASE_ORDERS_TABLE,
			ICT_PROJECT_RESOURCES_TABLE,
			ICT_SYNC_QUEUE_TABLE,
			ICT_SYNC_LOG_TABLE,
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Delete all plugin options from the database.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function delete_options() {
		global $wpdb;

		// Delete all options starting with 'ict_'
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ict_%'" );

		// Clear object cache
		wp_cache_flush();
	}

	/**
	 * Remove custom roles and capabilities.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function remove_roles_and_capabilities() {
		// Remove custom roles
		remove_role( 'ict_project_manager' );
		remove_role( 'ict_technician' );
		remove_role( 'ict_inventory_manager' );

		// Remove capabilities from administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$capabilities = array(
				'manage_ict_platform',
				'manage_ict_projects',
				'edit_ict_projects',
				'delete_ict_projects',
				'manage_ict_time_entries',
				'approve_ict_time_entries',
				'manage_ict_inventory',
				'manage_ict_purchase_orders',
				'approve_ict_purchase_orders',
				'view_ict_reports',
				'manage_ict_sync',
			);

			foreach ( $capabilities as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}

	/**
	 * Delete uploaded files and directories.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function delete_uploaded_files() {
		$upload_dir = wp_upload_dir();
		$ict_dir    = $upload_dir['basedir'] . '/ict-platform';

		if ( file_exists( $ict_dir ) ) {
			self::recursive_delete( $ict_dir );
		}
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * @since 1.0.0
	 * @param string $dir Directory path.
	 * @return bool
	 */
	private static function recursive_delete( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			is_dir( $path ) ? self::recursive_delete( $path ) : unlink( $path );
		}

		return rmdir( $dir );
	}

	/**
	 * Clear all scheduled hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_all_hooks() {
		wp_clear_scheduled_hook( 'ict_platform_sync_job' );
		wp_clear_scheduled_hook( 'ict_platform_cleanup_job' );
		wp_clear_scheduled_hook( 'ict_platform_stock_check' );
	}
}
