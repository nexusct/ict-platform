<?php
/**
 * Email Notification Handler
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Email_Notification
 *
 * Handles sending email notifications with templates and customization.
 */
class ICT_Email_Notification {

	/**
	 * Email templates.
	 *
	 * @var array
	 */
	private $templates = array();

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		$this->load_templates();
	}

	/**
	 * Load email templates.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function load_templates() {
		$this->templates = array(
			'project_created'        => 'project-created',
			'project_updated'        => 'project-updated',
			'project_completed'      => 'project-completed',
			'time_entry_submitted'   => 'time-entry-submitted',
			'time_entry_approved'    => 'time-entry-approved',
			'time_entry_rejected'    => 'time-entry-rejected',
			'low_stock_alert'        => 'low-stock-alert',
			'po_created'             => 'po-created',
			'po_approved'            => 'po-approved',
			'po_received'            => 'po-received',
			'task_assigned'          => 'task-assigned',
			'task_due_reminder'      => 'task-due-reminder',
			'sync_error'             => 'sync-error',
			'default'                => 'default',
		);
	}

	/**
	 * Send email notification.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients User IDs or email addresses.
	 * @param  array  $data       Notification data.
	 * @return array Result.
	 */
	public function send( $type, $recipients, $data ) {
		$emails = $this->resolve_recipients( $recipients );

		if ( empty( $emails ) ) {
			return array( 'success' => false, 'error' => 'no_valid_recipients' );
		}

		$subject = $this->get_subject( $type, $data );
		$body    = $this->get_body( $type, $data );
		$headers = $this->get_headers();

		$results = array(
			'sent'   => array(),
			'failed' => array(),
		);

		foreach ( $emails as $email ) {
			// Check user preferences
			if ( ! $this->should_send_to_user( $email, $type ) ) {
				continue;
			}

			$sent = wp_mail( $email, $subject, $body, $headers );

			if ( $sent ) {
				$results['sent'][] = $email;
			} else {
				$results['failed'][] = $email;
			}
		}

		return array(
			'success' => count( $results['sent'] ) > 0,
			'sent'    => count( $results['sent'] ),
			'failed'  => count( $results['failed'] ),
		);
	}

	/**
	 * Resolve recipients to email addresses.
	 *
	 * @since  1.1.0
	 * @param  array $recipients User IDs or email addresses.
	 * @return array Email addresses.
	 */
	private function resolve_recipients( $recipients ) {
		$emails = array();

		foreach ( $recipients as $recipient ) {
			if ( is_numeric( $recipient ) ) {
				// User ID
				$user = get_user_by( 'id', $recipient );
				if ( $user && $user->user_email ) {
					$emails[] = $user->user_email;
				}
			} elseif ( is_email( $recipient ) ) {
				$emails[] = sanitize_email( $recipient );
			}
		}

		return array_unique( $emails );
	}

	/**
	 * Check if should send email to user.
	 *
	 * @since  1.1.0
	 * @param  string $email Email address.
	 * @param  string $type  Notification type.
	 * @return bool True if should send.
	 */
	private function should_send_to_user( $email, $type ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return true; // External email, always send
		}

		$preferences = get_user_meta( $user->ID, 'ict_notification_preferences', true );

		if ( ! is_array( $preferences ) ) {
			return true; // No preferences set, use defaults
		}

		if ( isset( $preferences['email_enabled'] ) && ! $preferences['email_enabled'] ) {
			return false;
		}

		if ( isset( $preferences['notification_types'] ) && is_array( $preferences['notification_types'] ) ) {
			if ( ! in_array( $type, $preferences['notification_types'], true ) ) {
				return false;
			}
		}

		// Check quiet hours
		if ( isset( $preferences['quiet_hours']['enabled'] ) && $preferences['quiet_hours']['enabled'] ) {
			if ( $this->is_quiet_hours( $preferences['quiet_hours'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if current time is within quiet hours.
	 *
	 * @since  1.1.0
	 * @param  array $quiet_hours Quiet hours settings.
	 * @return bool True if within quiet hours.
	 */
	private function is_quiet_hours( $quiet_hours ) {
		$current_time = current_time( 'H:i' );
		$start        = $quiet_hours['start'] ?? '22:00';
		$end          = $quiet_hours['end'] ?? '07:00';

		if ( $start < $end ) {
			// Same day range (e.g., 09:00 - 17:00)
			return $current_time >= $start && $current_time < $end;
		} else {
			// Overnight range (e.g., 22:00 - 07:00)
			return $current_time >= $start || $current_time < $end;
		}
	}

	/**
	 * Get email subject.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return string Subject.
	 */
	private function get_subject( $type, $data ) {
		$site_name = get_bloginfo( 'name' );
		$prefix    = "[$site_name] ";

		if ( isset( $data['title'] ) ) {
			return $prefix . $data['title'];
		}

		$subjects = array(
			'project_created'        => __( 'New Project Created', 'ict-platform' ),
			'project_updated'        => __( 'Project Updated', 'ict-platform' ),
			'project_completed'      => __( 'Project Completed', 'ict-platform' ),
			'time_entry_submitted'   => __( 'Time Entry Submitted for Approval', 'ict-platform' ),
			'time_entry_approved'    => __( 'Your Time Entry Has Been Approved', 'ict-platform' ),
			'time_entry_rejected'    => __( 'Your Time Entry Has Been Rejected', 'ict-platform' ),
			'low_stock_alert'        => __( 'Low Stock Alert', 'ict-platform' ),
			'po_created'             => __( 'New Purchase Order Created', 'ict-platform' ),
			'po_approved'            => __( 'Purchase Order Approved', 'ict-platform' ),
			'po_received'            => __( 'Purchase Order Received', 'ict-platform' ),
			'task_assigned'          => __( 'New Task Assigned to You', 'ict-platform' ),
			'task_due_reminder'      => __( 'Task Due Reminder', 'ict-platform' ),
			'sync_error'             => __( 'Synchronization Error', 'ict-platform' ),
		);

		return $prefix . ( $subjects[ $type ] ?? __( 'Notification', 'ict-platform' ) );
	}

	/**
	 * Get email body.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return string Email body (HTML).
	 */
	private function get_body( $type, $data ) {
		$template = $this->templates[ $type ] ?? 'default';

		// Try to load template file
		$template_file = ICT_PLATFORM_PLUGIN_DIR . "templates/emails/{$template}.php";

		if ( file_exists( $template_file ) ) {
			ob_start();
			extract( $data );
			include $template_file;
			$content = ob_get_clean();
		} else {
			// Default inline template
			$content = $this->get_default_template( $type, $data );
		}

		return $this->wrap_in_layout( $content );
	}

	/**
	 * Get default email template.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return string HTML content.
	 */
	private function get_default_template( $type, $data ) {
		$title   = $data['title'] ?? __( 'Notification', 'ict-platform' );
		$message = $data['message'] ?? '';

		$html = '<h2>' . esc_html( $title ) . '</h2>';
		$html .= '<p>' . nl2br( esc_html( $message ) ) . '</p>';

		// Add facts if available
		if ( ! empty( $data['facts'] ) && is_array( $data['facts'] ) ) {
			$html .= '<table style="margin-top: 20px; border-collapse: collapse;">';
			foreach ( $data['facts'] as $name => $value ) {
				$html .= '<tr>';
				$html .= '<td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">' . esc_html( $name ) . '</td>';
				$html .= '<td style="padding: 8px; border-bottom: 1px solid #eee;">' . esc_html( $value ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		}

		// Add action button if URL provided
		if ( ! empty( $data['url'] ) ) {
			$html .= '<p style="margin-top: 20px;">';
			$html .= '<a href="' . esc_url( $data['url'] ) . '" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">';
			$html .= __( 'View Details', 'ict-platform' );
			$html .= '</a></p>';
		}

		return $html;
	}

	/**
	 * Wrap content in email layout.
	 *
	 * @since  1.1.0
	 * @param  string $content Email content.
	 * @return string Full HTML email.
	 */
	private function wrap_in_layout( $content ) {
		$site_name = get_bloginfo( 'name' );
		$logo_url  = get_option( 'ict_email_logo_url', '' );
		$primary_color = get_option( 'ict_email_primary_color', '#0073aa' );

		$html = '<!DOCTYPE html>';
		$html .= '<html><head><meta charset="utf-8"></head>';
		$html .= '<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333333; background-color: #f5f5f5;">';

		// Container
		$html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">';
		$html .= '<tr><td align="center">';

		// Email body
		$html .= '<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';

		// Header
		$html .= '<tr><td style="padding: 30px; background-color: ' . esc_attr( $primary_color ) . '; border-radius: 8px 8px 0 0; text-align: center;">';
		if ( $logo_url ) {
			$html .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '" style="max-height: 50px;">';
		} else {
			$html .= '<h1 style="margin: 0; color: #ffffff; font-size: 24px;">' . esc_html( $site_name ) . '</h1>';
		}
		$html .= '</td></tr>';

		// Content
		$html .= '<tr><td style="padding: 30px;">';
		$html .= $content;
		$html .= '</td></tr>';

		// Footer
		$html .= '<tr><td style="padding: 20px 30px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px; color: #666666;">';
		$html .= '<p style="margin: 0;">' . sprintf(
			__( 'This email was sent by %s.', 'ict-platform' ),
			esc_html( $site_name )
		) . '</p>';
		$html .= '<p style="margin: 10px 0 0;">';
		$html .= '<a href="' . esc_url( home_url( '/notification-preferences' ) ) . '" style="color: ' . esc_attr( $primary_color ) . ';">';
		$html .= __( 'Manage your notification preferences', 'ict-platform' );
		$html .= '</a></p>';
		$html .= '</td></tr>';

		$html .= '</table></td></tr></table></body></html>';

		return $html;
	}

	/**
	 * Get email headers.
	 *
	 * @since  1.1.0
	 * @return array Headers.
	 */
	private function get_headers() {
		$from_name  = get_option( 'ict_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'ict_email_from_address', get_option( 'admin_email' ) );

		return array(
			'Content-Type: text/html; charset=UTF-8',
			"From: {$from_name} <{$from_email}>",
		);
	}

	/**
	 * Send test email.
	 *
	 * @since  1.1.0
	 * @param  string $email Email address.
	 * @return bool True on success.
	 */
	public function send_test( $email ) {
		$data = array(
			'title'   => __( 'Test Email', 'ict-platform' ),
			'message' => __( 'This is a test email from ICT Platform. If you received this, your email notifications are configured correctly!', 'ict-platform' ),
			'facts'   => array(
				__( 'Time', 'ict-platform' )   => current_time( 'mysql' ),
				__( 'Server', 'ict-platform' ) => php_uname( 'n' ),
			),
		);

		$result = $this->send( 'default', array( $email ), $data );

		return $result['success'];
	}

	/**
	 * Queue email for later sending (digest mode).
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Recipients.
	 * @param  array  $data       Notification data.
	 * @return int Queue ID.
	 */
	public function queue( $type, $recipients, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_email_queue';

		$wpdb->insert(
			$table,
			array(
				'notification_type' => $type,
				'recipients'        => wp_json_encode( $recipients ),
				'data'              => wp_json_encode( $data ),
				'status'            => 'pending',
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Process email queue.
	 *
	 * @since  1.1.0
	 * @param  int $limit Max emails to process.
	 * @return int Number processed.
	 */
	public function process_queue( $limit = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_email_queue';

		$queued = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
				$limit
			)
		);

		$processed = 0;

		foreach ( $queued as $item ) {
			$recipients = json_decode( $item->recipients, true );
			$data       = json_decode( $item->data, true );

			$result = $this->send( $item->notification_type, $recipients, $data );

			$wpdb->update(
				$table,
				array(
					'status'     => $result['success'] ? 'sent' : 'failed',
					'sent_at'    => current_time( 'mysql' ),
					'result'     => wp_json_encode( $result ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$processed++;
		}

		return $processed;
	}

	/**
	 * Send digest email to user.
	 *
	 * @since  1.1.0
	 * @param  int    $user_id User ID.
	 * @param  string $period  Digest period (daily, weekly).
	 * @return bool True on success.
	 */
	public function send_digest( $user_id, $period = 'daily' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_notifications_log';

		$interval = $period === 'weekly' ? 7 : 1;

		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE JSON_CONTAINS(recipients, %s)
				AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
				ORDER BY created_at DESC",
				wp_json_encode( $user_id ),
				$interval
			)
		);

		if ( empty( $notifications ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		$subject = sprintf(
			__( '[%s] Your %s Digest', 'ict-platform' ),
			get_bloginfo( 'name' ),
			$period === 'weekly' ? __( 'Weekly', 'ict-platform' ) : __( 'Daily', 'ict-platform' )
		);

		$body = $this->build_digest_body( $notifications );

		return wp_mail( $user->user_email, $subject, $this->wrap_in_layout( $body ), $this->get_headers() );
	}

	/**
	 * Build digest email body.
	 *
	 * @since  1.1.0
	 * @param  array $notifications Notifications.
	 * @return string HTML body.
	 */
	private function build_digest_body( $notifications ) {
		$html = '<h2>' . __( 'Your Activity Digest', 'ict-platform' ) . '</h2>';
		$html .= '<p>' . sprintf(
			__( 'Here is a summary of %d notifications:', 'ict-platform' ),
			count( $notifications )
		) . '</p>';

		$html .= '<ul style="padding-left: 20px;">';
		foreach ( $notifications as $notification ) {
			$data = json_decode( $notification->data, true );
			$html .= '<li style="margin-bottom: 10px;">';
			$html .= '<strong>' . esc_html( $data['title'] ?? $notification->notification_type ) . '</strong>';
			$html .= '<br><small style="color: #666;">' . esc_html( $notification->created_at ) . '</small>';
			$html .= '</li>';
		}
		$html .= '</ul>';

		return $html;
	}
}
