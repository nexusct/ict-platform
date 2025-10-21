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
