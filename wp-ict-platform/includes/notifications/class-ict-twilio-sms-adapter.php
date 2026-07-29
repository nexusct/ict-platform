<?php
/**
 * Twilio SMS Adapter
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Twilio_SMS_Adapter
 *
 * Handles SMS notifications via Twilio API.
 */
class ICT_Twilio_SMS_Adapter {

	/**
	 * Twilio API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.twilio.com/2010-04-01';

	/**
	 * Account SID.
	 *
	 * @var string
	 */
	private $account_sid;

	/**
	 * Auth token.
	 *
	 * @var string
	 */
	private $auth_token;

	/**
	 * Sender phone number.
	 *
	 * @var string
	 */
	private $from_number;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->account_sid = get_option( 'ict_twilio_account_sid' );
		$this->auth_token  = ICT_Admin_Settings::decrypt( get_option( 'ict_twilio_auth_token' ) );
		$this->from_number = get_option( 'ict_twilio_from_number' );
	}

	/**
	 * Check if Twilio is configured.
	 *
	 * @since  1.1.0
	 * @return bool True if configured.
	 */
	public function is_configured() {
		return ! empty( $this->account_sid ) && ! empty( $this->auth_token ) && ! empty( $this->from_number );
	}

	/**
	 * Test connection to Twilio.
	 *
	 * @since  1.1.0
	 * @return array Test result.
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Twilio credentials are not configured', 'ict-platform' ),
			);
		}

		$response = wp_remote_get(
			"{$this->api_base}/Accounts/{$this->account_sid}.json",
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$this->account_sid}:{$this->auth_token}" ),
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
				'message' => __( 'Successfully connected to Twilio', 'ict-platform' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf( __( 'Connection failed with status code %d', 'ict-platform' ), $status_code ),
		);
	}

	/**
	 * Send SMS message.
	 *
	 * @since  1.1.0
	 * @param  string $to      Recipient phone number.
	 * @param  string $message Message text.
	 * @return array Result.
	 */
	public function send( $to, $message ) {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'error'   => 'not_configured',
			);
		}

		// Sanitize phone number
		$to = $this->sanitize_phone_number( $to );

		if ( empty( $to ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_phone_number',
			);
		}

		// Truncate message to SMS limit
		$message = $this->truncate_message( $message );

		$response = wp_remote_post(
			"{$this->api_base}/Accounts/{$this->account_sid}/Messages.json",
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$this->account_sid}:{$this->auth_token}" ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'From' => $this->from_number,
					'To'   => $to,
					'Body' => $message,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_sms( $to, $message, 'error', $response->get_error_message() );

			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$this->log_sms( $to, $message, 'success', null, $body['sid'] ?? null );

			return array(
				'success'    => true,
				'message_id' => $body['sid'] ?? null,
			);
		}

		$error = $body['message'] ?? 'Unknown error';
		$this->log_sms( $to, $message, 'error', $error );

		return array(
			'success' => false,
			'error'   => $error,
		);
	}

	/**
	 * Send notification to multiple recipients.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients User IDs or phone numbers.
	 * @param  array  $data       Notification data.
	 * @return array Results.
	 */
	public function send_notification( $type, $recipients, $data ) {
		$message = $this->format_notification_message( $type, $data );
		$phones  = $this->resolve_recipients( $recipients );

		$results = array(
			'sent'   => 0,
			'failed' => 0,
			'errors' => array(),
		);

		foreach ( $phones as $phone ) {
			// Check user preferences
			if ( ! $this->should_send_sms( $phone, $type ) ) {
				continue;
			}

			$result = $this->send( $phone, $message );

			if ( $result['success'] ) {
				++$results['sent'];
			} else {
				++$results['failed'];
				$results['errors'][] = $result['error'];
			}
		}

		return array(
			'success' => $results['sent'] > 0,
			'sent'    => $results['sent'],
			'failed'  => $results['failed'],
		);
	}

	/**
	 * Resolve recipients to phone numbers.
	 *
	 * @since  1.1.0
	 * @param  array $recipients User IDs or phone numbers.
	 * @return array Phone numbers.
	 */
	private function resolve_recipients( $recipients ) {
		$phones = array();

		foreach ( $recipients as $recipient ) {
			if ( is_numeric( $recipient ) && strlen( $recipient ) < 15 ) {
				// Likely a user ID
				$phone = get_user_meta( $recipient, 'phone', true );
				if ( empty( $phone ) ) {
					$phone = get_user_meta( $recipient, 'mobile', true );
				}
				if ( empty( $phone ) ) {
					$phone = get_user_meta( $recipient, 'ict_phone_number', true );
				}
				if ( $phone ) {
					$phones[] = $phone;
				}
			} else {
				// Phone number
				$phones[] = $recipient;
			}
		}

		return array_unique( array_filter( $phones ) );
	}

	/**
	 * Check if should send SMS to recipient.
	 *
	 * @since  1.1.0
	 * @param  string $phone Phone number.
	 * @param  string $type  Notification type.
	 * @return bool True if should send.
	 */
	private function should_send_sms( $phone, $type ) {
		// Find user by phone number
		$users = get_users(
			array(
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'   => 'phone',
						'value' => $phone,
					),
					array(
						'key'   => 'mobile',
						'value' => $phone,
					),
					array(
						'key'   => 'ict_phone_number',
						'value' => $phone,
					),
				),
				'number'     => 1,
			)
		);

		if ( empty( $users ) ) {
			return true; // External number, always send
		}

		$user        = $users[0];
		$preferences = get_user_meta( $user->ID, 'ict_notification_preferences', true );

		if ( ! is_array( $preferences ) ) {
			return true;
		}

		if ( isset( $preferences['sms_enabled'] ) && ! $preferences['sms_enabled'] ) {
			return false;
		}

		// Check quiet hours
		if ( isset( $preferences['quiet_hours']['enabled'] ) && $preferences['quiet_hours']['enabled'] ) {
			$current_time = current_time( 'H:i' );
			$start        = $preferences['quiet_hours']['start'] ?? '22:00';
			$end          = $preferences['quiet_hours']['end'] ?? '07:00';

			if ( $start < $end ) {
				if ( $current_time >= $start && $current_time < $end ) {
					return false;
				}
			} elseif ( $current_time >= $start || $current_time < $end ) {
					return false;
			}
		}

		return true;
	}

	/**
	 * Format notification message for SMS.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return string SMS message.
	 */
	private function format_notification_message( $type, $data ) {
		$site_name = get_bloginfo( 'name' );

		// Use provided message or generate from type
		if ( isset( $data['sms_message'] ) ) {
			$message = $data['sms_message'];
		} elseif ( isset( $data['message'] ) ) {
			$message = wp_strip_all_tags( $data['message'] );
		} else {
			$message = $this->get_default_message( $type, $data );
		}

		// Add site prefix
		$message = "[{$site_name}] {$message}";

		return $this->truncate_message( $message );
	}

	/**
	 * Get default message for notification type.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return string Message.
	 */
	private function get_default_message( $type, $data ) {
		switch ( $type ) {
			case 'project_completed':
				return sprintf(
					__( 'Project "%s" has been completed.', 'ict-platform' ),
					$data['project']['project_name'] ?? 'Unknown'
				);

			case 'time_entry_approved':
				return sprintf(
					__( 'Your time entry for %s hours has been approved.', 'ict-platform' ),
					$data['time_entry']['total_hours'] ?? '0'
				);

			case 'time_entry_rejected':
				return sprintf(
					__( 'Your time entry was rejected: %s', 'ict-platform' ),
					$data['reason'] ?? 'No reason provided'
				);

			case 'task_assigned':
				return sprintf(
					__( 'New task assigned: %s', 'ict-platform' ),
					$data['task']['title'] ?? $data['task']['name'] ?? 'Task'
				);

			case 'task_due_reminder':
				return sprintf(
					__( 'Reminder: Task "%s" is due soon.', 'ict-platform' ),
					$data['task']['title'] ?? $data['task']['name'] ?? 'Task'
				);

			case 'schedule_conflict':
				return __( 'Schedule conflict detected. Please check your calendar.', 'ict-platform' );

			default:
				return $data['title'] ?? __( 'You have a new notification.', 'ict-platform' );
		}
	}

	/**
	 * Sanitize phone number.
	 *
	 * @since  1.1.0
	 * @param  string $phone Phone number.
	 * @return string Sanitized phone number.
	 */
	private function sanitize_phone_number( $phone ) {
		// Remove all non-numeric characters except +
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		// Add + prefix if not present
		if ( strpos( $phone, '+' ) !== 0 ) {
			// Assume US number if no country code
			if ( strlen( $phone ) === 10 ) {
				$phone = '+1' . $phone;
			} elseif ( strlen( $phone ) === 11 && strpos( $phone, '1' ) === 0 ) {
				$phone = '+' . $phone;
			}
		}

		return $phone;
	}

	/**
	 * Truncate message to SMS limit.
	 *
	 * @since  1.1.0
	 * @param  string $message Message.
	 * @param  int    $limit   Character limit.
	 * @return string Truncated message.
	 */
	private function truncate_message( $message, $limit = 160 ) {
		if ( strlen( $message ) <= $limit ) {
			return $message;
		}

		return substr( $message, 0, $limit - 3 ) . '...';
	}

	/**
	 * Log SMS activity.
	 *
	 * @since  1.1.0
	 * @param  string      $to         Recipient.
	 * @param  string      $message    Message.
	 * @param  string      $status     Status.
	 * @param  string|null $error      Error message.
	 * @param  string|null $message_id Twilio message ID.
	 * @return void
	 */
	private function log_sms( $to, $message, $status, $error = null, $message_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_sms_log';

		$wpdb->insert(
			$table,
			array(
				'recipient'  => $to,
				'message'    => $message,
				'status'     => $status,
				'error'      => $error,
				'twilio_sid' => $message_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Send test SMS.
	 *
	 * @since  1.1.0
	 * @param  string $phone Phone number.
	 * @return array Result.
	 */
	public function send_test( $phone ) {
		return $this->send(
			$phone,
			sprintf(
				__( '[%s] This is a test SMS from ICT Platform. If you receive this, SMS notifications are configured correctly!', 'ict-platform' ),
				get_bloginfo( 'name' )
			)
		);
	}

	/**
	 * Get account balance.
	 *
	 * @since  1.1.0
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_balance() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Twilio is not configured', 'ict-platform' ) );
		}

		$response = wp_remote_get(
			"{$this->api_base}/Accounts/{$this->account_sid}/Balance.json",
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$this->account_sid}:{$this->auth_token}" ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'currency' => $body['currency'] ?? 'USD',
			'balance'  => $body['balance'] ?? '0',
		);
	}

	/**
	 * Get message history.
	 *
	 * @since  1.1.0
	 * @param  int $limit Number of messages.
	 * @return array Messages.
	 */
	public function get_message_history( $limit = 50 ) {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$response = wp_remote_get(
			"{$this->api_base}/Accounts/{$this->account_sid}/Messages.json?PageSize={$limit}",
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$this->account_sid}:{$this->auth_token}" ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['messages'] ?? array();
	}

	/**
	 * Validate phone number with Twilio Lookup.
	 *
	 * @since  1.1.0
	 * @param  string $phone Phone number.
	 * @return array Validation result.
	 */
	public function validate_phone( $phone ) {
		if ( ! $this->is_configured() ) {
			return array(
				'valid'   => false,
				'message' => __( 'Twilio is not configured', 'ict-platform' ),
			);
		}

		$phone = $this->sanitize_phone_number( $phone );

		$response = wp_remote_get(
			"https://lookups.twilio.com/v1/PhoneNumbers/{$phone}",
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$this->account_sid}:{$this->auth_token}" ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'valid'   => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return array(
				'valid'        => true,
				'phone_number' => $body['phone_number'] ?? $phone,
				'country_code' => $body['country_code'] ?? null,
				'carrier'      => $body['carrier']['name'] ?? null,
			);
		}

		return array(
			'valid'   => false,
			'message' => __( 'Invalid phone number', 'ict-platform' ),
		);
	}
}
