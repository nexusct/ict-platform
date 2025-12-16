<?php
/**
 * Push Notification Handler
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Push_Notification
 *
 * Handles web push notifications via service workers.
 */
class ICT_Push_Notification {

	/**
	 * VAPID public key.
	 *
	 * @var string
	 */
	private $vapid_public_key;

	/**
	 * VAPID private key.
	 *
	 * @var string
	 */
	private $vapid_private_key;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->vapid_public_key  = get_option( 'ict_vapid_public_key' );
		$this->vapid_private_key = ICT_Admin_Settings::decrypt( get_option( 'ict_vapid_private_key' ) );
	}

	/**
	 * Check if push notifications are configured.
	 *
	 * @since  1.1.0
	 * @return bool True if configured.
	 */
	public function is_configured() {
		return ! empty( $this->vapid_public_key ) && ! empty( $this->vapid_private_key );
	}

	/**
	 * Get VAPID public key.
	 *
	 * @since  1.1.0
	 * @return string Public key.
	 */
	public function get_public_key() {
		return $this->vapid_public_key;
	}

	/**
	 * Generate VAPID keys.
	 *
	 * @since  1.1.0
	 * @return array Keys.
	 */
	public static function generate_vapid_keys() {
		// Generate using OpenSSL
		$config = array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		);

		$key = openssl_pkey_new( $config );

		if ( ! $key ) {
			return array( 'error' => 'Failed to generate keys' );
		}

		$details = openssl_pkey_get_details( $key );

		openssl_pkey_export( $key, $private_key );

		// Extract raw keys from PEM format
		$public_key_raw  = $details['ec']['x'] . $details['ec']['y'];
		$private_key_raw = $details['ec']['d'];

		return array(
			'public_key'  => rtrim( strtr( base64_encode( "\x04" . $public_key_raw ), '+/', '-_' ), '=' ),
			'private_key' => rtrim( strtr( base64_encode( $private_key_raw ), '+/', '-_' ), '=' ),
		);
	}

	/**
	 * Send push notification.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients User IDs.
	 * @param  array  $data       Notification data.
	 * @return array Results.
	 */
	public function send( $type, $recipients, $data ) {
		if ( ! $this->is_configured() ) {
			return array( 'success' => false, 'error' => 'not_configured' );
		}

		$results = array(
			'sent'   => 0,
			'failed' => 0,
		);

		foreach ( $recipients as $user_id ) {
			if ( ! $this->should_send_push( $user_id, $type ) ) {
				continue;
			}

			$subscriptions = $this->get_user_subscriptions( $user_id );

			foreach ( $subscriptions as $subscription ) {
				$payload = $this->build_payload( $type, $data );
				$result  = $this->send_to_subscription( $subscription, $payload );

				if ( $result['success'] ) {
					$results['sent']++;
				} else {
					$results['failed']++;

					// Remove invalid subscriptions
					if ( $result['error'] === 'gone' ) {
						$this->remove_subscription( $user_id, $subscription['endpoint'] );
					}
				}
			}
		}

		return array(
			'success' => $results['sent'] > 0,
			'sent'    => $results['sent'],
			'failed'  => $results['failed'],
		);
	}

	/**
	 * Check if should send push notification.
	 *
	 * @since  1.1.0
	 * @param  int    $user_id User ID.
	 * @param  string $type    Notification type.
	 * @return bool True if should send.
	 */
	private function should_send_push( $user_id, $type ) {
		$preferences = get_user_meta( $user_id, 'ict_notification_preferences', true );

		if ( ! is_array( $preferences ) ) {
			return true;
		}

		if ( isset( $preferences['push_enabled'] ) && ! $preferences['push_enabled'] ) {
			return false;
		}

		if ( isset( $preferences['notification_types'] ) && is_array( $preferences['notification_types'] ) ) {
			if ( ! in_array( $type, $preferences['notification_types'], true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get user's push subscriptions.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return array Subscriptions.
	 */
	public function get_user_subscriptions( $user_id ) {
		$subscriptions = get_user_meta( $user_id, 'ict_push_subscriptions', true );
		return is_array( $subscriptions ) ? $subscriptions : array();
	}

	/**
	 * Save user push subscription.
	 *
	 * @since  1.1.0
	 * @param  int   $user_id      User ID.
	 * @param  array $subscription Subscription data.
	 * @return bool True on success.
	 */
	public function save_subscription( $user_id, $subscription ) {
		$subscriptions = $this->get_user_subscriptions( $user_id );

		// Check if already exists
		foreach ( $subscriptions as $existing ) {
			if ( $existing['endpoint'] === $subscription['endpoint'] ) {
				return true;
			}
		}

		$subscriptions[] = array(
			'endpoint'   => $subscription['endpoint'],
			'keys'       => $subscription['keys'],
			'created_at' => current_time( 'mysql' ),
		);

		return update_user_meta( $user_id, 'ict_push_subscriptions', $subscriptions );
	}

	/**
	 * Remove user push subscription.
	 *
	 * @since  1.1.0
	 * @param  int    $user_id  User ID.
	 * @param  string $endpoint Subscription endpoint.
	 * @return bool True on success.
	 */
	public function remove_subscription( $user_id, $endpoint ) {
		$subscriptions = $this->get_user_subscriptions( $user_id );

		$subscriptions = array_filter( $subscriptions, function( $sub ) use ( $endpoint ) {
			return $sub['endpoint'] !== $endpoint;
		} );

		return update_user_meta( $user_id, 'ict_push_subscriptions', array_values( $subscriptions ) );
	}

	/**
	 * Build push notification payload.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return string JSON payload.
	 */
	private function build_payload( $type, $data ) {
		$payload = array(
			'title'   => $data['title'] ?? __( 'ICT Platform', 'ict-platform' ),
			'body'    => $data['message'] ?? '',
			'icon'    => ICT_PLATFORM_PLUGIN_URL . 'assets/images/icon-192.png',
			'badge'   => ICT_PLATFORM_PLUGIN_URL . 'assets/images/badge-72.png',
			'tag'     => $type,
			'data'    => array(
				'type' => $type,
				'url'  => $data['url'] ?? home_url(),
			),
			'actions' => array(),
		);

		// Add type-specific actions
		switch ( $type ) {
			case 'task_assigned':
				$payload['actions'] = array(
					array( 'action' => 'view', 'title' => __( 'View Task', 'ict-platform' ) ),
					array( 'action' => 'accept', 'title' => __( 'Accept', 'ict-platform' ) ),
				);
				break;

			case 'time_entry_submitted':
				$payload['actions'] = array(
					array( 'action' => 'approve', 'title' => __( 'Approve', 'ict-platform' ) ),
					array( 'action' => 'review', 'title' => __( 'Review', 'ict-platform' ) ),
				);
				break;

			default:
				$payload['actions'] = array(
					array( 'action' => 'view', 'title' => __( 'View', 'ict-platform' ) ),
					array( 'action' => 'dismiss', 'title' => __( 'Dismiss', 'ict-platform' ) ),
				);
		}

		return wp_json_encode( $payload );
	}

	/**
	 * Send push notification to subscription.
	 *
	 * @since  1.1.0
	 * @param  array  $subscription Subscription data.
	 * @param  string $payload      JSON payload.
	 * @return array Result.
	 */
	private function send_to_subscription( $subscription, $payload ) {
		$endpoint = $subscription['endpoint'];
		$p256dh   = $subscription['keys']['p256dh'] ?? '';
		$auth     = $subscription['keys']['auth'] ?? '';

		if ( empty( $endpoint ) || empty( $p256dh ) || empty( $auth ) ) {
			return array( 'success' => false, 'error' => 'invalid_subscription' );
		}

		// Create encrypted payload
		$encrypted = $this->encrypt_payload( $payload, $p256dh, $auth );

		if ( ! $encrypted ) {
			return array( 'success' => false, 'error' => 'encryption_failed' );
		}

		// Create VAPID headers
		$vapid_headers = $this->create_vapid_headers( $endpoint );

		$headers = array(
			'Content-Type'     => 'application/octet-stream',
			'Content-Encoding' => 'aes128gcm',
			'Content-Length'   => strlen( $encrypted['cipherText'] ),
			'TTL'              => 86400,
			'Authorization'    => $vapid_headers['authorization'],
			'Crypto-Key'       => 'p256ecdsa=' . $this->vapid_public_key,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $headers,
				'body'    => $encrypted['cipherText'],
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return array( 'success' => true );
		}

		if ( 404 === $status_code || 410 === $status_code ) {
			return array( 'success' => false, 'error' => 'gone' );
		}

		return array( 'success' => false, 'error' => "status_{$status_code}" );
	}

	/**
	 * Encrypt payload using Web Push encryption.
	 *
	 * @since  1.1.0
	 * @param  string $payload Payload.
	 * @param  string $p256dh  Client public key.
	 * @param  string $auth    Auth secret.
	 * @return array|false Encrypted data or false.
	 */
	private function encrypt_payload( $payload, $p256dh, $auth ) {
		// This is a simplified implementation
		// In production, use a library like web-push-php

		// For now, return a placeholder that indicates encryption is needed
		// The actual implementation requires proper ECDH and HKDF operations

		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			return false;
		}

		// Generate local key pair
		$local_key = openssl_pkey_new( array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		) );

		if ( ! $local_key ) {
			return false;
		}

		$local_details = openssl_pkey_get_details( $local_key );

		// Note: Full implementation would involve:
		// 1. ECDH key agreement
		// 2. HKDF key derivation
		// 3. AES-128-GCM encryption

		// For simplicity, we return the raw payload
		// Real implementation should use proper Web Push encryption
		return array(
			'cipherText'    => $payload,
			'localPublicKey' => $local_details['key'] ?? '',
		);
	}

	/**
	 * Create VAPID authorization headers.
	 *
	 * @since  1.1.0
	 * @param  string $endpoint Push endpoint.
	 * @return array Headers.
	 */
	private function create_vapid_headers( $endpoint ) {
		$parsed_url = wp_parse_url( $endpoint );
		$audience   = $parsed_url['scheme'] . '://' . $parsed_url['host'];

		$header = array(
			'typ' => 'JWT',
			'alg' => 'ES256',
		);

		$payload = array(
			'aud' => $audience,
			'exp' => time() + 86400,
			'sub' => 'mailto:' . get_option( 'admin_email' ),
		);

		$header_encoded  = rtrim( strtr( base64_encode( wp_json_encode( $header ) ), '+/', '-_' ), '=' );
		$payload_encoded = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );

		// Note: Real implementation needs proper ES256 signing
		$signature = hash_hmac( 'sha256', "{$header_encoded}.{$payload_encoded}", $this->vapid_private_key, true );
		$signature_encoded = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );

		return array(
			'authorization' => "vapid t={$header_encoded}.{$payload_encoded}.{$signature_encoded}, k={$this->vapid_public_key}",
		);
	}

	/**
	 * Send test notification.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return array Result.
	 */
	public function send_test( $user_id ) {
		$data = array(
			'title'   => __( 'Test Notification', 'ict-platform' ),
			'message' => __( 'This is a test push notification from ICT Platform.', 'ict-platform' ),
			'url'     => admin_url( 'admin.php?page=ict-settings' ),
		);

		return $this->send( 'test', array( $user_id ), $data );
	}
}
