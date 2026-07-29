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
			// Core sync jobs (registered in class-ict-activator.php)
			'ict_platform_sync_job',
			'ict_platform_cleanup_job',
			'ict_platform_stock_check',
			// Email / reporting jobs (registered in class-ict-core.php)
			'ict_process_email_queue',
			'ict_send_email_digest',
			'ict_process_scheduled_reports',
			'ict_cleanup_old_reports',
			// Feature module jobs
			'ict_collect_metrics',
			'ict_cleanup_2fa_data',
			'ict_process_scheduled_push',
			'ict_cleanup_push_data',
			'ict_calculate_kpis',
			'ict_process_recurring_invoices',
			'ict_send_payment_reminders',
			'ict_run_scheduled_reports',
			'ict_cleanup_rate_limit_data',
			'ict_scheduled_backup',
			'ict_check_certification_expirations',
			'ict_cleanup_old_notifications',
			'ict_check_quote_expirations',
			'ict_update_exchange_rates',
			'ict_sync_calendars',
			'ict_process_recurring_tasks',
			'ict_check_sla_compliance',
			'ict_check_maintenance_schedules',
			'ict_check_warranty_expirations',
			// Dynamic per-schedule report jobs (registered in class-ict-advanced-reporting.php)
			'ict_generate_scheduled_report',
		);

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
