<?php
/**
 * API Rate Limiter
 *
 * Protect API endpoints with rate limiting including:
 * - Per-user and per-IP rate limits
 * - Configurable limits per endpoint
 * - Sliding window algorithm
 * - Rate limit headers in responses
 * - Whitelist/blacklist support
 * - Usage analytics
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_API_Rate_Limiter
 */
class ICT_API_Rate_Limiter {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_API_Rate_Limiter
	 */
	private static $instance = null;

	/**
	 * Table names.
	 *
	 * @var array
	 */
	private $tables = array();

	/**
	 * Default limits.
	 *
	 * @var array
	 */
	private $default_limits = array(
		'requests_per_minute' => 60,
		'requests_per_hour'   => 1000,
		'requests_per_day'    => 10000,
	);

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_API_Rate_Limiter
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
			'rate_limits' => $wpdb->prefix . 'ict_rate_limits',
			'requests'    => $wpdb->prefix . 'ict_api_requests',
			'rules'       => $wpdb->prefix . 'ict_rate_rules',
			'whitelist'   => $wpdb->prefix . 'ict_rate_whitelist',
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

		// Apply rate limiting to REST API.
		add_filter( 'rest_pre_dispatch', array( $this, 'check_rate_limit' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'add_rate_limit_headers' ), 10, 3 );

		// Cleanup.
		add_action( 'ict_cleanup_rate_limit_data', array( $this, 'cleanup_old_data' ) );
		if ( ! wp_next_scheduled( 'ict_cleanup_rate_limit_data' ) ) {
			wp_schedule_event( time(), 'hourly', 'ict_cleanup_rate_limit_data' );
		}
	}

	/**
	 * Create database tables.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		// Rate limits tracking.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['rate_limits']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            identifier_type enum('user','ip','api_key') DEFAULT 'ip',
            endpoint varchar(255) DEFAULT NULL,
            window_start datetime NOT NULL,
            window_type enum('minute','hour','day') DEFAULT 'minute',
            request_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_window (identifier, endpoint(100), window_type, window_start),
            KEY window_start (window_start),
            KEY identifier (identifier)
        ) $charset_collate;";

		// Request log.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['requests']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            api_key varchar(100) DEFAULT NULL,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            status_code int(11) DEFAULT NULL,
            response_time_ms int(11) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            request_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY endpoint (endpoint(100)),
            KEY created_at (created_at)
        ) $charset_collate;";

		// Rate limit rules.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['rules']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            endpoint_pattern varchar(255) DEFAULT '*',
            identifier_type enum('user','ip','api_key','role') DEFAULT 'ip',
            role varchar(100) DEFAULT NULL,
            requests_per_minute int(11) DEFAULT NULL,
            requests_per_hour int(11) DEFAULT NULL,
            requests_per_day int(11) DEFAULT NULL,
            burst_limit int(11) DEFAULT NULL,
            priority int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY endpoint_pattern (endpoint_pattern(100)),
            KEY is_active (is_active)
        ) $charset_collate;";

		// Whitelist/blacklist.
		$sql[] = "CREATE TABLE IF NOT EXISTS {$this->tables['whitelist']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            identifier_type enum('user','ip','api_key','ip_range') DEFAULT 'ip',
            list_type enum('whitelist','blacklist') DEFAULT 'whitelist',
            reason text,
            expires_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY identifier_type_list (identifier, identifier_type, list_type),
            KEY list_type (list_type),
            KEY expires_at (expires_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		$this->seed_default_rules();
	}

	/**
	 * Seed default rate limit rules.
	 */
	private function seed_default_rules() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->tables['rules']}" );
		if ( $count > 0 ) {
			return;
		}

		$rules = array(
			array( 'Default - Anonymous', '*', 'ip', null, 30, 500, 5000, 10, 0 ),
			array( 'Default - Authenticated', '*', 'user', null, 60, 1000, 10000, 20, 10 ),
			array( 'Default - Admin', '*', 'role', 'administrator', 120, 2000, 20000, 50, 20 ),
			array( 'Auth Endpoints', '/wp-json/ict/v1/auth/*', 'ip', null, 5, 20, 100, 5, 100 ),
			array( 'Search Endpoints', '/wp-json/ict/v1/*/search', 'user', null, 20, 200, 2000, 10, 50 ),
			array( 'Export Endpoints', '/wp-json/ict/v1/*/export', 'user', null, 5, 20, 100, 2, 50 ),
		);

		foreach ( $rules as $rule ) {
			$wpdb->insert(
				$this->tables['rules'],
				array(
					'name'                => $rule[0],
					'endpoint_pattern'    => $rule[1],
					'identifier_type'     => $rule[2],
					'role'                => $rule[3],
					'requests_per_minute' => $rule[4],
					'requests_per_hour'   => $rule[5],
					'requests_per_day'    => $rule[6],
					'burst_limit'         => $rule[7],
					'priority'            => $rule[8],
				)
			);
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$namespace = 'ict/v1';

		// Rate limit status.
		register_rest_route(
			$namespace,
			'/rate-limit/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// Rules management.
		register_rest_route(
			$namespace,
			'/rate-limit/rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_rules' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_rule' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/rate-limit/rules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_rule' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_rule' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Whitelist/blacklist.
		register_rest_route(
			$namespace,
			'/rate-limit/list',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_list' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_add_to_list' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/rate-limit/list/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'rest_remove_from_list' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Analytics.
		register_rest_route(
			$namespace,
			'/rate-limit/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Reset limits.
		register_rest_route(
			$namespace,
			'/rate-limit/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_reset_limits' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check admin permission.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check rate limit.
	 *
	 * @param mixed           $result  Response to replace.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function check_rate_limit( $result, $server, $request ) {
		// Skip if already handled or not ICT endpoint.
		if ( $result !== null ) {
			return $result;
		}

		$route = $request->get_route();

		// Only apply to ICT endpoints.
		if ( strpos( $route, '/ict/v1/' ) === false ) {
			return $result;
		}

		$identifier      = $this->get_identifier();
		$identifier_type = $this->get_identifier_type();

		// Check whitelist/blacklist.
		if ( $this->is_blacklisted( $identifier, $identifier_type ) ) {
			return new WP_Error(
				'rate_limit_blocked',
				'Access denied',
				array( 'status' => 403 )
			);
		}

		if ( $this->is_whitelisted( $identifier, $identifier_type ) ) {
			$this->log_request( $request );
			return $result;
		}

		// Get applicable rule.
		$rule   = $this->get_applicable_rule( $route, $identifier_type );
		$limits = $this->get_limits( $rule );

		// Check limits.
		$blocked     = false;
		$retry_after = 0;

		foreach ( array( 'minute', 'hour', 'day' ) as $window_type ) {
			$limit_key = 'requests_per_' . $window_type;
			if ( isset( $limits[ $limit_key ] ) && $limits[ $limit_key ] > 0 ) {
				$count = $this->get_request_count( $identifier, $route, $window_type );

				if ( $count >= $limits[ $limit_key ] ) {
					$blocked     = true;
					$retry_after = $this->get_retry_after( $window_type );
					break;
				}
			}
		}

		if ( $blocked ) {
			return new WP_Error(
				'rate_limit_exceeded',
				'Rate limit exceeded. Please try again later.',
				array(
					'status'      => 429,
					'retry_after' => $retry_after,
				)
			);
		}

		// Increment counters.
		$this->increment_counters( $identifier, $route );

		// Log request.
		$this->log_request( $request );

		return $result;
	}

	/**
	 * Add rate limit headers to response.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_REST_Server   $server   Server.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_REST_Response
	 */
	public function add_rate_limit_headers( $response, $server, $request ) {
		$route = $request->get_route();

		if ( strpos( $route, '/ict/v1/' ) === false ) {
			return $response;
		}

		$identifier      = $this->get_identifier();
		$identifier_type = $this->get_identifier_type();
		$rule            = $this->get_applicable_rule( $route, $identifier_type );
		$limits          = $this->get_limits( $rule );

		// Get current counts.
		$minute_count = $this->get_request_count( $identifier, $route, 'minute' );
		$hour_count   = $this->get_request_count( $identifier, $route, 'hour' );

		$minute_limit = $limits['requests_per_minute'] ?? $this->default_limits['requests_per_minute'];
		$hour_limit   = $limits['requests_per_hour'] ?? $this->default_limits['requests_per_hour'];

		$response->header( 'X-RateLimit-Limit', $minute_limit );
		$response->header( 'X-RateLimit-Remaining', max( 0, $minute_limit - $minute_count ) );
		$response->header( 'X-RateLimit-Reset', time() + ( 60 - ( time() % 60 ) ) );
		$response->header( 'X-RateLimit-Limit-Hour', $hour_limit );
		$response->header( 'X-RateLimit-Remaining-Hour', max( 0, $hour_limit - $hour_count ) );

		return $response;
	}

	/**
	 * Get identifier for current request.
	 *
	 * @return string
	 */
	private function get_identifier() {
		// Check for API key.
		$api_key = $this->get_api_key();
		if ( $api_key ) {
			return 'key:' . $api_key;
		}

		// Check for authenticated user.
		if ( is_user_logged_in() ) {
			return 'user:' . get_current_user_id();
		}

		// Fall back to IP.
		return 'ip:' . $this->get_client_ip();
	}

	/**
	 * Get identifier type.
	 *
	 * @return string
	 */
	private function get_identifier_type() {
		if ( $this->get_api_key() ) {
			return 'api_key';
		}

		if ( is_user_logged_in() ) {
			return 'user';
		}

		return 'ip';
	}

	/**
	 * Get API key from request.
	 *
	 * @return string|null
	 */
	private function get_api_key() {
		// Check header.
		$key = isset( $_SERVER['HTTP_X_API_KEY'] ) ? sanitize_text_field( $_SERVER['HTTP_X_API_KEY'] ) : null;

		if ( ! $key ) {
			// Check query parameter.
			$key = isset( $_GET['api_key'] ) ? sanitize_text_field( $_GET['api_key'] ) : null;
		}

		return $key;
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
	 * Get applicable rate limit rule.
	 *
	 * @param string $endpoint        Endpoint.
	 * @param string $identifier_type Identifier type.
	 * @return object|null
	 */
	private function get_applicable_rule( $endpoint, $identifier_type ) {
		global $wpdb;

		$user_role = null;
		if ( is_user_logged_in() ) {
			$user      = wp_get_current_user();
			$user_role = $user->roles[0] ?? null;
		}

		// Get matching rules.
		$rules = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['rules']}
            WHERE is_active = 1
            AND (identifier_type = %s OR identifier_type = 'role')
            ORDER BY priority DESC, id ASC",
				$identifier_type
			)
		);

		foreach ( $rules as $rule ) {
			// Check role match.
			if ( $rule->identifier_type === 'role' && $rule->role !== $user_role ) {
				continue;
			}

			// Check endpoint pattern.
			if ( $rule->endpoint_pattern !== '*' ) {
				$pattern = str_replace( array( '*', '/' ), array( '.*', '\/' ), $rule->endpoint_pattern );
				if ( ! preg_match( '/^' . $pattern . '$/', $endpoint ) ) {
					continue;
				}
			}

			return $rule;
		}

		return null;
	}

	/**
	 * Get limits from rule or defaults.
	 *
	 * @param object|null $rule Rate limit rule.
	 * @return array
	 */
	private function get_limits( $rule ) {
		if ( ! $rule ) {
			return $this->default_limits;
		}

		return array(
			'requests_per_minute' => $rule->requests_per_minute ?? $this->default_limits['requests_per_minute'],
			'requests_per_hour'   => $rule->requests_per_hour ?? $this->default_limits['requests_per_hour'],
			'requests_per_day'    => $rule->requests_per_day ?? $this->default_limits['requests_per_day'],
		);
	}

	/**
	 * Get request count for identifier and window.
	 *
	 * @param string $identifier  Identifier.
	 * @param string $endpoint    Endpoint.
	 * @param string $window_type Window type.
	 * @return int
	 */
	private function get_request_count( $identifier, $endpoint, $window_type ) {
		global $wpdb;

		$window_start = $this->get_window_start( $window_type );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT request_count FROM {$this->tables['rate_limits']}
            WHERE identifier = %s AND endpoint = %s AND window_type = %s AND window_start = %s",
				$identifier,
				$endpoint,
				$window_type,
				$window_start
			)
		);

		return intval( $count );
	}

	/**
	 * Increment rate limit counters.
	 *
	 * @param string $identifier Identifier.
	 * @param string $endpoint   Endpoint.
	 */
	private function increment_counters( $identifier, $endpoint ) {
		global $wpdb;

		foreach ( array( 'minute', 'hour', 'day' ) as $window_type ) {
			$window_start = $this->get_window_start( $window_type );

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$this->tables['rate_limits']}
                (identifier, endpoint, window_type, window_start, request_count)
                VALUES (%s, %s, %s, %s, 1)
                ON DUPLICATE KEY UPDATE request_count = request_count + 1",
					$identifier,
					$endpoint,
					$window_type,
					$window_start
				)
			);
		}
	}

	/**
	 * Get window start time.
	 *
	 * @param string $window_type Window type.
	 * @return string
	 */
	private function get_window_start( $window_type ) {
		$time = time();

		switch ( $window_type ) {
			case 'minute':
				return date( 'Y-m-d H:i:00', $time );
			case 'hour':
				return date( 'Y-m-d H:00:00', $time );
			case 'day':
				return date( 'Y-m-d 00:00:00', $time );
			default:
				return date( 'Y-m-d H:i:00', $time );
		}
	}

	/**
	 * Get retry after seconds.
	 *
	 * @param string $window_type Window type.
	 * @return int
	 */
	private function get_retry_after( $window_type ) {
		$time = time();

		switch ( $window_type ) {
			case 'minute':
				return 60 - ( $time % 60 );
			case 'hour':
				return 3600 - ( $time % 3600 );
			case 'day':
				return 86400 - ( $time % 86400 );
			default:
				return 60;
		}
	}

	/**
	 * Check if identifier is whitelisted.
	 *
	 * @param string $identifier      Identifier.
	 * @param string $identifier_type Identifier type.
	 * @return bool
	 */
	private function is_whitelisted( $identifier, $identifier_type ) {
		global $wpdb;

		$raw_identifier = str_replace( array( 'ip:', 'user:', 'key:' ), '', $identifier );

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['whitelist']}
            WHERE identifier = %s AND identifier_type = %s AND list_type = 'whitelist'
            AND (expires_at IS NULL OR expires_at > NOW())",
				$raw_identifier,
				$identifier_type
			)
		);

		return $entry !== null;
	}

	/**
	 * Check if identifier is blacklisted.
	 *
	 * @param string $identifier      Identifier.
	 * @param string $identifier_type Identifier type.
	 * @return bool
	 */
	private function is_blacklisted( $identifier, $identifier_type ) {
		global $wpdb;

		$raw_identifier = str_replace( array( 'ip:', 'user:', 'key:' ), '', $identifier );

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['whitelist']}
            WHERE identifier = %s AND identifier_type = %s AND list_type = 'blacklist'
            AND (expires_at IS NULL OR expires_at > NOW())",
				$raw_identifier,
				$identifier_type
			)
		);

		return $entry !== null;
	}

	/**
	 * Log API request.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function log_request( $request ) {
		global $wpdb;

		// Only log if enabled.
		if ( ! get_option( 'ict_rate_limit_logging', true ) ) {
			return;
		}

		$wpdb->insert(
			$this->tables['requests'],
			array(
				'user_id'    => is_user_logged_in() ? get_current_user_id() : null,
				'ip_address' => $this->get_client_ip(),
				'api_key'    => $this->get_api_key(),
				'endpoint'   => $request->get_route(),
				'method'     => $request->get_method(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : null,
			)
		);
	}

	/**
	 * Get rate limit status.
	 */
	public function rest_get_status( $request ) {
		$identifier      = $this->get_identifier();
		$identifier_type = $this->get_identifier_type();
		$rule            = $this->get_applicable_rule( '/ict/v1/', $identifier_type );
		$limits          = $this->get_limits( $rule );

		$minute_count = $this->get_request_count( $identifier, '/ict/v1/', 'minute' );
		$hour_count   = $this->get_request_count( $identifier, '/ict/v1/', 'hour' );
		$day_count    = $this->get_request_count( $identifier, '/ict/v1/', 'day' );

		return rest_ensure_response(
			array(
				'success' => true,
				'limits'  => array(
					'minute' => array(
						'limit'     => $limits['requests_per_minute'],
						'used'      => $minute_count,
						'remaining' => max( 0, $limits['requests_per_minute'] - $minute_count ),
						'reset'     => time() + ( 60 - ( time() % 60 ) ),
					),
					'hour'   => array(
						'limit'     => $limits['requests_per_hour'],
						'used'      => $hour_count,
						'remaining' => max( 0, $limits['requests_per_hour'] - $hour_count ),
						'reset'     => time() + ( 3600 - ( time() % 3600 ) ),
					),
					'day'    => array(
						'limit'     => $limits['requests_per_day'],
						'used'      => $day_count,
						'remaining' => max( 0, $limits['requests_per_day'] - $day_count ),
						'reset'     => time() + ( 86400 - ( time() % 86400 ) ),
					),
				),
			)
		);
	}

	/**
	 * Get rules.
	 */
	public function rest_get_rules( $request ) {
		global $wpdb;

		$rules = $wpdb->get_results(
			"SELECT * FROM {$this->tables['rules']} ORDER BY priority DESC, name ASC"
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'rules'   => $rules,
			)
		);
	}

	/**
	 * Create rule.
	 */
	public function rest_create_rule( $request ) {
		global $wpdb;

		$wpdb->insert(
			$this->tables['rules'],
			array(
				'name'                => sanitize_text_field( $request->get_param( 'name' ) ),
				'endpoint_pattern'    => sanitize_text_field( $request->get_param( 'endpoint_pattern' ) ) ?: '*',
				'identifier_type'     => sanitize_text_field( $request->get_param( 'identifier_type' ) ) ?: 'ip',
				'role'                => sanitize_text_field( $request->get_param( 'role' ) ),
				'requests_per_minute' => intval( $request->get_param( 'requests_per_minute' ) ),
				'requests_per_hour'   => intval( $request->get_param( 'requests_per_hour' ) ),
				'requests_per_day'    => intval( $request->get_param( 'requests_per_day' ) ),
				'burst_limit'         => intval( $request->get_param( 'burst_limit' ) ),
				'priority'            => intval( $request->get_param( 'priority' ) ),
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'rule_id' => $wpdb->insert_id,
				'message' => 'Rule created successfully',
			)
		);
	}

	/**
	 * Update rule.
	 */
	public function rest_update_rule( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$fields = array(
			'name',
			'endpoint_pattern',
			'identifier_type',
			'role',
			'requests_per_minute',
			'requests_per_hour',
			'requests_per_day',
			'burst_limit',
			'priority',
			'is_active',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->tables['rules'], $data, array( 'id' => $id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Rule updated successfully',
			)
		);
	}

	/**
	 * Delete rule.
	 */
	public function rest_delete_rule( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->tables['rules'], array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Rule deleted successfully',
			)
		);
	}

	/**
	 * Get whitelist/blacklist.
	 */
	public function rest_get_list( $request ) {
		global $wpdb;

		$list_type = $request->get_param( 'type' );

		$sql  = "SELECT * FROM {$this->tables['whitelist']} WHERE 1=1";
		$args = array();

		if ( $list_type ) {
			$sql   .= ' AND list_type = %s';
			$args[] = $list_type;
		}

		$sql .= ' ORDER BY created_at DESC';

		$list = ! empty( $args )
			? $wpdb->get_results( $wpdb->prepare( $sql, $args ) )
			: $wpdb->get_results( $sql );

		return rest_ensure_response(
			array(
				'success' => true,
				'list'    => $list,
			)
		);
	}

	/**
	 * Add to list.
	 */
	public function rest_add_to_list( $request ) {
		global $wpdb;

		$wpdb->insert(
			$this->tables['whitelist'],
			array(
				'identifier'      => sanitize_text_field( $request->get_param( 'identifier' ) ),
				'identifier_type' => sanitize_text_field( $request->get_param( 'identifier_type' ) ) ?: 'ip',
				'list_type'       => sanitize_text_field( $request->get_param( 'list_type' ) ) ?: 'whitelist',
				'reason'          => sanitize_textarea_field( $request->get_param( 'reason' ) ),
				'expires_at'      => sanitize_text_field( $request->get_param( 'expires_at' ) ),
				'created_by'      => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'entry_id' => $wpdb->insert_id,
				'message'  => 'Entry added successfully',
			)
		);
	}

	/**
	 * Remove from list.
	 */
	public function rest_remove_from_list( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->tables['whitelist'], array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Entry removed successfully',
			)
		);
	}

	/**
	 * Get analytics.
	 */
	public function rest_get_analytics( $request ) {
		global $wpdb;

		$days = intval( $request->get_param( 'days' ) ) ?: 7;

		$stats = array(
			'total_requests' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->tables['requests']} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
					$days
				)
			),
			'unique_users'   => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id) FROM {$this->tables['requests']} WHERE user_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
					$days
				)
			),
			'unique_ips'     => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT ip_address) FROM {$this->tables['requests']} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
					$days
				)
			),
		);

		// Top endpoints.
		$stats['top_endpoints'] = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT endpoint, COUNT(*) as count
            FROM {$this->tables['requests']}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY endpoint
            ORDER BY count DESC
            LIMIT 10",
				$days
			)
		);

		// Top users.
		$stats['top_users'] = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) as count
            FROM {$this->tables['requests']}
            WHERE user_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY user_id
            ORDER BY count DESC
            LIMIT 10",
				$days
			)
		);

		// Requests by hour.
		$stats['by_hour'] = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as hour, COUNT(*) as count
            FROM {$this->tables['requests']}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY hour
            ORDER BY hour",
				$days
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/**
	 * Reset limits.
	 */
	public function rest_reset_limits( $request ) {
		global $wpdb;

		$identifier = sanitize_text_field( $request->get_param( 'identifier' ) );

		if ( $identifier ) {
			$wpdb->delete( $this->tables['rate_limits'], array( 'identifier' => $identifier ) );
		} else {
			$wpdb->query( "TRUNCATE TABLE {$this->tables['rate_limits']}" );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Rate limits reset successfully',
			)
		);
	}

	/**
	 * Cleanup old data.
	 */
	public function cleanup_old_data() {
		global $wpdb;

		// Remove old rate limit records.
		$wpdb->query(
			"DELETE FROM {$this->tables['rate_limits']} WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)"
		);

		// Remove old request logs (keep 30 days).
		$retention_days = get_option( 'ict_rate_limit_log_retention', 30 );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->tables['requests']} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);

		// Remove expired whitelist/blacklist entries.
		$wpdb->query(
			"DELETE FROM {$this->tables['whitelist']} WHERE expires_at IS NOT NULL AND expires_at < NOW()"
		);
	}
}
