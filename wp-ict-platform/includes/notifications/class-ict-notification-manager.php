<?php
/**
 * Notification Manager
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Notification_Manager
 *
 * Central manager for all notification channels (email, SMS, Teams, push).
 */
class ICT_Notification_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Notification_Manager
	 */
	private static $instance = null;

	/**
	 * Notification types and their default channels.
	 *
	 * @var array
	 */
	private $notification_types = array(
		'project_created'        => array( 'email', 'teams' ),
		'project_updated'        => array( 'email', 'teams' ),
		'project_completed'      => array( 'email', 'teams', 'sms' ),
		'time_entry_submitted'   => array( 'email' ),
		'time_entry_approved'    => array( 'email', 'sms' ),
		'time_entry_rejected'    => array( 'email', 'sms' ),
		'low_stock_alert'        => array( 'email', 'teams' ),
		'po_created'             => array( 'email', 'teams' ),
		'po_approved'            => array( 'email' ),
		'po_received'            => array( 'email', 'teams' ),
		'task_assigned'          => array( 'email', 'sms', 'push' ),
		'task_due_reminder'      => array( 'email', 'sms', 'push' ),
		'sync_error'             => array( 'email', 'teams' ),
		'user_mentioned'         => array( 'email', 'push' ),
		'schedule_conflict'      => array( 'email', 'sms' ),
		'overtime_alert'         => array( 'email', 'teams' ),
	);

	/**
	 * Get singleton instance.
	 *
	 * @since  1.1.0
	 * @return ICT_Notification_Manager
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
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private function init_hooks() {
		// Project notifications
		add_action( 'ict_project_created', array( $this, 'handle_project_created' ), 10, 1 );
		add_action( 'ict_project_updated', array( $this, 'handle_project_updated' ), 10, 2 );
		add_action( 'ict_project_completed', array( $this, 'handle_project_completed' ), 10, 1 );

		// Time entry notifications
		add_action( 'ict_time_entry_submitted', array( $this, 'handle_time_entry_submitted' ), 10, 1 );
		add_action( 'ict_time_entry_approved', array( $this, 'handle_time_entry_approved' ), 10, 1 );
		add_action( 'ict_time_entry_rejected', array( $this, 'handle_time_entry_rejected' ), 10, 2 );

		// Inventory notifications
		add_action( 'ict_low_stock_detected', array( $this, 'handle_low_stock_alert' ), 10, 1 );

		// PO notifications
		add_action( 'ict_po_created', array( $this, 'handle_po_created' ), 10, 1 );
		add_action( 'ict_po_approved', array( $this, 'handle_po_approved' ), 10, 1 );
		add_action( 'ict_po_received', array( $this, 'handle_po_received' ), 10, 1 );

		// Task notifications
		add_action( 'ict_task_assigned', array( $this, 'handle_task_assigned' ), 10, 2 );
		add_action( 'ict_task_due_reminder', array( $this, 'handle_task_due_reminder' ), 10, 1 );

		// System notifications
		add_action( 'ict_sync_error', array( $this, 'handle_sync_error' ), 10, 2 );
	}

	/**
	 * Send notification through multiple channels.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Array of user IDs or email addresses.
	 * @param  array  $data       Notification data.
	 * @param  array  $channels   Optional specific channels to use.
	 * @return array Results per channel.
	 */
	public function send( $type, $recipients, $data, $channels = null ) {
		if ( ! get_option( 'ict_enable_notifications', true ) ) {
			return array( 'skipped' => true, 'reason' => 'notifications_disabled' );
		}

		$channels = $channels ?? $this->get_channels_for_type( $type );
		$results  = array();

		foreach ( $channels as $channel ) {
			$results[ $channel ] = $this->send_via_channel( $channel, $type, $recipients, $data );
		}

		// Log notification
		$this->log_notification( $type, $recipients, $data, $results );

		return $results;
	}

	/**
	 * Get enabled channels for notification type.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @return array Enabled channels.
	 */
	private function get_channels_for_type( $type ) {
		$default_channels = $this->notification_types[ $type ] ?? array( 'email' );
		$user_settings    = get_option( 'ict_notification_channels', array() );

		if ( isset( $user_settings[ $type ] ) ) {
			return array_intersect( $default_channels, $user_settings[ $type ] );
		}

		return $default_channels;
	}

	/**
	 * Send notification via specific channel.
	 *
	 * @since  1.1.0
	 * @param  string $channel    Channel name.
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Recipients.
	 * @param  array  $data       Notification data.
	 * @return array Result.
	 */
	private function send_via_channel( $channel, $type, $recipients, $data ) {
		switch ( $channel ) {
			case 'email':
				return $this->send_email( $type, $recipients, $data );

			case 'sms':
				return $this->send_sms( $type, $recipients, $data );

			case 'teams':
				return $this->send_teams( $type, $data );

			case 'push':
				return $this->send_push( $type, $recipients, $data );

			default:
				return array( 'success' => false, 'error' => 'unknown_channel' );
		}
	}

	/**
	 * Send email notification.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Recipients.
	 * @param  array  $data       Notification data.
	 * @return array Result.
	 */
	private function send_email( $type, $recipients, $data ) {
		if ( ! get_option( 'ict_enable_email_notifications', true ) ) {
			return array( 'success' => false, 'skipped' => true );
		}

		$email_handler = new ICT_Email_Notification();
		return $email_handler->send( $type, $recipients, $data );
	}

	/**
	 * Send SMS notification.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Recipients.
	 * @param  array  $data       Notification data.
	 * @return array Result.
	 */
	private function send_sms( $type, $recipients, $data ) {
		if ( ! get_option( 'ict_enable_sms_notifications', false ) ) {
			return array( 'success' => false, 'skipped' => true );
		}

		$sms_handler = new ICT_Twilio_SMS_Adapter();
		return $sms_handler->send_notification( $type, $recipients, $data );
	}

	/**
	 * Send Microsoft Teams notification.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return array Result.
	 */
	private function send_teams( $type, $data ) {
		if ( ! get_option( 'ict_enable_teams_notifications', false ) ) {
			return array( 'success' => false, 'skipped' => true );
		}

		$teams_handler = new ICT_Microsoft_Teams_Adapter();
		$webhook_url   = $this->get_teams_webhook_for_type( $type );

		if ( empty( $webhook_url ) ) {
			return array( 'success' => false, 'error' => 'no_webhook_configured' );
		}

		$card    = $this->create_teams_card_for_type( $type, $data );
		$success = $teams_handler->send_webhook_message( $webhook_url, '', $card );

		return array( 'success' => $success );
	}

	/**
	 * Send push notification.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Recipients.
	 * @param  array  $data       Notification data.
	 * @return array Result.
	 */
	private function send_push( $type, $recipients, $data ) {
		// Push notifications via service worker
		$push_handler = new ICT_Push_Notification();
		return $push_handler->send( $type, $recipients, $data );
	}

	/**
	 * Get Teams webhook URL for notification type.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @return string Webhook URL.
	 */
	private function get_teams_webhook_for_type( $type ) {
		$webhook_mapping = array(
			'project_created'   => 'ict_teams_project_webhook',
			'project_updated'   => 'ict_teams_project_webhook',
			'project_completed' => 'ict_teams_project_webhook',
			'low_stock_alert'   => 'ict_teams_inventory_webhook',
			'po_created'        => 'ict_teams_inventory_webhook',
			'po_received'       => 'ict_teams_inventory_webhook',
			'sync_error'        => 'ict_teams_alerts_webhook',
			'overtime_alert'    => 'ict_teams_alerts_webhook',
		);

		$option_name = $webhook_mapping[ $type ] ?? 'ict_teams_general_webhook';

		return get_option( $option_name, '' );
	}

	/**
	 * Create Teams card for notification type.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @param  array  $data Notification data.
	 * @return array Teams card structure.
	 */
	private function create_teams_card_for_type( $type, $data ) {
		$title       = $data['title'] ?? $this->get_notification_title( $type );
		$description = $data['message'] ?? '';
		$theme_color = $this->get_theme_color_for_type( $type );

		$card = array(
			'@type'      => 'MessageCard',
			'@context'   => 'http://schema.org/extensions',
			'themeColor' => $theme_color,
			'summary'    => $title,
			'sections'   => array(
				array(
					'activityTitle' => $title,
					'text'          => $description,
					'markdown'      => true,
				),
			),
		);

		if ( isset( $data['facts'] ) ) {
			$card['sections'][0]['facts'] = array();
			foreach ( $data['facts'] as $name => $value ) {
				$card['sections'][0]['facts'][] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}

		if ( isset( $data['url'] ) ) {
			$card['potentialAction'] = array(
				array(
					'@type'   => 'OpenUri',
					'name'    => __( 'View Details', 'ict-platform' ),
					'targets' => array(
						array(
							'os'  => 'default',
							'uri' => $data['url'],
						),
					),
				),
			);
		}

		return $card;
	}

	/**
	 * Get notification title for type.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @return string Title.
	 */
	private function get_notification_title( $type ) {
		$titles = array(
			'project_created'        => __( 'New Project Created', 'ict-platform' ),
			'project_updated'        => __( 'Project Updated', 'ict-platform' ),
			'project_completed'      => __( 'Project Completed', 'ict-platform' ),
			'time_entry_submitted'   => __( 'Time Entry Submitted', 'ict-platform' ),
			'time_entry_approved'    => __( 'Time Entry Approved', 'ict-platform' ),
			'time_entry_rejected'    => __( 'Time Entry Rejected', 'ict-platform' ),
			'low_stock_alert'        => __( 'Low Stock Alert', 'ict-platform' ),
			'po_created'             => __( 'Purchase Order Created', 'ict-platform' ),
			'po_approved'            => __( 'Purchase Order Approved', 'ict-platform' ),
			'po_received'            => __( 'Purchase Order Received', 'ict-platform' ),
			'task_assigned'          => __( 'New Task Assigned', 'ict-platform' ),
			'task_due_reminder'      => __( 'Task Due Reminder', 'ict-platform' ),
			'sync_error'             => __( 'Sync Error', 'ict-platform' ),
			'overtime_alert'         => __( 'Overtime Alert', 'ict-platform' ),
		);

		return $titles[ $type ] ?? __( 'ICT Platform Notification', 'ict-platform' );
	}

	/**
	 * Get theme color for notification type.
	 *
	 * @since  1.1.0
	 * @param  string $type Notification type.
	 * @return string Hex color code.
	 */
	private function get_theme_color_for_type( $type ) {
		$colors = array(
			'project_created'        => '0076D7',
			'project_completed'      => '00FF00',
			'low_stock_alert'        => 'FF0000',
			'sync_error'             => 'FF0000',
			'overtime_alert'         => 'FFA500',
			'time_entry_rejected'    => 'FF0000',
			'time_entry_approved'    => '00FF00',
		);

		return $colors[ $type ] ?? '0076D7';
	}

	/**
	 * Log notification.
	 *
	 * @since  1.1.0
	 * @param  string $type       Notification type.
	 * @param  array  $recipients Recipients.
	 * @param  array  $data       Notification data.
	 * @param  array  $results    Send results.
	 * @return void
	 */
	private function log_notification( $type, $recipients, $data, $results ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ict_notifications_log';

		$wpdb->insert(
			$table,
			array(
				'notification_type' => $type,
				'recipients'        => wp_json_encode( $recipients ),
				'data'              => wp_json_encode( $data ),
				'results'           => wp_json_encode( $results ),
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Handle project created event.
	 *
	 * @since  1.1.0
	 * @param  array $project Project data.
	 * @return void
	 */
	public function handle_project_created( $project ) {
		$recipients = $this->get_project_stakeholders( $project );

		$data = array(
			'title'   => sprintf( __( 'New Project: %s', 'ict-platform' ), $project['project_name'] ),
			'message' => sprintf(
				__( 'A new project "%s" has been created.', 'ict-platform' ),
				$project['project_name']
			),
			'facts'   => array(
				__( 'Project Number', 'ict-platform' ) => $project['project_number'] ?? 'N/A',
				__( 'Status', 'ict-platform' )         => ucfirst( $project['status'] ?? 'pending' ),
			),
			'project' => $project,
		);

		$this->send( 'project_created', $recipients, $data );
	}

	/**
	 * Handle project updated event.
	 *
	 * @since  1.1.0
	 * @param  array $project Project data.
	 * @param  array $changes Changed fields.
	 * @return void
	 */
	public function handle_project_updated( $project, $changes ) {
		$recipients = $this->get_project_stakeholders( $project );

		$data = array(
			'title'   => sprintf( __( 'Project Updated: %s', 'ict-platform' ), $project['project_name'] ),
			'message' => sprintf(
				__( 'Project "%s" has been updated.', 'ict-platform' ),
				$project['project_name']
			),
			'project' => $project,
			'changes' => $changes,
		);

		$this->send( 'project_updated', $recipients, $data );
	}

	/**
	 * Handle project completed event.
	 *
	 * @since  1.1.0
	 * @param  array $project Project data.
	 * @return void
	 */
	public function handle_project_completed( $project ) {
		$recipients = $this->get_project_stakeholders( $project );

		$data = array(
			'title'   => sprintf( __( 'Project Completed: %s', 'ict-platform' ), $project['project_name'] ),
			'message' => sprintf(
				__( 'Congratulations! Project "%s" has been completed.', 'ict-platform' ),
				$project['project_name']
			),
			'project' => $project,
		);

		$this->send( 'project_completed', $recipients, $data );
	}

	/**
	 * Handle time entry submitted event.
	 *
	 * @since  1.1.0
	 * @param  array $time_entry Time entry data.
	 * @return void
	 */
	public function handle_time_entry_submitted( $time_entry ) {
		// Notify approvers
		$approvers = $this->get_time_entry_approvers( $time_entry );

		$technician_name = ICT_Helper::get_user_display_name( $time_entry['technician_id'] );

		$data = array(
			'title'      => __( 'Time Entry Submitted for Approval', 'ict-platform' ),
			'message'    => sprintf(
				__( '%s has submitted a time entry for %s hours.', 'ict-platform' ),
				$technician_name,
				number_format( $time_entry['total_hours'], 2 )
			),
			'time_entry' => $time_entry,
		);

		$this->send( 'time_entry_submitted', $approvers, $data );
	}

	/**
	 * Handle time entry approved event.
	 *
	 * @since  1.1.0
	 * @param  array $time_entry Time entry data.
	 * @return void
	 */
	public function handle_time_entry_approved( $time_entry ) {
		$recipients = array( $time_entry['technician_id'] );

		$data = array(
			'title'      => __( 'Time Entry Approved', 'ict-platform' ),
			'message'    => sprintf(
				__( 'Your time entry for %s hours has been approved.', 'ict-platform' ),
				number_format( $time_entry['total_hours'], 2 )
			),
			'time_entry' => $time_entry,
		);

		$this->send( 'time_entry_approved', $recipients, $data );
	}

	/**
	 * Handle time entry rejected event.
	 *
	 * @since  1.1.0
	 * @param  array  $time_entry Time entry data.
	 * @param  string $reason     Rejection reason.
	 * @return void
	 */
	public function handle_time_entry_rejected( $time_entry, $reason ) {
		$recipients = array( $time_entry['technician_id'] );

		$data = array(
			'title'      => __( 'Time Entry Rejected', 'ict-platform' ),
			'message'    => sprintf(
				__( 'Your time entry has been rejected. Reason: %s', 'ict-platform' ),
				$reason
			),
			'time_entry' => $time_entry,
			'reason'     => $reason,
		);

		$this->send( 'time_entry_rejected', $recipients, $data );
	}

	/**
	 * Handle low stock alert.
	 *
	 * @since  1.1.0
	 * @param  array $items Low stock items.
	 * @return void
	 */
	public function handle_low_stock_alert( $items ) {
		$recipients = $this->get_inventory_managers();

		$item_list = array();
		foreach ( array_slice( $items, 0, 5 ) as $item ) {
			$item_list[] = sprintf( '%s (%d available)', $item->item_name, $item->quantity_available );
		}

		$data = array(
			'title'   => __( 'Low Stock Alert', 'ict-platform' ),
			'message' => sprintf(
				__( '%d items are below reorder level: %s', 'ict-platform' ),
				count( $items ),
				implode( ', ', $item_list )
			),
			'items'   => $items,
		);

		$this->send( 'low_stock_alert', $recipients, $data );
	}

	/**
	 * Handle PO created event.
	 *
	 * @since  1.1.0
	 * @param  array $po Purchase order data.
	 * @return void
	 */
	public function handle_po_created( $po ) {
		$approvers = $this->get_po_approvers();

		$data = array(
			'title'   => sprintf( __( 'New Purchase Order: %s', 'ict-platform' ), $po['po_number'] ),
			'message' => sprintf(
				__( 'A new purchase order %s has been created for %s.', 'ict-platform' ),
				$po['po_number'],
				ICT_Helper::format_currency( $po['total_amount'] )
			),
			'po'      => $po,
		);

		$this->send( 'po_created', $approvers, $data );
	}

	/**
	 * Handle PO approved event.
	 *
	 * @since  1.1.0
	 * @param  array $po Purchase order data.
	 * @return void
	 */
	public function handle_po_approved( $po ) {
		$recipients = array( $po['created_by'] );

		$data = array(
			'title'   => sprintf( __( 'Purchase Order Approved: %s', 'ict-platform' ), $po['po_number'] ),
			'message' => sprintf(
				__( 'Purchase order %s has been approved.', 'ict-platform' ),
				$po['po_number']
			),
			'po'      => $po,
		);

		$this->send( 'po_approved', $recipients, $data );
	}

	/**
	 * Handle PO received event.
	 *
	 * @since  1.1.0
	 * @param  array $po Purchase order data.
	 * @return void
	 */
	public function handle_po_received( $po ) {
		$recipients = $this->get_inventory_managers();

		$data = array(
			'title'   => sprintf( __( 'Purchase Order Received: %s', 'ict-platform' ), $po['po_number'] ),
			'message' => sprintf(
				__( 'Purchase order %s has been received.', 'ict-platform' ),
				$po['po_number']
			),
			'po'      => $po,
		);

		$this->send( 'po_received', $recipients, $data );
	}

	/**
	 * Handle task assigned event.
	 *
	 * @since  1.1.0
	 * @param  array $task    Task data.
	 * @param  int   $user_id Assigned user ID.
	 * @return void
	 */
	public function handle_task_assigned( $task, $user_id ) {
		$data = array(
			'title'   => __( 'New Task Assigned', 'ict-platform' ),
			'message' => sprintf(
				__( 'You have been assigned a new task: %s', 'ict-platform' ),
				$task['title'] ?? $task['name'] ?? 'Task'
			),
			'task'    => $task,
		);

		$this->send( 'task_assigned', array( $user_id ), $data );
	}

	/**
	 * Handle task due reminder.
	 *
	 * @since  1.1.0
	 * @param  array $task Task data.
	 * @return void
	 */
	public function handle_task_due_reminder( $task ) {
		$recipients = array( $task['assigned_to'] ?? $task['user_id'] );

		$data = array(
			'title'   => __( 'Task Due Reminder', 'ict-platform' ),
			'message' => sprintf(
				__( 'Task "%s" is due soon.', 'ict-platform' ),
				$task['title'] ?? $task['name'] ?? 'Task'
			),
			'task'    => $task,
		);

		$this->send( 'task_due_reminder', $recipients, $data );
	}

	/**
	 * Handle sync error.
	 *
	 * @since  1.1.0
	 * @param  string $service      Service name.
	 * @param  string $error_message Error message.
	 * @return void
	 */
	public function handle_sync_error( $service, $error_message ) {
		$admins = $this->get_admins();

		$data = array(
			'title'   => sprintf( __( 'Sync Error: %s', 'ict-platform' ), ucfirst( $service ) ),
			'message' => $error_message,
			'service' => $service,
		);

		$this->send( 'sync_error', $admins, $data );
	}

	/**
	 * Get project stakeholders.
	 *
	 * @since  1.1.0
	 * @param  array $project Project data.
	 * @return array User IDs.
	 */
	private function get_project_stakeholders( $project ) {
		$stakeholders = array();

		if ( ! empty( $project['project_manager_id'] ) ) {
			$stakeholders[] = $project['project_manager_id'];
		}

		// Get assigned technicians
		global $wpdb;
		$assigned = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT resource_id FROM " . ICT_PROJECT_RESOURCES_TABLE . "
				WHERE project_id = %d AND resource_type = 'user'",
				$project['id'] ?? 0
			)
		);

		$stakeholders = array_merge( $stakeholders, $assigned );

		return array_unique( array_filter( $stakeholders ) );
	}

	/**
	 * Get time entry approvers.
	 *
	 * @since  1.1.0
	 * @param  array $time_entry Time entry data.
	 * @return array User IDs.
	 */
	private function get_time_entry_approvers( $time_entry ) {
		// Get project manager
		global $wpdb;
		$project_manager = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT project_manager_id FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
				$time_entry['project_id'] ?? 0
			)
		);

		$approvers = array();

		if ( $project_manager ) {
			$approvers[] = $project_manager;
		}

		// Get users with approval capability
		$users = get_users( array(
			'capability' => 'approve_ict_time_entries',
		) );

		foreach ( $users as $user ) {
			$approvers[] = $user->ID;
		}

		return array_unique( array_filter( $approvers ) );
	}

	/**
	 * Get inventory managers.
	 *
	 * @since  1.1.0
	 * @return array User IDs.
	 */
	private function get_inventory_managers() {
		$users = get_users( array(
			'capability' => 'manage_ict_inventory',
		) );

		return wp_list_pluck( $users, 'ID' );
	}

	/**
	 * Get PO approvers.
	 *
	 * @since  1.1.0
	 * @return array User IDs.
	 */
	private function get_po_approvers() {
		$users = get_users( array(
			'capability' => 'approve_ict_purchase_orders',
		) );

		return wp_list_pluck( $users, 'ID' );
	}

	/**
	 * Get admin users.
	 *
	 * @since  1.1.0
	 * @return array User IDs.
	 */
	private function get_admins() {
		$users = get_users( array(
			'capability' => 'manage_ict_platform',
		) );

		return wp_list_pluck( $users, 'ID' );
	}

	/**
	 * Get user notification preferences.
	 *
	 * @since  1.1.0
	 * @param  int $user_id User ID.
	 * @return array Preferences.
	 */
	public function get_user_preferences( $user_id ) {
		$defaults = array(
			'email_enabled'  => true,
			'sms_enabled'    => false,
			'push_enabled'   => true,
			'digest_mode'    => 'instant', // instant, daily, weekly
			'quiet_hours'    => array(
				'enabled' => false,
				'start'   => '22:00',
				'end'     => '07:00',
			),
			'notification_types' => array_keys( $this->notification_types ),
		);

		$preferences = get_user_meta( $user_id, 'ict_notification_preferences', true );

		return wp_parse_args( $preferences ?: array(), $defaults );
	}

	/**
	 * Update user notification preferences.
	 *
	 * @since  1.1.0
	 * @param  int   $user_id     User ID.
	 * @param  array $preferences Preferences.
	 * @return bool True on success.
	 */
	public function update_user_preferences( $user_id, $preferences ) {
		return update_user_meta( $user_id, 'ict_notification_preferences', $preferences );
	}
}
