<?php
/**
 * Fired during plugin deactivation
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Deactivator
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class ICT_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clears scheduled cron jobs and flushes rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate() {
		// Clear scheduled cron jobs
		self::clear_scheduled_hooks();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set deactivation timestamp
		update_option( 'ict_platform_deactivated', current_time( 'timestamp' ) );
	}

	/**
	 * Clear all scheduled cron hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function clear_scheduled_hooks() {
		$hooks = array(
			'ict_platform_sync_job',
			'ict_platform_cleanup_job',
			'ict_platform_stock_check',
		);

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			wp_clear_scheduled_hook( $hook );
		}
	}
}
