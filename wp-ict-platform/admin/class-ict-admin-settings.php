<?php
/**
 * Admin settings functionality
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Admin_Settings
 *
 * Handles plugin settings registration and sanitization.
 */
class ICT_Admin_Settings {

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		// General settings
		register_setting(
			'ict_general_settings',
			'ict_currency',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'USD',
			)
		);

		register_setting(
			'ict_general_settings',
			'ict_date_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'Y-m-d',
			)
		);

		register_setting(
			'ict_general_settings',
			'ict_time_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'H:i:s',
			)
		);

		// Zoho CRM settings
		register_setting(
			'ict_zoho_crm_settings',
			'ict_zoho_crm_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_zoho_crm_settings',
			'ict_zoho_crm_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		register_setting(
			'ict_zoho_crm_settings',
			'ict_zoho_crm_access_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		register_setting(
			'ict_zoho_crm_settings',
			'ict_zoho_crm_refresh_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		// Zoho FSM settings
		register_setting(
			'ict_zoho_fsm_settings',
			'ict_zoho_fsm_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_zoho_fsm_settings',
			'ict_zoho_fsm_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		// Zoho Books settings
		register_setting(
			'ict_zoho_books_settings',
			'ict_zoho_books_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_zoho_books_settings',
			'ict_zoho_books_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		// Zoho People settings
		register_setting(
			'ict_zoho_people_settings',
			'ict_zoho_people_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_zoho_people_settings',
			'ict_zoho_people_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		// Zoho Desk settings
		register_setting(
			'ict_zoho_desk_settings',
			'ict_zoho_desk_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_zoho_desk_settings',
			'ict_zoho_desk_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		// Sync settings
		register_setting(
			'ict_sync_settings',
			'ict_sync_interval',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 15,
			)
		);

		register_setting(
			'ict_sync_settings',
			'ict_sync_rate_limit',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 60,
			)
		);

		// Time tracking settings
		register_setting(
			'ict_time_settings',
			'ict_time_rounding',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 15,
			)
		);

		register_setting(
			'ict_time_settings',
			'ict_overtime_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 8,
			)
		);

		register_setting(
			'ict_time_settings',
			'ict_enable_gps_tracking',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Inventory settings
		register_setting(
			'ict_inventory_settings',
			'ict_low_stock_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			)
		);

		// Feature flags
		register_setting(
			'ict_feature_settings',
			'ict_enable_offline_mode',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'ict_feature_settings',
			'ict_enable_notifications',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Data retention
		register_setting(
			'ict_data_settings',
			'ict_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		// Microsoft Teams integration settings
		$this->register_teams_settings();

		// Twilio SMS settings
		$this->register_twilio_settings();

		// Email notification settings
		$this->register_email_settings();

		// Push notification settings
		$this->register_push_settings();

		// Biometric authentication settings
		$this->register_biometric_settings();

		// Advanced reporting settings
		$this->register_reporting_settings();

		// Offline mode settings
		$this->register_offline_settings();

		// Custom fields settings
		$this->register_custom_fields_settings();

		// Advanced role management settings
		$this->register_role_settings();
	}

	/**
	 * Register Microsoft Teams integration settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_teams_settings() {
		register_setting(
			'ict_teams_settings',
			'ict_teams_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_client_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_client_secret',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_tenant_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_default_channel_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_notify_project_updates',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'ict_teams_settings',
			'ict_teams_notify_low_stock',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
	}

	/**
	 * Register Twilio SMS settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_twilio_settings() {
		register_setting(
			'ict_twilio_settings',
			'ict_twilio_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ict_twilio_settings',
			'ict_twilio_account_sid',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_twilio_settings',
			'ict_twilio_auth_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);

		register_setting(
			'ict_twilio_settings',
			'ict_twilio_from_number',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_twilio_settings',
			'ict_twilio_messaging_service_sid',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Register email notification settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_email_settings() {
		register_setting(
			'ict_email_settings',
			'ict_email_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'ict_email_settings',
			'ict_email_from_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			'ict_email_settings',
			'ict_email_from_address',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => get_option( 'admin_email' ),
			)
		);

		register_setting(
			'ict_email_settings',
			'ict_email_digest_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ict_email_settings',
			'ict_email_digest_frequency',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'daily',
			)
		);
	}

	/**
	 * Register push notification settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_push_settings() {
		register_setting(
			'ict_push_settings',
			'ict_push_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ict_push_settings',
			'ict_push_vapid_public_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'ict_push_settings',
			'ict_push_vapid_private_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_encrypted' ),
			)
		);
	}

	/**
	 * Register biometric authentication settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_biometric_settings() {
		register_setting(
			'ict_biometric_settings',
			'ict_biometric_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ict_biometric_settings',
			'ict_biometric_relying_party_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);

		register_setting(
			'ict_biometric_settings',
			'ict_biometric_relying_party_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			'ict_biometric_settings',
			'ict_biometric_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 60000,
			)
		);

		register_setting(
			'ict_biometric_settings',
			'ict_biometric_require_resident_key',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Register advanced reporting settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_reporting_settings() {
		register_setting(
			'ict_reporting_settings',
			'ict_reporting_default_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'pdf',
			)
		);

		register_setting(
			'ict_reporting_settings',
			'ict_reporting_company_logo',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);

		register_setting(
			'ict_reporting_settings',
			'ict_reporting_company_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			'ict_reporting_settings',
			'ict_reporting_auto_schedule_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'ict_reporting_settings',
			'ict_reporting_retention_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 90,
			)
		);
	}

	/**
	 * Register offline mode settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_offline_settings() {
		register_setting(
			'ict_offline_settings',
			'ict_offline_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'ict_offline_settings',
			'ict_offline_max_queue_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 100,
			)
		);

		register_setting(
			'ict_offline_settings',
			'ict_offline_conflict_resolution',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'server_wins',
			)
		);

		register_setting(
			'ict_offline_settings',
			'ict_offline_sync_interval',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
			)
		);
	}

	/**
	 * Register custom fields settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_custom_fields_settings() {
		register_setting(
			'ict_custom_fields_settings',
			'ict_custom_fields_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'ict_custom_fields_settings',
			'ict_custom_fields_max_per_entity',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 50,
			)
		);
	}

	/**
	 * Register advanced role management settings.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function register_role_settings() {
		register_setting(
			'ict_role_settings',
			'ict_role_management_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'ict_role_settings',
			'ict_role_dynamic_permissions',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Sanitize and encrypt sensitive data.
	 *
	 * @since  1.0.0
	 * @param  string $value Value to sanitize and encrypt.
	 * @return string Encrypted value.
	 */
	public function sanitize_encrypted( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( $value );

		// Simple encryption (in production, use proper encryption)
		if ( function_exists( 'openssl_encrypt' ) ) {
			$key    = wp_salt( 'auth' );
			$iv     = substr( wp_salt( 'secure_auth' ), 0, 16 );
			$value  = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
		}

		return $value;
	}

	/**
	 * Decrypt sensitive data.
	 *
	 * @since  1.0.0
	 * @param  string $value Encrypted value.
	 * @return string Decrypted value.
	 */
	public static function decrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( function_exists( 'openssl_decrypt' ) ) {
			$key   = wp_salt( 'auth' );
			$iv    = substr( wp_salt( 'secure_auth' ), 0, 16 );
			$value = openssl_decrypt( $value, 'AES-256-CBC', $key, 0, $iv );
		}

		return $value;
	}
}
