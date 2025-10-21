<?php
/**
 * Zoho OAuth Token Manager
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_Token_Manager
 *
 * Manages OAuth tokens with automatic refresh and encryption.
 */
class ICT_Zoho_Token_Manager {

	/**
	 * Zoho service name.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $service;

	/**
	 * OAuth base URL.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $oauth_base_url = 'https://accounts.zoho.com/oauth/v2';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $service Service name.
	 */
	public function __construct( $service ) {
		$this->service = $service;
	}

	/**
	 * Get valid access token.
	 *
	 * @since  1.0.0
	 * @return string|false Access token or false if not available.
	 */
	public function get_access_token() {
		// Check if token exists
		$access_token = $this->get_stored_access_token();

		if ( ! $access_token ) {
			return false;
		}

		// Check if token is expired
		if ( $this->is_token_expired() ) {
			// Try to refresh
			if ( ! $this->refresh_token() ) {
				return false;
			}
			// Get the new token
			$access_token = $this->get_stored_access_token();
		}

		return $access_token;
	}

	/**
	 * Get stored access token.
	 *
	 * @since  1.0.0
	 * @return string|false Access token or false.
	 */
	protected function get_stored_access_token() {
		$encrypted_token = get_option( "ict_zoho_{$this->service}_access_token" );

		if ( ! $encrypted_token ) {
			return false;
		}

		return ICT_Admin_Settings::decrypt( $encrypted_token );
	}

	/**
	 * Check if token is expired.
	 *
	 * @since  1.0.0
	 * @return bool True if expired.
	 */
	protected function is_token_expired() {
		$expires_at = get_option( "ict_zoho_{$this->service}_token_expires" );

		if ( ! $expires_at ) {
			return true;
		}

		// Check if expired or expiring in next 5 minutes
		return ( time() + 300 ) >= $expires_at;
	}

	/**
	 * Refresh access token.
	 *
	 * @since  1.0.0
	 * @return bool True on success.
	 */
	public function refresh_token() {
		$refresh_token = $this->get_refresh_token();

		if ( ! $refresh_token ) {
			return false;
		}

		$client_id     = get_option( "ict_zoho_{$this->service}_client_id" );
		$client_secret = ICT_Admin_Settings::decrypt(
			get_option( "ict_zoho_{$this->service}_client_secret" )
		);

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$response = wp_remote_post(
			$this->oauth_base_url . '/token',
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Log error
			error_log(
				sprintf(
					'Zoho token refresh failed for %s: %s',
					$this->service,
					$response->get_error_message()
				)
			);
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			error_log(
				sprintf(
					'Zoho token refresh error for %s: %s',
					$this->service,
					$body['error']
				)
			);
			return false;
		}

		if ( ! isset( $body['access_token'] ) ) {
			return false;
		}

		// Store new access token
		$this->store_access_token( $body['access_token'], $body['expires_in'] );

		// Update refresh token if provided
		if ( isset( $body['refresh_token'] ) ) {
			$this->store_refresh_token( $body['refresh_token'] );
		}

		return true;
	}

	/**
	 * Get refresh token.
	 *
	 * @since  1.0.0
	 * @return string|false Refresh token or false.
	 */
	protected function get_refresh_token() {
		$encrypted_token = get_option( "ict_zoho_{$this->service}_refresh_token" );

		if ( ! $encrypted_token ) {
			return false;
		}

		return ICT_Admin_Settings::decrypt( $encrypted_token );
	}

	/**
	 * Store OAuth tokens.
	 *
	 * @since  1.0.0
	 * @param  string $access_token  Access token.
	 * @param  string $refresh_token Refresh token.
	 * @param  int    $expires_in    Expiration time in seconds.
	 * @return bool True on success.
	 */
	public function store_tokens( $access_token, $refresh_token, $expires_in ) {
		$this->store_access_token( $access_token, $expires_in );
		$this->store_refresh_token( $refresh_token );

		// Update last sync time
		update_option( "ict_zoho_{$this->service}_last_sync", current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Store access token.
	 *
	 * @since  1.0.0
	 * @param  string $access_token Access token.
	 * @param  int    $expires_in   Expiration time in seconds.
	 * @return void
	 */
	protected function store_access_token( $access_token, $expires_in ) {
		// Encrypt token
		$encrypted = $this->encrypt_token( $access_token );

		update_option( "ict_zoho_{$this->service}_access_token", $encrypted );
		update_option( "ict_zoho_{$this->service}_token_expires", time() + $expires_in );
	}

	/**
	 * Store refresh token.
	 *
	 * @since  1.0.0
	 * @param  string $refresh_token Refresh token.
	 * @return void
	 */
	protected function store_refresh_token( $refresh_token ) {
		$encrypted = $this->encrypt_token( $refresh_token );
		update_option( "ict_zoho_{$this->service}_refresh_token", $encrypted );
	}

	/**
	 * Encrypt token.
	 *
	 * @since  1.0.0
	 * @param  string $token Token to encrypt.
	 * @return string Encrypted token.
	 */
	protected function encrypt_token( $token ) {
		if ( empty( $token ) ) {
			return '';
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$key   = wp_salt( 'auth' );
			$iv    = substr( wp_salt( 'secure_auth' ), 0, 16 );
			$token = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );
		}

		return $token;
	}

	/**
	 * Revoke tokens.
	 *
	 * @since  1.0.0
	 * @return bool True on success.
	 */
	public function revoke_tokens() {
		delete_option( "ict_zoho_{$this->service}_access_token" );
		delete_option( "ict_zoho_{$this->service}_refresh_token" );
		delete_option( "ict_zoho_{$this->service}_token_expires" );

		return true;
	}

	/**
	 * Check if service is authenticated.
	 *
	 * @since  1.0.0
	 * @return bool True if authenticated.
	 */
	public function is_authenticated() {
		$access_token  = get_option( "ict_zoho_{$this->service}_access_token" );
		$refresh_token = get_option( "ict_zoho_{$this->service}_refresh_token" );

		return ! empty( $access_token ) && ! empty( $refresh_token );
	}
}
