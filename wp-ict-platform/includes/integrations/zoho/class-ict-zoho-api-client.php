<?php
/**
 * Base Zoho API Client
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_API_Client
 *
 * Base class for making authenticated requests to Zoho APIs.
 */
class ICT_Zoho_API_Client {

	/**
	 * Zoho service name.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $service;

	/**
	 * API base URL.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $api_base_url;

	/**
	 * OAuth base URL.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $oauth_base_url = 'https://accounts.zoho.com/oauth/v2';

	/**
	 * Rate limiter instance.
	 *
	 * @since  1.0.0
	 * @var    ICT_Zoho_Rate_Limiter
	 */
	protected $rate_limiter;

	/**
	 * Token manager instance.
	 *
	 * @since  1.0.0
	 * @var    ICT_Zoho_Token_Manager
	 */
	protected $token_manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $service Service name (crm, fsm, books, people, desk).
	 */
	public function __construct( $service ) {
		$this->service       = $service;
		$this->rate_limiter  = new ICT_Zoho_Rate_Limiter( $service );
		$this->token_manager = new ICT_Zoho_Token_Manager( $service );

		// Set API base URL based on service
		$this->set_api_base_url();
	}

	/**
	 * Set API base URL based on service.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function set_api_base_url() {
		$base_urls = array(
			'crm'    => 'https://www.zohoapis.com/crm/v3',
			'fsm'    => 'https://www.zohoapis.com/fsm/v1',
			'books'  => 'https://www.zohoapis.com/books/v3',
			'people' => 'https://www.zohoapis.com/people/api',
			'desk'   => 'https://www.zohoapis.com/desk/v1',
		);

		$this->api_base_url = $base_urls[ $this->service ] ?? '';
	}

	/**
	 * Make GET request.
	 *
	 * @since  1.0.0
	 * @param  string $endpoint API endpoint.
	 * @param  array  $params   Query parameters.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function get( $endpoint, $params = array() ) {
		return $this->request( 'GET', $endpoint, $params );
	}

	/**
	 * Make POST request.
	 *
	 * @since  1.0.0
	 * @param  string $endpoint API endpoint.
	 * @param  array  $data     Request body data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function post( $endpoint, $data = array() ) {
		return $this->request( 'POST', $endpoint, array(), $data );
	}

	/**
	 * Make PUT request.
	 *
	 * @since  1.0.0
	 * @param  string $endpoint API endpoint.
	 * @param  array  $data     Request body data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function put( $endpoint, $data = array() ) {
		return $this->request( 'PUT', $endpoint, array(), $data );
	}

	/**
	 * Make DELETE request.
	 *
	 * @since  1.0.0
	 * @param  string $endpoint API endpoint.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function delete( $endpoint ) {
		return $this->request( 'DELETE', $endpoint );
	}

	/**
	 * Make authenticated API request.
	 *
	 * @since  1.0.0
	 * @param  string $method   HTTP method.
	 * @param  string $endpoint API endpoint.
	 * @param  array  $params   Query parameters.
	 * @param  array  $data     Request body data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	protected function request( $method, $endpoint, $params = array(), $data = array() ) {
		// Check rate limit
		if ( ! $this->rate_limiter->check() ) {
			throw new Exception( __( 'Rate limit exceeded. Please try again later.', 'ict-platform' ) );
		}

		// Get access token
		$access_token = $this->token_manager->get_access_token();
		if ( ! $access_token ) {
			throw new Exception( __( 'Not authenticated with Zoho. Please connect your account.', 'ict-platform' ) );
		}

		// Build URL
		$url = $this->api_base_url . '/' . ltrim( $endpoint, '/' );
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		// Prepare request arguments
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Zoho-oauthtoken ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Make request
		$start_time = microtime( true );
		$response   = wp_remote_request( $url, $args );
		$duration   = round( ( microtime( true ) - $start_time ) * 1000 );

		// Record rate limit usage
		$this->rate_limiter->record();

		// Handle response
		if ( is_wp_error( $response ) ) {
			throw new Exception(
				sprintf(
					__( 'API request failed: %s', 'ict-platform' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		// Handle errors
		if ( $status_code >= 400 ) {
			$error_message = $this->extract_error_message( $decoded, $status_code );

			// If unauthorized, try to refresh token and retry once
			if ( 401 === $status_code ) {
				if ( $this->token_manager->refresh_token() ) {
					return $this->request( $method, $endpoint, $params, $data );
				}
			}

			throw new Exception( $error_message );
		}

		return array(
			'success'  => true,
			'data'     => $decoded,
			'duration' => $duration,
		);
	}

	/**
	 * Extract error message from response.
	 *
	 * @since  1.0.0
	 * @param  array $response    Decoded response.
	 * @param  int   $status_code HTTP status code.
	 * @return string Error message.
	 */
	protected function extract_error_message( $response, $status_code ) {
		if ( isset( $response['message'] ) ) {
			return $response['message'];
		}

		if ( isset( $response['error'] ) ) {
			if ( is_string( $response['error'] ) ) {
				return $response['error'];
			}
			if ( is_array( $response['error'] ) && isset( $response['error']['message'] ) ) {
				return $response['error']['message'];
			}
		}

		if ( isset( $response['code'] ) && isset( $response['details'] ) ) {
			return sprintf( '%s: %s', $response['code'], $response['details'] );
		}

		return sprintf( __( 'API request failed with status code %d', 'ict-platform' ), $status_code );
	}

	/**
	 * Test connection to Zoho service.
	 *
	 * @since  1.0.0
	 * @return array Test result.
	 */
	public function test_connection() {
		try {
			// Each service has different test endpoints
			$test_endpoints = array(
				'crm'    => '/users',
				'fsm'    => '/territories',
				'books'  => '/organizations',
				'people' => '/forms',
				'desk'   => '/myinfo',
			);

			$endpoint = $test_endpoints[ $this->service ] ?? '/';
			$this->get( $endpoint );

			return array(
				'success' => true,
				'message' => __( 'Connection successful', 'ict-platform' ),
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Get authorization URL for OAuth flow.
	 *
	 * @since  1.0.0
	 * @param  string $redirect_uri Redirect URI after authorization.
	 * @return string Authorization URL.
	 */
	public function get_authorization_url( $redirect_uri ) {
		$client_id = get_option( "ict_zoho_{$this->service}_client_id" );

		if ( empty( $client_id ) ) {
			return '';
		}

		$params = array(
			'scope'         => $this->get_scope(),
			'client_id'     => $client_id,
			'response_type' => 'code',
			'access_type'   => 'offline',
			'redirect_uri'  => $redirect_uri,
			'state'         => wp_create_nonce( 'zoho_oauth_' . $this->service ),
		);

		return $this->oauth_base_url . '/auth?' . http_build_query( $params );
	}

	/**
	 * Get OAuth scope for service.
	 *
	 * @since  1.0.0
	 * @return string Scope string.
	 */
	protected function get_scope() {
		$scopes = array(
			'crm'    => 'ZohoCRM.modules.ALL,ZohoCRM.settings.ALL,ZohoCRM.users.READ',
			'fsm'    => 'ZohoFSM.modules.ALL,ZohoFSM.settings.ALL',
			'books'  => 'ZohoBooks.fullaccess.ALL',
			'people' => 'ZohoPeople.forms.ALL,ZohoPeople.employee.ALL,ZohoPeople.timesheets.ALL',
			'desk'   => 'Desk.tickets.ALL,Desk.settings.READ',
		);

		return $scopes[ $this->service ] ?? '';
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @since  1.0.0
	 * @param  string $code         Authorization code.
	 * @param  string $redirect_uri Redirect URI.
	 * @return bool True on success.
	 * @throws Exception On error.
	 */
	public function exchange_code_for_tokens( $code, $redirect_uri ) {
		$client_id     = get_option( "ict_zoho_{$this->service}_client_id" );
		$client_secret = ICT_Admin_Settings::decrypt(
			get_option( "ict_zoho_{$this->service}_client_secret" )
		);

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new Exception( __( 'Client credentials not configured', 'ict-platform' ) );
		}

		$response = wp_remote_post(
			$this->oauth_base_url . '/token',
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			throw new Exception( $body['error'] );
		}

		// Store tokens
		return $this->token_manager->store_tokens(
			$body['access_token'],
			$body['refresh_token'],
			$body['expires_in']
		);
	}
}
