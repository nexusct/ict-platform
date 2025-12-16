<?php
/**
 * Two-Factor Authentication
 *
 * Enhanced security with two-factor authentication including:
 * - TOTP (Time-based One-Time Password) support
 * - Email-based verification codes
 * - SMS verification (via external providers)
 * - Backup codes for recovery
 * - Remember trusted devices
 * - Per-role 2FA requirements
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Two_Factor_Auth
 */
class ICT_Two_Factor_Auth {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Two_Factor_Auth
	 */
	private static $instance = null;

	/**
	 * Table names.
	 *
	 * @var array
	 */
	private $tables = array();

	/**
	 * TOTP settings.
	 *
	 * @var array
	 */
	private $totp_settings = array(
		'digits'    => 6,
		'period'    => 30,
		'algorithm' => 'sha1',
	);

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Two_Factor_Auth
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

		$this->tables = array(
			'user_2fa'        => $wpdb->prefix . 'ict_user_2fa',
			'backup_codes'    => $wpdb->prefix . 'ict_2fa_backup_codes',
			'trusted_devices' => $wpdb->prefix . 'ict_trusted_devices',
			'verification'    => $wpdb->prefix . 'ict_2fa_verification',
		);

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		register_activation_hook( ICT_PLUGIN_FILE, array( $this, 'maybe_create_tables' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_create_tables' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Login hooks.
		add_filter( 'authenticate', array( $this, 'check_2fa_required' ), 100, 3 );
		add_action( 'wp_login', array( $this, 'handle_login' ), 10, 2 );

		// Cleanup.
		add_action( 'ict_cleanup_2fa_data', array( $this, 'cleanup_expired_data' ) );
		if ( ! wp_next_scheduled( 'ict_cleanup_2fa_data' ) ) {
			wp_schedule_event( time(), 'daily', 'ict_cleanup_2fa_data' );
		}
	}

	/**
	 * Create database tables.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// User 2FA settings.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['user_2fa']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            method enum('totp','email','sms') DEFAULT 'totp',
            secret_key varchar(100) DEFAULT NULL,
            phone_number varchar(50) DEFAULT NULL,
            is_enabled tinyint(1) DEFAULT 0,
            is_confirmed tinyint(1) DEFAULT 0,
            enabled_at datetime DEFAULT NULL,
            last_used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

		// Backup codes.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['backup_codes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            code_hash varchar(255) NOT NULL,
            is_used tinyint(1) DEFAULT 0,
            used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

		// Trusted devices.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['trusted_devices']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            device_token varchar(255) NOT NULL,
            device_name varchar(200) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            os varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            trusted_until datetime NOT NULL,
            last_used_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY device_token (device_token),
            KEY user_id (user_id),
            KEY trusted_until (trusted_until)
        ) $charset_collate;";

		// Verification codes (for email/SMS).
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['verification']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            code_hash varchar(255) NOT NULL,
            method enum('email','sms') DEFAULT 'email',
            expires_at datetime NOT NULL,
            attempts int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$namespace = 'ict/v1';

		// Setup 2FA.
		register_rest_route(
			$namespace,
			'/2fa/setup',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_setup' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_setup_2fa' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
			)
		);

		// Verify setup.
		register_rest_route(
			$namespace,
			'/2fa/verify-setup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_verify_setup' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Disable 2FA.
		register_rest_route(
			$namespace,
			'/2fa/disable',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_disable_2fa' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Verify code (during login).
		register_rest_route(
			$namespace,
			'/2fa/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_verify_code' ),
				'permission_callback' => '__return_true',
			)
		);

		// Send verification code (email/SMS).
		register_rest_route(
			$namespace,
			'/2fa/send-code',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_send_code' ),
				'permission_callback' => '__return_true',
			)
		);

		// Backup codes.
		register_rest_route(
			$namespace,
			'/2fa/backup-codes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_backup_codes' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_generate_backup_codes' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
			)
		);

		// Trusted devices.
		register_rest_route(
			$namespace,
			'/2fa/trusted-devices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_trusted_devices' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/2fa/trusted-devices/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'rest_remove_trusted_device' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Status.
		register_rest_route(
			$namespace,
			'/2fa/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_status' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Admin settings.
		register_rest_route(
			$namespace,
			'/2fa/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);
	}

	/**
	 * Check user permission.
	 */
	public function check_user_permission() {
		return is_user_logged_in();
	}

	/**
	 * Check admin permission.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Generate secret key.
	 *
	 * @return string
	 */
	private function generate_secret() {
		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret = '';
		for ( $i = 0; $i < 16; $i++ ) {
			$secret .= $chars[ wp_rand( 0, 31 ) ];
		}
		return $secret;
	}

	/**
	 * Generate TOTP code.
	 *
	 * @param string $secret Secret key.
	 * @param int    $time   Timestamp (optional).
	 * @return string
	 */
	private function generate_totp( $secret, $time = null ) {
		if ( $time === null ) {
			$time = time();
		}

		$counter        = floor( $time / $this->totp_settings['period'] );
		$binary_counter = pack( 'N*', 0 ) . pack( 'N*', $counter );

		// Decode base32 secret.
		$secret_binary = $this->base32_decode( $secret );

		// Generate HMAC.
		$hash = hash_hmac( $this->totp_settings['algorithm'], $binary_counter, $secret_binary, true );

		// Dynamic truncation.
		$offset = ord( $hash[ strlen( $hash ) - 1 ] ) & 0xf;
		$code   = (
			( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xff )
		) % pow( 10, $this->totp_settings['digits'] );

		return str_pad( $code, $this->totp_settings['digits'], '0', STR_PAD_LEFT );
	}

	/**
	 * Verify TOTP code.
	 *
	 * @param string $secret Secret key.
	 * @param string $code   Code to verify.
	 * @param int    $window Time window (periods).
	 * @return bool
	 */
	private function verify_totp( $secret, $code, $window = 1 ) {
		$time = time();

		for ( $i = -$window; $i <= $window; $i++ ) {
			$check_time = $time + ( $i * $this->totp_settings['period'] );
			if ( hash_equals( $this->generate_totp( $secret, $check_time ), $code ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Base32 decode.
	 *
	 * @param string $input Base32 encoded string.
	 * @return string
	 */
	private function base32_decode( $input ) {
		$map    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$input  = strtoupper( $input );
		$output = '';
		$v      = 0;
		$vbits  = 0;

		for ( $i = 0, $len = strlen( $input ); $i < $len; $i++ ) {
			$v    <<= 5;
			$v     += strpos( $map, $input[ $i ] );
			$vbits += 5;

			if ( $vbits >= 8 ) {
				$vbits  -= 8;
				$output .= chr( ( $v >> $vbits ) & 0xff );
			}
		}

		return $output;
	}

	/**
	 * Get provisioning URI for TOTP.
	 *
	 * @param int    $user_id User ID.
	 * @param string $secret  Secret key.
	 * @return string
	 */
	private function get_provisioning_uri( $user_id, $secret ) {
		$user   = get_user_by( 'ID', $user_id );
		$issuer = get_bloginfo( 'name' );
		$label  = $user->user_email;

		return sprintf(
			'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
			rawurlencode( $issuer ),
			rawurlencode( $label ),
			$secret,
			rawurlencode( $issuer ),
			strtoupper( $this->totp_settings['algorithm'] ),
			$this->totp_settings['digits'],
			$this->totp_settings['period']
		);
	}

	/**
	 * Get 2FA setup.
	 */
	public function rest_get_setup( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d",
				$user_id
			)
		);

		$methods = array(
			array(
				'id'          => 'totp',
				'name'        => 'Authenticator App',
				'description' => 'Use Google Authenticator or similar app',
			),
			array(
				'id'          => 'email',
				'name'        => 'Email',
				'description' => 'Receive codes via email',
			),
		);

		// Add SMS if configured.
		if ( get_option( 'ict_2fa_sms_enabled' ) ) {
			$methods[] = array(
				'id'          => 'sms',
				'name'        => 'SMS',
				'description' => 'Receive codes via text message',
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'is_enabled' => $existing && $existing->is_enabled,
				'method'     => $existing ? $existing->method : null,
				'methods'    => $methods,
			)
		);
	}

	/**
	 * Setup 2FA.
	 */
	public function rest_setup_2fa( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$method  = sanitize_text_field( $request->get_param( 'method' ) ) ?: 'totp';

		// Check if already setup.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d",
				$user_id
			)
		);

		$response = array( 'success' => true );

		if ( $method === 'totp' ) {
			$secret           = $this->generate_secret();
			$provisioning_uri = $this->get_provisioning_uri( $user_id, $secret );

			if ( $existing ) {
				$wpdb->update(
					$this->tables['user_2fa'],
					array(
						'method'       => 'totp',
						'secret_key'   => $secret,
						'is_confirmed' => 0,
					),
					array( 'user_id' => $user_id )
				);
			} else {
				$wpdb->insert(
					$this->tables['user_2fa'],
					array(
						'user_id'    => $user_id,
						'method'     => 'totp',
						'secret_key' => $secret,
					)
				);
			}

			$response['secret']           = $secret;
			$response['provisioning_uri'] = $provisioning_uri;
			$response['qr_code_url']      = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode( $provisioning_uri );

		} elseif ( $method === 'email' ) {
			if ( $existing ) {
				$wpdb->update(
					$this->tables['user_2fa'],
					array(
						'method'       => 'email',
						'is_confirmed' => 1,
					),
					array( 'user_id' => $user_id )
				);
			} else {
				$wpdb->insert(
					$this->tables['user_2fa'],
					array(
						'user_id'      => $user_id,
						'method'       => 'email',
						'is_confirmed' => 1,
					)
				);
			}

			$response['message'] = 'Email 2FA configured. You will receive codes at your registered email.';

		} elseif ( $method === 'sms' ) {
			$phone = sanitize_text_field( $request->get_param( 'phone_number' ) );

			if ( ! $phone ) {
				return new WP_Error( 'no_phone', 'Phone number is required', array( 'status' => 400 ) );
			}

			if ( $existing ) {
				$wpdb->update(
					$this->tables['user_2fa'],
					array(
						'method'       => 'sms',
						'phone_number' => $phone,
						'is_confirmed' => 0,
					),
					array( 'user_id' => $user_id )
				);
			} else {
				$wpdb->insert(
					$this->tables['user_2fa'],
					array(
						'user_id'      => $user_id,
						'method'       => 'sms',
						'phone_number' => $phone,
					)
				);
			}

			// Send verification code.
			$this->send_verification_code( $user_id, 'sms' );

			$response['message'] = 'Verification code sent to your phone';
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Verify setup.
	 */
	public function rest_verify_setup( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$code    = sanitize_text_field( $request->get_param( 'code' ) );

		$user_2fa = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! $user_2fa ) {
			return new WP_Error( 'not_setup', '2FA not setup', array( 'status' => 400 ) );
		}

		$verified = false;

		if ( $user_2fa->method === 'totp' ) {
			$verified = $this->verify_totp( $user_2fa->secret_key, $code );
		} else {
			$verified = $this->verify_email_sms_code( $user_id, $code );
		}

		if ( ! $verified ) {
			return new WP_Error( 'invalid_code', 'Invalid verification code', array( 'status' => 400 ) );
		}

		// Enable 2FA.
		$wpdb->update(
			$this->tables['user_2fa'],
			array(
				'is_enabled'   => 1,
				'is_confirmed' => 1,
				'enabled_at'   => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id )
		);

		// Generate backup codes.
		$backup_codes = $this->generate_backup_codes_internal( $user_id );

		return rest_ensure_response(
			array(
				'success'      => true,
				'message'      => '2FA enabled successfully',
				'backup_codes' => $backup_codes,
			)
		);
	}

	/**
	 * Disable 2FA.
	 */
	public function rest_disable_2fa( $request ) {
		global $wpdb;

		$user_id  = get_current_user_id();
		$password = $request->get_param( 'password' );

		// Verify password.
		$user = get_user_by( 'ID', $user_id );
		if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
			return new WP_Error( 'invalid_password', 'Invalid password', array( 'status' => 401 ) );
		}

		// Disable 2FA.
		$wpdb->update(
			$this->tables['user_2fa'],
			array( 'is_enabled' => 0 ),
			array( 'user_id' => $user_id )
		);

		// Delete backup codes.
		$wpdb->delete( $this->tables['backup_codes'], array( 'user_id' => $user_id ) );

		// Remove trusted devices.
		$wpdb->delete( $this->tables['trusted_devices'], array( 'user_id' => $user_id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => '2FA disabled successfully',
			)
		);
	}

	/**
	 * Verify code during login.
	 */
	public function rest_verify_code( $request ) {
		global $wpdb;

		$user_id      = intval( $request->get_param( 'user_id' ) );
		$code         = sanitize_text_field( $request->get_param( 'code' ) );
		$trust_device = $request->get_param( 'trust_device' );
		$nonce        = $request->get_param( 'nonce' );

		// Verify nonce.
		$stored_nonce = get_transient( 'ict_2fa_nonce_' . $user_id );
		if ( ! $stored_nonce || $nonce !== $stored_nonce ) {
			return new WP_Error( 'invalid_session', 'Invalid session', array( 'status' => 401 ) );
		}

		$user_2fa = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d AND is_enabled = 1",
				$user_id
			)
		);

		if ( ! $user_2fa ) {
			return new WP_Error( 'not_enabled', '2FA not enabled', array( 'status' => 400 ) );
		}

		$verified = false;

		// Check TOTP.
		if ( $user_2fa->method === 'totp' ) {
			$verified = $this->verify_totp( $user_2fa->secret_key, $code );
		} else {
			$verified = $this->verify_email_sms_code( $user_id, $code );
		}

		// Check backup code if not verified.
		if ( ! $verified ) {
			$verified = $this->verify_backup_code( $user_id, $code );
		}

		if ( ! $verified ) {
			return new WP_Error( 'invalid_code', 'Invalid verification code', array( 'status' => 401 ) );
		}

		// Update last used.
		$wpdb->update(
			$this->tables['user_2fa'],
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'user_id' => $user_id )
		);

		// Trust device if requested.
		$device_token = null;
		if ( $trust_device ) {
			$device_token = $this->trust_current_device( $user_id );
		}

		// Clear nonce.
		delete_transient( 'ict_2fa_nonce_' . $user_id );

		// Log in user.
		wp_set_auth_cookie( $user_id, true );

		return rest_ensure_response(
			array(
				'success'      => true,
				'message'      => 'Verification successful',
				'device_token' => $device_token,
				'redirect_url' => admin_url(),
			)
		);
	}

	/**
	 * Send verification code.
	 */
	public function rest_send_code( $request ) {
		global $wpdb;

		$user_id = intval( $request->get_param( 'user_id' ) );

		$user_2fa = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d AND is_enabled = 1",
				$user_id
			)
		);

		if ( ! $user_2fa || $user_2fa->method === 'totp' ) {
			return new WP_Error( 'invalid_method', 'Cannot send code for this method', array( 'status' => 400 ) );
		}

		$this->send_verification_code( $user_id, $user_2fa->method );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Verification code sent',
			)
		);
	}

	/**
	 * Send verification code via email/SMS.
	 *
	 * @param int    $user_id User ID.
	 * @param string $method  Method (email or sms).
	 */
	private function send_verification_code( $user_id, $method ) {
		global $wpdb;

		// Generate code.
		$code = str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );

		// Store code.
		$wpdb->insert(
			$this->tables['verification'],
			array(
				'user_id'    => $user_id,
				'code_hash'  => wp_hash_password( $code ),
				'method'     => $method,
				'expires_at' => date( 'Y-m-d H:i:s', strtotime( '+10 minutes' ) ),
			)
		);

		$user = get_user_by( 'ID', $user_id );

		if ( $method === 'email' ) {
			wp_mail(
				$user->user_email,
				'Your verification code',
				sprintf( 'Your verification code is: %s\n\nThis code expires in 10 minutes.', $code )
			);
		} elseif ( $method === 'sms' ) {
			$user_2fa = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT phone_number FROM {$this->tables['user_2fa']} WHERE user_id = %d",
					$user_id
				)
			);

			if ( $user_2fa && $user_2fa->phone_number ) {
				do_action( 'ict_send_sms', $user_2fa->phone_number, sprintf( 'Your verification code is: %s', $code ) );
			}
		}
	}

	/**
	 * Verify email/SMS code.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Code to verify.
	 * @return bool
	 */
	private function verify_email_sms_code( $user_id, $code ) {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['verification']}
            WHERE user_id = %d AND expires_at > NOW() AND attempts < 5
            ORDER BY created_at DESC LIMIT 1",
				$user_id
			)
		);

		if ( ! $record ) {
			return false;
		}

		// Increment attempts.
		$wpdb->update(
			$this->tables['verification'],
			array( 'attempts' => $record->attempts + 1 ),
			array( 'id' => $record->id )
		);

		if ( wp_check_password( $code, $record->code_hash ) ) {
			// Delete used code.
			$wpdb->delete( $this->tables['verification'], array( 'id' => $record->id ) );
			return true;
		}

		return false;
	}

	/**
	 * Generate backup codes.
	 */
	public function rest_generate_backup_codes( $request ) {
		$user_id  = get_current_user_id();
		$password = $request->get_param( 'password' );

		// Verify password.
		$user = get_user_by( 'ID', $user_id );
		if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
			return new WP_Error( 'invalid_password', 'Invalid password', array( 'status' => 401 ) );
		}

		$codes = $this->generate_backup_codes_internal( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'codes'   => $codes,
				'message' => 'New backup codes generated. Store them securely.',
			)
		);
	}

	/**
	 * Generate backup codes internally.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function generate_backup_codes_internal( $user_id ) {
		global $wpdb;

		// Delete old codes.
		$wpdb->delete( $this->tables['backup_codes'], array( 'user_id' => $user_id ) );

		$codes = array();

		for ( $i = 0; $i < 10; $i++ ) {
			$code    = strtoupper( wp_generate_password( 8, false ) );
			$codes[] = $code;

			$wpdb->insert(
				$this->tables['backup_codes'],
				array(
					'user_id'   => $user_id,
					'code_hash' => wp_hash_password( $code ),
				)
			);
		}

		return $codes;
	}

	/**
	 * Verify backup code.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Code to verify.
	 * @return bool
	 */
	private function verify_backup_code( $user_id, $code ) {
		global $wpdb;

		$codes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['backup_codes']}
            WHERE user_id = %d AND is_used = 0",
				$user_id
			)
		);

		foreach ( $codes as $backup_code ) {
			if ( wp_check_password( strtoupper( $code ), $backup_code->code_hash ) ) {
				// Mark as used.
				$wpdb->update(
					$this->tables['backup_codes'],
					array(
						'is_used' => 1,
						'used_at' => current_time( 'mysql' ),
					),
					array( 'id' => $backup_code->id )
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Get backup codes.
	 */
	public function rest_get_backup_codes( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as remaining
            FROM {$this->tables['backup_codes']}
            WHERE user_id = %d",
				$user_id
			)
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'total'     => intval( $stats->total ),
				'remaining' => intval( $stats->remaining ),
			)
		);
	}

	/**
	 * Get trusted devices.
	 */
	public function rest_get_trusted_devices( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$devices = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, device_name, browser, os, ip_address, trusted_until, last_used_at, created_at
            FROM {$this->tables['trusted_devices']}
            WHERE user_id = %d AND trusted_until > NOW()
            ORDER BY last_used_at DESC",
				$user_id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'devices' => $devices,
			)
		);
	}

	/**
	 * Remove trusted device.
	 */
	public function rest_remove_trusted_device( $request ) {
		global $wpdb;

		$user_id   = get_current_user_id();
		$device_id = intval( $request->get_param( 'id' ) );

		$wpdb->delete(
			$this->tables['trusted_devices'],
			array(
				'id'      => $device_id,
				'user_id' => $user_id,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Device removed successfully',
			)
		);
	}

	/**
	 * Trust current device.
	 *
	 * @param int $user_id User ID.
	 * @return string Device token.
	 */
	private function trust_current_device( $user_id ) {
		global $wpdb;

		$token = wp_generate_password( 64, false );
		$days  = get_option( 'ict_2fa_trust_days', 30 );

		$wpdb->insert(
			$this->tables['trusted_devices'],
			array(
				'user_id'       => $user_id,
				'device_token'  => hash( 'sha256', $token ),
				'device_name'   => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' ),
				'browser'       => $this->detect_browser(),
				'os'            => $this->detect_os(),
				'ip_address'    => $this->get_client_ip(),
				'trusted_until' => date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) ),
			)
		);

		return $token;
	}

	/**
	 * Check if device is trusted.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Device token.
	 * @return bool
	 */
	private function is_device_trusted( $user_id, $token ) {
		global $wpdb;

		if ( ! $token ) {
			return false;
		}

		$device = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['trusted_devices']}
            WHERE user_id = %d AND device_token = %s AND trusted_until > NOW()",
				$user_id,
				hash( 'sha256', $token )
			)
		);

		if ( $device ) {
			// Update last used.
			$wpdb->update(
				$this->tables['trusted_devices'],
				array( 'last_used_at' => current_time( 'mysql' ) ),
				array( 'id' => $device->id )
			);
			return true;
		}

		return false;
	}

	/**
	 * Get 2FA status.
	 */
	public function rest_get_status( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$user_2fa = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d",
				$user_id
			)
		);

		$backup_codes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['backup_codes']}
            WHERE user_id = %d AND is_used = 0",
				$user_id
			)
		);

		$trusted_devices = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['trusted_devices']}
            WHERE user_id = %d AND trusted_until > NOW()",
				$user_id
			)
		);

		return rest_ensure_response(
			array(
				'success'         => true,
				'is_enabled'      => $user_2fa && $user_2fa->is_enabled,
				'method'          => $user_2fa ? $user_2fa->method : null,
				'enabled_at'      => $user_2fa ? $user_2fa->enabled_at : null,
				'last_used_at'    => $user_2fa ? $user_2fa->last_used_at : null,
				'backup_codes'    => intval( $backup_codes ),
				'trusted_devices' => intval( $trusted_devices ),
			)
		);
	}

	/**
	 * Get 2FA settings.
	 */
	public function rest_get_settings( $request ) {
		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => array(
					'required_roles'    => get_option( 'ict_2fa_required_roles', array() ),
					'trust_days'        => get_option( 'ict_2fa_trust_days', 30 ),
					'sms_enabled'       => get_option( 'ict_2fa_sms_enabled', false ),
					'email_enabled'     => get_option( 'ict_2fa_email_enabled', true ),
					'totp_enabled'      => get_option( 'ict_2fa_totp_enabled', true ),
					'grace_period_days' => get_option( 'ict_2fa_grace_period', 7 ),
				),
			)
		);
	}

	/**
	 * Update 2FA settings.
	 */
	public function rest_update_settings( $request ) {
		$settings = array(
			'ict_2fa_required_roles' => $request->get_param( 'required_roles' ),
			'ict_2fa_trust_days'     => intval( $request->get_param( 'trust_days' ) ),
			'ict_2fa_sms_enabled'    => $request->get_param( 'sms_enabled' ),
			'ict_2fa_email_enabled'  => $request->get_param( 'email_enabled' ),
			'ict_2fa_totp_enabled'   => $request->get_param( 'totp_enabled' ),
			'ict_2fa_grace_period'   => intval( $request->get_param( 'grace_period_days' ) ),
		);

		foreach ( $settings as $key => $value ) {
			if ( $value !== null ) {
				update_option( $key, $value );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Settings updated successfully',
			)
		);
	}

	/**
	 * Check if 2FA is required during login.
	 */
	public function check_2fa_required( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! $user ) {
			return $user;
		}

		global $wpdb;

		$user_2fa = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d AND is_enabled = 1",
				$user->ID
			)
		);

		if ( ! $user_2fa ) {
			return $user;
		}

		// Check trusted device.
		$device_token = isset( $_COOKIE['ict_device_token'] ) ? sanitize_text_field( $_COOKIE['ict_device_token'] ) : null;
		if ( $this->is_device_trusted( $user->ID, $device_token ) ) {
			return $user;
		}

		// Generate nonce for 2FA verification.
		$nonce = wp_generate_password( 32, false );
		set_transient( 'ict_2fa_nonce_' . $user->ID, $nonce, 300 );

		// Return error to trigger 2FA.
		return new WP_Error(
			'2fa_required',
			'2FA verification required',
			array(
				'user_id' => $user->ID,
				'method'  => $user_2fa->method,
				'nonce'   => $nonce,
			)
		);
	}

	/**
	 * Handle login for 2FA users.
	 */
	public function handle_login( $user_login, $user ) {
		// Send email code if method is email.
		global $wpdb;

		$user_2fa = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['user_2fa']} WHERE user_id = %d AND is_enabled = 1 AND method = 'email'",
				$user->ID
			)
		);

		if ( $user_2fa ) {
			$this->send_verification_code( $user->ID, 'email' );
		}
	}

	/**
	 * Detect browser from user agent.
	 *
	 * @return string
	 */
	private function detect_browser() {
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		if ( strpos( $user_agent, 'Chrome' ) !== false ) {
			return 'Chrome';
		} elseif ( strpos( $user_agent, 'Firefox' ) !== false ) {
			return 'Firefox';
		} elseif ( strpos( $user_agent, 'Safari' ) !== false ) {
			return 'Safari';
		} elseif ( strpos( $user_agent, 'Edge' ) !== false ) {
			return 'Edge';
		}

		return 'Unknown';
	}

	/**
	 * Detect OS from user agent.
	 *
	 * @return string
	 */
	private function detect_os() {
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		if ( strpos( $user_agent, 'Windows' ) !== false ) {
			return 'Windows';
		} elseif ( strpos( $user_agent, 'Mac' ) !== false ) {
			return 'macOS';
		} elseif ( strpos( $user_agent, 'Linux' ) !== false ) {
			return 'Linux';
		} elseif ( strpos( $user_agent, 'Android' ) !== false ) {
			return 'Android';
		} elseif ( strpos( $user_agent, 'iOS' ) !== false ) {
			return 'iOS';
		}

		return 'Unknown';
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
				$ip = explode( ',', $_SERVER[ $key ] )[0];
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Cleanup expired data.
	 */
	public function cleanup_expired_data() {
		global $wpdb;

		// Remove expired verification codes.
		$wpdb->query(
			"DELETE FROM {$this->tables['verification']} WHERE expires_at < NOW()"
		);

		// Remove expired trusted devices.
		$wpdb->query(
			"DELETE FROM {$this->tables['trusted_devices']} WHERE trusted_until < NOW()"
		);
	}
}
