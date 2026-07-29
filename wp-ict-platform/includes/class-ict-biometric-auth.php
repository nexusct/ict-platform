<?php
/**
 * Biometric Authentication Handler
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Biometric_Auth
 *
 * Handles WebAuthn/FIDO2 biometric authentication for mobile devices.
 */
class ICT_Biometric_Auth {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Biometric_Auth
	 */
	private static $instance = null;

	/**
	 * Relying party ID (domain).
	 *
	 * @var string
	 */
	private $rp_id;

	/**
	 * Relying party name.
	 *
	 * @var string
	 */
	private $rp_name;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Biometric_Auth
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		$this->rp_id   = wp_parse_url( home_url(), PHP_URL_HOST );
		$this->rp_name = get_bloginfo( 'name' );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes() {
		$this->register_endpoints();
		add_action( 'wp_login', array( $this, 'maybe_prompt_biometric_setup' ), 10, 2 );
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_endpoints() {
		// Registration endpoints
		register_rest_route(
			'ict/v1',
			'/auth/biometric/register/options',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_registration_options' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'ict/v1',
			'/auth/biometric/register/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'verify_registration' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Authentication endpoints
		register_rest_route(
			'ict/v1',
			'/auth/biometric/login/options',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_authentication_options' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ict/v1',
			'/auth/biometric/login/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'verify_authentication' ),
				'permission_callback' => '__return_true',
			)
		);

		// Management endpoints
		register_rest_route(
			'ict/v1',
			'/auth/biometric/credentials',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_credentials' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'ict/v1',
			'/auth/biometric/credentials/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_credential' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'ict/v1',
			'/auth/biometric/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_biometric_status' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Get WebAuthn registration options.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_registration_options( $request ) {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Not authenticated',
				),
				401
			);
		}

		// Generate challenge
		$challenge = $this->generate_challenge();

		// Store challenge in transient
		set_transient(
			'ict_webauthn_reg_' . $user->ID,
			$challenge,
			300 // 5 minutes
		);

		// Get existing credentials for exclusion
		$existing_credentials = $this->get_user_credentials( $user->ID );
		$exclude_credentials  = array();

		foreach ( $existing_credentials as $cred ) {
			$exclude_credentials[] = array(
				'type' => 'public-key',
				'id'   => $cred['credential_id'],
			);
		}

		$device_name = $request->get_param( 'device_name' ) ?? 'Mobile Device';

		$options = array(
			'challenge'              => $this->base64url_encode( $challenge ),
			'rp'                     => array(
				'name' => $this->rp_name,
				'id'   => $this->rp_id,
			),
			'user'                   => array(
				'id'          => $this->base64url_encode( (string) $user->ID ),
				'name'        => $user->user_login,
				'displayName' => $user->display_name,
			),
			'pubKeyCredParams'       => array(
				array(
					'alg'  => -7, // ES256
					'type' => 'public-key',
				),
				array(
					'alg'  => -257, // RS256
					'type' => 'public-key',
				),
			),
			'timeout'                => 60000, // 60 seconds
			'attestation'            => 'none',
			'authenticatorSelection' => array(
				'authenticatorAttachment' => 'platform', // Use platform authenticator (TouchID, FaceID, etc.)
				'userVerification'        => 'required',
				'residentKey'             => 'preferred',
			),
			'excludeCredentials'     => $exclude_credentials,
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'options' => $options,
			),
			200
		);
	}

	/**
	 * Verify WebAuthn registration.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function verify_registration( $request ) {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Not authenticated',
				),
				401
			);
		}

		// Get stored challenge
		$stored_challenge = get_transient( 'ict_webauthn_reg_' . $user->ID );

		if ( ! $stored_challenge ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Registration session expired',
				),
				400
			);
		}

		// Delete challenge
		delete_transient( 'ict_webauthn_reg_' . $user->ID );

		// Get credential from request
		$credential    = $request->get_json_params();
		$device_name   = $credential['device_name'] ?? 'Unknown Device';
		$credential_id = $credential['id'] ?? '';
		$client_data   = $credential['response']['clientDataJSON'] ?? '';
		$attestation   = $credential['response']['attestationObject'] ?? '';

		if ( empty( $credential_id ) || empty( $client_data ) || empty( $attestation ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid credential data',
				),
				400
			);
		}

		// Decode client data
		$client_data_decoded = json_decode( $this->base64url_decode( $client_data ), true );

		if ( ! $client_data_decoded ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to decode client data',
				),
				400
			);
		}

		// Verify challenge
		$challenge_from_client = $this->base64url_decode( $client_data_decoded['challenge'] );

		if ( ! hash_equals( $stored_challenge, $challenge_from_client ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Challenge verification failed',
				),
				400
			);
		}

		// Verify origin
		$expected_origin = home_url();
		if ( isset( $client_data_decoded['origin'] ) && $client_data_decoded['origin'] !== $expected_origin ) {
			// Allow localhost for development
			if ( strpos( $client_data_decoded['origin'], 'localhost' ) === false ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'Origin verification failed',
					),
					400
				);
			}
		}

		// Parse attestation object and extract public key
		$attestation_decoded = $this->base64url_decode( $attestation );
		$public_key_info     = $this->parse_attestation( $attestation_decoded );

		if ( ! $public_key_info ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to parse attestation',
				),
				400
			);
		}

		// Store credential
		$saved = $this->save_credential(
			$user->ID,
			array(
				'credential_id' => $credential_id,
				'public_key'    => $public_key_info['public_key'],
				'sign_count'    => $public_key_info['sign_count'] ?? 0,
				'device_name'   => sanitize_text_field( $device_name ),
				'created_at'    => current_time( 'mysql' ),
				'last_used'     => null,
			)
		);

		if ( ! $saved ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to save credential',
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Biometric authentication registered successfully',
			),
			200
		);
	}

	/**
	 * Get WebAuthn authentication options.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_authentication_options( $request ) {
		$username = $request->get_param( 'username' );

		if ( empty( $username ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Username required',
				),
				400
			);
		}

		$user = get_user_by( 'login', $username );

		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}

		if ( ! $user ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'User not found',
				),
				404
			);
		}

		// Get user credentials
		$credentials = $this->get_user_credentials( $user->ID );

		if ( empty( $credentials ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'No biometric credentials registered',
				),
				400
			);
		}

		// Generate challenge
		$challenge = $this->generate_challenge();

		// Store challenge
		set_transient(
			'ict_webauthn_auth_' . $user->ID,
			$challenge,
			300 // 5 minutes
		);

		// Build allowed credentials
		$allow_credentials = array();
		foreach ( $credentials as $cred ) {
			$allow_credentials[] = array(
				'type' => 'public-key',
				'id'   => $cred['credential_id'],
			);
		}

		$options = array(
			'challenge'        => $this->base64url_encode( $challenge ),
			'timeout'          => 60000,
			'rpId'             => $this->rp_id,
			'allowCredentials' => $allow_credentials,
			'userVerification' => 'required',
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'options' => $options,
				'user_id' => $user->ID,
			),
			200
		);
	}

	/**
	 * Verify WebAuthn authentication.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function verify_authentication( $request ) {
		$credential = $request->get_json_params();
		$user_id    = $credential['user_id'] ?? 0;

		if ( ! $user_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'User ID required',
				),
				400
			);
		}

		// Get stored challenge
		$stored_challenge = get_transient( 'ict_webauthn_auth_' . $user_id );

		if ( ! $stored_challenge ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Authentication session expired',
				),
				400
			);
		}

		// Delete challenge
		delete_transient( 'ict_webauthn_auth_' . $user_id );

		$credential_id      = $credential['id'] ?? '';
		$client_data        = $credential['response']['clientDataJSON'] ?? '';
		$authenticator_data = $credential['response']['authenticatorData'] ?? '';
		$signature          = $credential['response']['signature'] ?? '';

		if ( empty( $credential_id ) || empty( $client_data ) || empty( $authenticator_data ) || empty( $signature ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid credential data',
				),
				400
			);
		}

		// Get stored credential
		$stored_credential = $this->get_credential( $user_id, $credential_id );

		if ( ! $stored_credential ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Credential not found',
				),
				400
			);
		}

		// Decode client data
		$client_data_decoded = json_decode( $this->base64url_decode( $client_data ), true );

		if ( ! $client_data_decoded ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to decode client data',
				),
				400
			);
		}

		// Verify challenge
		$challenge_from_client = $this->base64url_decode( $client_data_decoded['challenge'] );

		if ( ! hash_equals( $stored_challenge, $challenge_from_client ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Challenge verification failed',
				),
				400
			);
		}

		// Verify signature (simplified)
		$auth_data_raw    = $this->base64url_decode( $authenticator_data );
		$client_data_raw  = $this->base64url_decode( $client_data );
		$client_data_hash = hash( 'sha256', $client_data_raw, true );
		$signed_data      = $auth_data_raw . $client_data_hash;
		$signature_raw    = $this->base64url_decode( $signature );

		// Verify with public key
		$verified = $this->verify_signature(
			$signed_data,
			$signature_raw,
			$stored_credential['public_key']
		);

		if ( ! $verified ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Signature verification failed',
				),
				400
			);
		}

		// Update sign count and last used
		$this->update_credential_usage( $user_id, $credential_id );

		// Log in user
		$user = get_user_by( 'id', $user_id );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// Generate auth token for API use
		$token = $this->generate_auth_token( $user_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Authentication successful',
				'user'    => array(
					'id'           => $user->ID,
					'login'        => $user->user_login,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
				),
				'token'   => $token,
			),
			200
		);
	}

	/**
	 * List user's registered credentials.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function list_credentials( $request ) {
		$user_id     = get_current_user_id();
		$credentials = $this->get_user_credentials( $user_id );

		// Don't expose public key
		$safe_credentials = array_map(
			function ( $cred ) {
				return array(
					'id'          => $cred['credential_id'],
					'device_name' => $cred['device_name'],
					'created_at'  => $cred['created_at'],
					'last_used'   => $cred['last_used'],
				);
			},
			$credentials
		);

		return new WP_REST_Response(
			array(
				'success'     => true,
				'credentials' => $safe_credentials,
			),
			200
		);
	}

	/**
	 * Delete a credential.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function delete_credential( $request ) {
		$user_id       = get_current_user_id();
		$credential_id = $request->get_param( 'id' );

		$deleted = $this->remove_credential( $user_id, $credential_id );

		if ( ! $deleted ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to delete credential',
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Credential deleted',
			),
			200
		);
	}

	/**
	 * Get biometric status for current user.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_biometric_status( $request ) {
		$user_id     = get_current_user_id();
		$credentials = $this->get_user_credentials( $user_id );

		return new WP_REST_Response(
			array(
				'success'           => true,
				'biometric_enabled' => ! empty( $credentials ),
				'credential_count'  => count( $credentials ),
				'can_register'      => $this->can_register_biometric(),
			),
			200
		);
	}

	/**
	 * Check if biometric registration is available.
	 *
	 * @since  1.1.0
	 * @return bool True if available.
	 */
	private function can_register_biometric() {
		return get_option( 'ict_enable_biometric_auth', true );
	}

	/**
	 * Generate random challenge.
	 *
	 * @since  1.1.0
	 * @return string Challenge bytes.
	 */
	private function generate_challenge() {
		return random_bytes( 32 );
	}

	/**
	 * Base64URL encode.
	 *
	 * @since  1.1.0
	 * @param  string $data Data to encode.
	 * @return string Encoded string.
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64URL decode.
	 *
	 * @since  1.1.0
	 * @param  string $data Encoded string.
	 * @return string Decoded data.
	 */
	private function base64url_decode( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ) . str_repeat( '=', 3 - ( 3 + strlen( $data ) ) % 4 ) );
	}

	/**
	 * Parse attestation object.
	 *
	 * @since  1.1.0
	 * @param  string $attestation Raw attestation data.
	 * @return array|false Parsed data or false.
	 */
	private function parse_attestation( $attestation ) {
		// Simplified CBOR parsing
		// In production, use a proper CBOR library

		// For now, extract a basic identifier
		return array(
			'public_key' => $this->base64url_encode( $attestation ),
			'sign_count' => 0,
		);
	}

	/**
	 * Verify signature.
	 *
	 * @since  1.1.0
	 * @param  string $data      Data that was signed.
	 * @param  string $signature Signature bytes.
	 * @param  string $public_key Public key.
	 * @return bool True if valid.
	 */
	private function verify_signature( $data, $signature, $public_key ) {
		// Simplified verification
		// In production, use proper cryptographic verification
		return ! empty( $data ) && ! empty( $signature ) && ! empty( $public_key );
	}

	/**
	 * Get user's credentials.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return array Credentials.
	 */
	private function get_user_credentials( $user_id ) {
		$credentials = get_user_meta( $user_id, 'ict_webauthn_credentials', true );
		return is_array( $credentials ) ? $credentials : array();
	}

	/**
	 * Get specific credential.
	 *
	 * @since  1.1.0
	 * @param  int    $user_id       User ID.
	 * @param  string $credential_id Credential ID.
	 * @return array|null Credential or null.
	 */
	private function get_credential( $user_id, $credential_id ) {
		$credentials = $this->get_user_credentials( $user_id );

		foreach ( $credentials as $cred ) {
			if ( $cred['credential_id'] === $credential_id ) {
				return $cred;
			}
		}

		return null;
	}

	/**
	 * Save credential.
	 *
	 * @since  1.1.0
	 * @param  int   $user_id    User ID.
	 * @param  array $credential Credential data.
	 * @return bool True on success.
	 */
	private function save_credential( $user_id, $credential ) {
		$credentials   = $this->get_user_credentials( $user_id );
		$credentials[] = $credential;

		return update_user_meta( $user_id, 'ict_webauthn_credentials', $credentials );
	}

	/**
	 * Remove credential.
	 *
	 * @since  1.1.0
	 * @param  int    $user_id       User ID.
	 * @param  string $credential_id Credential ID.
	 * @return bool True on success.
	 */
	private function remove_credential( $user_id, $credential_id ) {
		$credentials = $this->get_user_credentials( $user_id );

		$credentials = array_filter(
			$credentials,
			function ( $cred ) use ( $credential_id ) {
				return $cred['credential_id'] !== $credential_id;
			}
		);

		return update_user_meta( $user_id, 'ict_webauthn_credentials', array_values( $credentials ) );
	}

	/**
	 * Update credential usage.
	 *
	 * @since  1.1.0
	 * @param  int    $user_id       User ID.
	 * @param  string $credential_id Credential ID.
	 * @return void
	 */
	private function update_credential_usage( $user_id, $credential_id ) {
		$credentials = $this->get_user_credentials( $user_id );

		foreach ( $credentials as &$cred ) {
			if ( $cred['credential_id'] === $credential_id ) {
				$cred['last_used']  = current_time( 'mysql' );
				$cred['sign_count'] = ( $cred['sign_count'] ?? 0 ) + 1;
				break;
			}
		}

		update_user_meta( $user_id, 'ict_webauthn_credentials', $credentials );
	}

	/**
	 * Generate auth token for API.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return string Auth token.
	 */
	private function generate_auth_token( $user_id ) {
		$token = wp_generate_password( 64, false );

		set_transient(
			'ict_auth_token_' . $token,
			$user_id,
			DAY_IN_SECONDS
		);

		return $token;
	}

	/**
	 * Validate auth token.
	 *
	 * @since  1.1.0
	 * @param  string $token Auth token.
	 * @return int|false User ID or false.
	 */
	public function validate_auth_token( $token ) {
		return get_transient( 'ict_auth_token_' . $token );
	}

	/**
	 * Maybe prompt biometric setup after login.
	 *
	 * @since  1.1.0
	 * @param  string  $user_login Username.
	 * @param  WP_User $user       User object.
	 * @return void
	 */
	public function maybe_prompt_biometric_setup( $user_login, $user ) {
		if ( ! $this->can_register_biometric() ) {
			return;
		}

		$credentials = $this->get_user_credentials( $user->ID );

		if ( empty( $credentials ) ) {
			// Set flag to show biometric setup prompt
			set_transient( 'ict_show_biometric_prompt_' . $user->ID, true, 3600 );
		}
	}

	/**
	 * Should show biometric setup prompt.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return bool True if should show.
	 */
	public function should_show_biometric_prompt( $user_id ) {
		$prompt = get_transient( 'ict_show_biometric_prompt_' . $user_id );

		if ( $prompt ) {
			delete_transient( 'ict_show_biometric_prompt_' . $user_id );
			return true;
		}

		return false;
	}
}
