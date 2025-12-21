<?php
/**
 * REST API: Authentication Controller
 *
 * Handles authentication endpoints for mobile app.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Auth_Controller
 *
 * Handles user authentication via REST API.
 */
class ICT_REST_Auth_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ict/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'auth';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /auth/login
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'username' => array(
							'required' => true,
							'type'     => 'string',
						),
						'password' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		// POST /auth/logout
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// POST /auth/refresh
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refresh_token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'refresh_token' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		// GET /auth/profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /auth/validate
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/validate',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'validate_token' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * Login user and generate tokens.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function login( $request ) {
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$password = $request->get_param( 'password' );

		// Authenticate user
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'authentication_failed',
				'Invalid username or password',
				array( 'status' => 401 )
			);
		}

		// Generate JWT tokens
		$token         = $this->generate_jwt_token( $user->ID );
		$refresh_token = $this->generate_refresh_token( $user->ID );

		// Save refresh token
		update_user_meta( $user->ID, '_ict_refresh_token', $refresh_token );
		update_user_meta( $user->ID, '_ict_refresh_token_expiry', time() + ( 30 * DAY_IN_SECONDS ) );

		// Prepare user data
		$user_data = $this->prepare_user_data( $user );

		return new WP_REST_Response(
			array(
				'token'         => $token,
				'refresh_token' => $refresh_token,
				'user'          => $user_data,
			),
			200
		);
	}

	/**
	 * Logout user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function logout( $request ) {
		$user_id = get_current_user_id();

		// Delete refresh token
		delete_user_meta( $user_id, '_ict_refresh_token' );
		delete_user_meta( $user_id, '_ict_refresh_token_expiry' );

		return new WP_REST_Response(
			array(
				'message' => 'Logged out successfully',
			),
			200
		);
	}

	/**
	 * Refresh access token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refresh_token( $request ) {
		$refresh_token = sanitize_text_field( $request->get_param( 'refresh_token' ) );

		// Validate refresh token
		$user_id = $this->validate_refresh_token( $refresh_token );

		if ( ! $user_id ) {
			return new WP_Error(
				'invalid_refresh_token',
				'Invalid or expired refresh token',
				array( 'status' => 401 )
			);
		}

		// Generate new tokens
		$token             = $this->generate_jwt_token( $user_id );
		$new_refresh_token = $this->generate_refresh_token( $user_id );

		// Update refresh token
		update_user_meta( $user_id, '_ict_refresh_token', $new_refresh_token );
		update_user_meta( $user_id, '_ict_refresh_token_expiry', time() + ( 30 * DAY_IN_SECONDS ) );

		return new WP_REST_Response(
			array(
				'token'         => $token,
				'refresh_token' => $new_refresh_token,
			),
			200
		);
	}

	/**
	 * Get current user profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_profile( $request ) {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return new WP_Error(
				'not_authenticated',
				'User not authenticated',
				array( 'status' => 401 )
			);
		}

		$user_data = $this->prepare_user_data( $user );

		return new WP_REST_Response( $user_data, 200 );
	}

	/**
	 * Validate token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function validate_token( $request ) {
		return new WP_REST_Response(
			array(
				'valid'   => true,
				'user_id' => get_current_user_id(),
			),
			200
		);
	}

	/**
	 * Generate JWT token.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function generate_jwt_token( $user_id ) {
		$issued_at  = time();
		$expires_at = $issued_at + ( 24 * HOUR_IN_SECONDS ); // 24 hours

		$payload = array(
			'iss'     => get_bloginfo( 'url' ),
			'iat'     => $issued_at,
			'exp'     => $expires_at,
			'user_id' => $user_id,
		);

		return $this->encode_jwt( $payload );
	}

	/**
	 * Generate refresh token.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function generate_refresh_token( $user_id ) {
		return wp_generate_password( 64, false );
	}

	/**
	 * Validate refresh token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return int|false User ID if valid, false otherwise.
	 */
	private function validate_refresh_token( $refresh_token ) {
		global $wpdb;

		// Find user with this refresh token
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				WHERE meta_key = '_ict_refresh_token'
				AND meta_value = %s",
				$refresh_token
			)
		);

		if ( ! $user_id ) {
			return false;
		}

		// Check expiry
		$expiry = get_user_meta( $user_id, '_ict_refresh_token_expiry', true );
		if ( ! $expiry || time() > $expiry ) {
			return false;
		}

		return (int) $user_id;
	}

	/**
	 * Encode JWT.
	 *
	 * @param array $payload Payload data.
	 * @return string
	 */
	private function encode_jwt( $payload ) {
		$secret_key = $this->get_secret_key();

		$header = array(
			'typ' => 'JWT',
			'alg' => 'HS256',
		);

		$header_encoded  = $this->base64url_encode( wp_json_encode( $header ) );
		$payload_encoded = $this->base64url_encode( wp_json_encode( $payload ) );

		$signature = hash_hmac(
			'sha256',
			$header_encoded . '.' . $payload_encoded,
			$secret_key,
			true
		);

		$signature_encoded = $this->base64url_encode( $signature );

		return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
	}

	/**
	 * Base64 URL encode.
	 *
	 * @param string $data Data to encode.
	 * @return string
	 */
	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Get secret key.
	 *
	 * @return string
	 */
	private function get_secret_key() {
		if ( defined( 'ICT_JWT_SECRET_KEY' ) ) {
			return ICT_JWT_SECRET_KEY;
		}

		// Generate and save secret key
		$secret_key = get_option( 'ict_jwt_secret_key' );
		if ( ! $secret_key ) {
			$secret_key = wp_generate_password( 64, true, true );
			update_option( 'ict_jwt_secret_key', $secret_key, false );
		}

		return $secret_key;
	}

	/**
	 * Prepare user data for response.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	private function prepare_user_data( $user ) {
		return array(
			'id'           => $user->ID,
			'username'     => $user->user_login,
			'email'        => $user->user_email,
			'firstName'    => $user->first_name,
			'lastName'     => $user->last_name,
			'displayName'  => $user->display_name,
			'avatar'       => get_avatar_url( $user->ID ),
			'roles'        => $user->roles,
			'capabilities' => array_keys( array_filter( $user->allcaps ) ),
		);
	}
}
