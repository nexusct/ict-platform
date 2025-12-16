<?php
/**
 * Microsoft Teams Integration Adapter
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Microsoft_Teams_Adapter
 *
 * Handles Microsoft Teams integration for notifications, channel messaging, and activity feeds.
 */
class ICT_Microsoft_Teams_Adapter {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Microsoft_Teams_Adapter
	 */
	private static $instance = null;

	/**
	 * Microsoft Graph API base URL.
	 *
	 * @var string
	 */
	private $graph_api_base = 'https://graph.microsoft.com/v1.0';

	/**
	 * OAuth base URL.
	 *
	 * @var string
	 */
	private $oauth_base_url = 'https://login.microsoftonline.com';

	/**
	 * Webhook URLs for incoming webhooks.
	 *
	 * @var array
	 */
	private $webhooks = array();

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Microsoft_Teams_Adapter
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
		$this->load_webhooks();
	}

	/**
	 * Initialize Teams integration.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function init() {
		// Add any initialization hooks here
		add_action( 'ict_project_status_changed', array( $this, 'notify_project_update' ), 10, 3 );
		add_action( 'ict_low_stock_detected', array( $this, 'notify_low_stock' ), 10, 1 );
	}

	/**
	 * Load configured webhooks from options.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function load_webhooks() {
		$this->webhooks = get_option( 'ict_teams_webhooks', array() );
	}

	/**
	 * Test connection to Microsoft Teams.
	 *
	 * @since  1.1.0
	 * @return array Test result.
	 */
	public function test_connection() {
		$access_token = $this->get_access_token();

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Not authenticated with Microsoft Teams', 'ict-platform' ),
			);
		}

		$response = wp_remote_get(
			$this->graph_api_base . '/me',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			return array(
				'success' => true,
				'message' => __( 'Successfully connected to Microsoft Teams', 'ict-platform' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf( __( 'Connection failed with status code %d', 'ict-platform' ), $status_code ),
		);
	}

	/**
	 * Get access token, refreshing if necessary.
	 *
	 * @since  1.1.0
	 * @return string|false Access token or false.
	 */
	private function get_access_token() {
		$access_token  = get_option( 'ict_teams_access_token' );
		$token_expires = get_option( 'ict_teams_token_expires', 0 );

		if ( ! empty( $access_token ) && time() < $token_expires ) {
			return ICT_Admin_Settings::decrypt( $access_token );
		}

		// Try to refresh token
		if ( $this->refresh_access_token() ) {
			return ICT_Admin_Settings::decrypt( get_option( 'ict_teams_access_token' ) );
		}

		return false;
	}

	/**
	 * Refresh access token.
	 *
	 * @since  1.1.0
	 * @return bool True on success.
	 */
	private function refresh_access_token() {
		$refresh_token = get_option( 'ict_teams_refresh_token' );
		$client_id     = get_option( 'ict_teams_client_id' );
		$client_secret = ICT_Admin_Settings::decrypt( get_option( 'ict_teams_client_secret' ) );
		$tenant_id     = get_option( 'ict_teams_tenant_id', 'common' );

		if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$response = wp_remote_post(
			"{$this->oauth_base_url}/{$tenant_id}/oauth2/v2.0/token",
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => ICT_Admin_Settings::decrypt( $refresh_token ),
					'grant_type'    => 'refresh_token',
					'scope'         => $this->get_scope(),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$this->store_tokens( $body );
			return true;
		}

		return false;
	}

	/**
	 * Get OAuth scope.
	 *
	 * @since  1.1.0
	 * @return string OAuth scope.
	 */
	public function get_scope() {
		return 'https://graph.microsoft.com/.default offline_access';
	}

	/**
	 * Get authorization URL.
	 *
	 * @since  1.1.0
	 * @param  string $redirect_uri Redirect URI.
	 * @return string Authorization URL.
	 */
	public function get_authorization_url( $redirect_uri ) {
		$client_id = get_option( 'ict_teams_client_id' );
		$tenant_id = get_option( 'ict_teams_tenant_id', 'common' );

		if ( empty( $client_id ) ) {
			return '';
		}

		$params = array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'redirect_uri'  => $redirect_uri,
			'response_mode' => 'query',
			'scope'         => 'https://graph.microsoft.com/ChannelMessage.Send https://graph.microsoft.com/Team.ReadBasic.All https://graph.microsoft.com/User.Read offline_access',
			'state'         => wp_create_nonce( 'teams_oauth' ),
		);

		return "{$this->oauth_base_url}/{$tenant_id}/oauth2/v2.0/authorize?" . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @since  1.1.0
	 * @param  string $code         Authorization code.
	 * @param  string $redirect_uri Redirect URI.
	 * @return bool True on success.
	 */
	public function exchange_code_for_tokens( $code, $redirect_uri ) {
		$client_id     = get_option( 'ict_teams_client_id' );
		$client_secret = ICT_Admin_Settings::decrypt( get_option( 'ict_teams_client_secret' ) );
		$tenant_id     = get_option( 'ict_teams_tenant_id', 'common' );

		$response = wp_remote_post(
			"{$this->oauth_base_url}/{$tenant_id}/oauth2/v2.0/token",
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
					'scope'         => 'https://graph.microsoft.com/ChannelMessage.Send https://graph.microsoft.com/Team.ReadBasic.All https://graph.microsoft.com/User.Read offline_access',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$this->store_tokens( $body );
			return true;
		}

		return false;
	}

	/**
	 * Store OAuth tokens.
	 *
	 * @since  1.1.0
	 * @param  array $tokens Token data from OAuth response.
	 * @return void
	 */
	private function store_tokens( $tokens ) {
		$settings = new ICT_Admin_Settings();

		update_option( 'ict_teams_access_token', $settings->sanitize_encrypted( $tokens['access_token'] ) );

		if ( isset( $tokens['refresh_token'] ) ) {
			update_option( 'ict_teams_refresh_token', $settings->sanitize_encrypted( $tokens['refresh_token'] ) );
		}

		$expires_in = isset( $tokens['expires_in'] ) ? (int) $tokens['expires_in'] : 3600;
		update_option( 'ict_teams_token_expires', time() + $expires_in - 300 ); // 5 min buffer
	}

	/**
	 * Send message to Teams channel via webhook.
	 *
	 * @since  1.1.0
	 * @param  string $webhook_url Webhook URL.
	 * @param  string $message     Message text.
	 * @param  array  $card        Optional adaptive card data.
	 * @return bool True on success.
	 */
	public function send_webhook_message( $webhook_url, $message, $card = null ) {
		$payload = array(
			'@type'      => 'MessageCard',
			'@context'   => 'http://schema.org/extensions',
			'themeColor' => '0076D7',
			'summary'    => wp_strip_all_tags( $message ),
			'sections'   => array(
				array(
					'activityTitle' => __( 'ICT Platform Notification', 'ict-platform' ),
					'text'          => $message,
					'markdown'      => true,
				),
			),
		);

		if ( ! empty( $card ) ) {
			$payload = array_merge( $payload, $card );
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			ICT_Helper::log_sync( array(
				'entity_type'   => 'teams_notification',
				'direction'     => 'outbound',
				'zoho_service'  => 'teams',
				'action'        => 'send_message',
				'status'        => 'error',
				'error_message' => $response->get_error_message(),
			) );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		ICT_Helper::log_sync( array(
			'entity_type'  => 'teams_notification',
			'direction'    => 'outbound',
			'zoho_service' => 'teams',
			'action'       => 'send_message',
			'status'       => ( $status_code >= 200 && $status_code < 300 ) ? 'success' : 'error',
		) );

		return $status_code >= 200 && $status_code < 300;
	}

	/**
	 * Send message to Teams channel via Graph API.
	 *
	 * @since  1.1.0
	 * @param  string $team_id    Team ID.
	 * @param  string $channel_id Channel ID.
	 * @param  string $message    Message content.
	 * @return array|WP_Error Response or error.
	 */
	public function send_channel_message( $team_id, $channel_id, $message ) {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return new WP_Error( 'not_authenticated', __( 'Not authenticated with Microsoft Teams', 'ict-platform' ) );
		}

		$response = wp_remote_post(
			"{$this->graph_api_base}/teams/{$team_id}/channels/{$channel_id}/messages",
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'body' => array(
						'content' => $message,
					),
				) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Get list of teams.
	 *
	 * @since  1.1.0
	 * @return array|WP_Error Teams list or error.
	 */
	public function get_teams() {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return new WP_Error( 'not_authenticated', __( 'Not authenticated with Microsoft Teams', 'ict-platform' ) );
		}

		$response = wp_remote_get(
			"{$this->graph_api_base}/me/joinedTeams",
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['value'] ) ? $body['value'] : array();
	}

	/**
	 * Get channels for a team.
	 *
	 * @since  1.1.0
	 * @param  string $team_id Team ID.
	 * @return array|WP_Error Channels list or error.
	 */
	public function get_channels( $team_id ) {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return new WP_Error( 'not_authenticated', __( 'Not authenticated with Microsoft Teams', 'ict-platform' ) );
		}

		$response = wp_remote_get(
			"{$this->graph_api_base}/teams/{$team_id}/channels",
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['value'] ) ? $body['value'] : array();
	}

	/**
	 * Send project update notification to Teams.
	 *
	 * @since  1.1.0
	 * @param  array $project Project data.
	 * @param  string $action  Action type (created, updated, completed).
	 * @return bool True on success.
	 */
	public function notify_project_update( $project, $action = 'updated' ) {
		$webhook_url = get_option( 'ict_teams_project_webhook' );

		if ( empty( $webhook_url ) ) {
			return false;
		}

		$action_text = array(
			'created'   => __( 'New Project Created', 'ict-platform' ),
			'updated'   => __( 'Project Updated', 'ict-platform' ),
			'completed' => __( 'Project Completed', 'ict-platform' ),
		);

		$card = array(
			'@type'      => 'MessageCard',
			'@context'   => 'http://schema.org/extensions',
			'themeColor' => $action === 'completed' ? '00FF00' : '0076D7',
			'summary'    => $action_text[ $action ] ?? __( 'Project Update', 'ict-platform' ),
			'sections'   => array(
				array(
					'activityTitle'    => $action_text[ $action ] ?? __( 'Project Update', 'ict-platform' ),
					'activitySubtitle' => $project['project_name'] ?? '',
					'facts'            => array(
						array(
							'name'  => __( 'Project Number', 'ict-platform' ),
							'value' => $project['project_number'] ?? 'N/A',
						),
						array(
							'name'  => __( 'Status', 'ict-platform' ),
							'value' => ucfirst( $project['status'] ?? 'pending' ),
						),
						array(
							'name'  => __( 'Progress', 'ict-platform' ),
							'value' => ( $project['progress_percentage'] ?? 0 ) . '%',
						),
					),
					'markdown' => true,
				),
			),
		);

		return $this->send_webhook_message( $webhook_url, '', $card );
	}

	/**
	 * Send time entry notification to Teams.
	 *
	 * @since  1.1.0
	 * @param  array  $time_entry Time entry data.
	 * @param  string $action     Action type (clock_in, clock_out, approved).
	 * @return bool True on success.
	 */
	public function notify_time_entry( $time_entry, $action = 'clock_in' ) {
		$webhook_url = get_option( 'ict_teams_timetracking_webhook' );

		if ( empty( $webhook_url ) ) {
			return false;
		}

		$technician_name = ICT_Helper::get_user_display_name( $time_entry['technician_id'] ?? 0 );

		$action_text = array(
			'clock_in'  => sprintf( __( '%s clocked in', 'ict-platform' ), $technician_name ),
			'clock_out' => sprintf( __( '%s clocked out', 'ict-platform' ), $technician_name ),
			'approved'  => sprintf( __( 'Time entry approved for %s', 'ict-platform' ), $technician_name ),
		);

		$message = $action_text[ $action ] ?? __( 'Time Entry Update', 'ict-platform' );

		if ( ! empty( $time_entry['total_hours'] ) ) {
			$message .= sprintf( ' - %s hours', number_format( $time_entry['total_hours'], 2 ) );
		}

		return $this->send_webhook_message( $webhook_url, $message );
	}

	/**
	 * Send low stock alert to Teams.
	 *
	 * @since  1.1.0
	 * @param  array $items Low stock items.
	 * @return bool True on success.
	 */
	public function notify_low_stock( $items ) {
		$webhook_url = get_option( 'ict_teams_inventory_webhook' );

		if ( empty( $webhook_url ) || empty( $items ) ) {
			return false;
		}

		$facts = array();
		foreach ( array_slice( $items, 0, 10 ) as $item ) {
			$facts[] = array(
				'name'  => $item->item_name,
				'value' => sprintf(
					__( '%d available (reorder at %d)', 'ict-platform' ),
					$item->quantity_available,
					$item->reorder_level
				),
			);
		}

		$card = array(
			'@type'      => 'MessageCard',
			'@context'   => 'http://schema.org/extensions',
			'themeColor' => 'FF0000',
			'summary'    => __( 'Low Stock Alert', 'ict-platform' ),
			'sections'   => array(
				array(
					'activityTitle' => __( '⚠️ Low Stock Alert', 'ict-platform' ),
					'activitySubtitle' => sprintf(
						__( '%d items below reorder level', 'ict-platform' ),
						count( $items )
					),
					'facts'    => $facts,
					'markdown' => true,
				),
			),
		);

		return $this->send_webhook_message( $webhook_url, '', $card );
	}

	/**
	 * Add or update webhook.
	 *
	 * @since  1.1.0
	 * @param  string $key         Webhook key.
	 * @param  string $webhook_url Webhook URL.
	 * @return bool True on success.
	 */
	public function set_webhook( $key, $webhook_url ) {
		$this->webhooks[ $key ] = esc_url_raw( $webhook_url );
		return update_option( 'ict_teams_webhooks', $this->webhooks );
	}

	/**
	 * Get webhook by key.
	 *
	 * @since  1.1.0
	 * @param  string $key Webhook key.
	 * @return string|null Webhook URL or null.
	 */
	public function get_webhook( $key ) {
		return $this->webhooks[ $key ] ?? null;
	}

	/**
	 * Disconnect Microsoft Teams integration.
	 *
	 * @since  1.1.0
	 * @return bool True on success.
	 */
	public function disconnect() {
		delete_option( 'ict_teams_access_token' );
		delete_option( 'ict_teams_refresh_token' );
		delete_option( 'ict_teams_token_expires' );
		delete_option( 'ict_teams_webhooks' );

		return true;
	}

	/**
	 * Check if Teams is connected.
	 *
	 * @since  1.1.0
	 * @return bool True if connected.
	 */
	public function is_connected() {
		$access_token = get_option( 'ict_teams_access_token' );
		return ! empty( $access_token );
	}

	/**
	 * Create adaptive card for rich notifications.
	 *
	 * @since  1.1.0
	 * @param  string $title       Card title.
	 * @param  string $description Card description.
	 * @param  array  $facts       Key-value facts.
	 * @param  array  $actions     Action buttons.
	 * @return array Adaptive card structure.
	 */
	public function create_adaptive_card( $title, $description, $facts = array(), $actions = array() ) {
		$card = array(
			'@type'      => 'MessageCard',
			'@context'   => 'http://schema.org/extensions',
			'themeColor' => '0076D7',
			'summary'    => $title,
			'sections'   => array(
				array(
					'activityTitle' => $title,
					'text'          => $description,
					'markdown'      => true,
				),
			),
		);

		if ( ! empty( $facts ) ) {
			$card['sections'][0]['facts'] = array();
			foreach ( $facts as $name => $value ) {
				$card['sections'][0]['facts'][] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}

		if ( ! empty( $actions ) ) {
			$card['potentialAction'] = array();
			foreach ( $actions as $action ) {
				$card['potentialAction'][] = array(
					'@type'   => 'OpenUri',
					'name'    => $action['name'],
					'targets' => array(
						array(
							'os'  => 'default',
							'uri' => $action['url'],
						),
					),
				);
			}
		}

		return $card;
	}
}
