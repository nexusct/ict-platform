<?php
/**
 * Zoho Desk Adapter
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_Desk_Adapter
 *
 * Handles synchronization with Zoho Desk (Tickets → Support Issues).
 */
class ICT_Zoho_Desk_Adapter extends ICT_Zoho_API_Client {

	/**
	 * Organization ID for Desk API.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $org_id;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( 'desk' );
		$this->org_id = get_option( 'ict_zoho_desk_org_id' );
	}

	/**
	 * Create entity in Zoho Desk.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type (always 'ticket').
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function create( $entity_type, $data ) {
		if ( empty( $this->org_id ) ) {
			throw new Exception( __( 'Zoho Desk organization ID not configured', 'ict-platform' ) );
		}

		$payload = array(
			'subject'     => $data['subject'],
			'description' => $data['description'] ?? '',
			'departmentId' => get_option( 'ict_zoho_desk_department_id' ),
			'contactId'   => $this->get_desk_contact_id( $data['client_id'] ),
			'priority'    => $data['priority'] ?? 'Medium',
			'status'      => 'Open',
			'channel'     => 'Web',
		);

		if ( ! empty( $data['project_id'] ) ) {
			global $wpdb;
			$project = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT project_number, project_name FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
					$data['project_id']
				)
			);

			if ( $project ) {
				$payload['cf'] = array(
					'cf_project' => $project->project_number,
				);
			}
		}

		$response = $this->post( '/tickets', $payload );

		if ( isset( $response['data']['id'] ) ) {
			$zoho_id = $response['data']['id'];

			// Store ticket ID (if using custom support table)
			update_post_meta( $data['support_ticket_id'], 'zoho_desk_ticket_id', $zoho_id );

			return array(
				'zoho_id' => $zoho_id,
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create ticket in Zoho Desk', 'ict-platform' ) );
	}

	/**
	 * Update entity in Zoho Desk.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function update( $entity_type, $entity_id, $data ) {
		$zoho_id = get_post_meta( $entity_id, 'zoho_desk_ticket_id', true );

		if ( ! $zoho_id ) {
			return $this->create( $entity_type, $data );
		}

		$payload = array(
			'subject'  => $data['subject'],
			'priority' => $data['priority'] ?? 'Medium',
			'status'   => $this->map_status_to_desk( $data['status'] ?? 'open' ),
		);

		$this->put( "/tickets/{$zoho_id}", $payload );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Delete entity from Zoho Desk.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function delete( $entity_type, $entity_id ) {
		$zoho_id = get_post_meta( $entity_id, 'zoho_desk_ticket_id', true );

		if ( ! $zoho_id ) {
			throw new Exception( __( 'Ticket not found in Zoho Desk', 'ict-platform' ) );
		}

		$this->delete( "/tickets/{$zoho_id}" );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'deleted',
		);
	}

	/**
	 * Sync tickets from Zoho Desk.
	 *
	 * @since  1.0.0
	 * @param  array $params Query parameters.
	 * @return array Sync results.
	 */
	public function sync_tickets( $params = array() ) {
		$defaults = array(
			'limit' => 100,
			'from'  => 0,
		);

		$params   = wp_parse_args( $params, $defaults );
		$response = $this->get( '/tickets', $params );

		if ( ! isset( $response['data']['data'] ) ) {
			return array( 'success' => false, 'message' => __( 'No tickets found', 'ict-platform' ) );
		}

		$tickets = $response['data']['data'];
		$synced = 0;

		foreach ( $tickets as $ticket ) {
			// Create or update local support ticket
			// Implementation depends on support ticket structure
			$synced++;
		}

		return array(
			'success' => true,
			'synced'  => $synced,
		);
	}

	/**
	 * Add comment to ticket.
	 *
	 * @since  1.0.0
	 * @param  string $ticket_id Zoho ticket ID.
	 * @param  string $comment   Comment text.
	 * @param  bool   $is_public Whether comment is public.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function add_comment( $ticket_id, $comment, $is_public = false ) {
		$payload = array(
			'content'   => $comment,
			'isPublic'  => $is_public,
		);

		$response = $this->post( "/tickets/{$ticket_id}/comments", $payload );

		if ( isset( $response['data']['id'] ) ) {
			return array(
				'comment_id' => $response['data']['id'],
				'status'     => 'created',
			);
		}

		throw new Exception( __( 'Failed to add comment to ticket', 'ict-platform' ) );
	}

	/**
	 * Get ticket details.
	 *
	 * @since  1.0.0
	 * @param  string $ticket_id Zoho ticket ID.
	 * @return array Ticket data.
	 * @throws Exception On error.
	 */
	public function get_ticket( $ticket_id ) {
		$response = $this->get( "/tickets/{$ticket_id}" );

		if ( isset( $response['data'] ) ) {
			return $response['data'];
		}

		throw new Exception( __( 'Ticket not found', 'ict-platform' ) );
	}

	/**
	 * Get ticket comments.
	 *
	 * @since  1.0.0
	 * @param  string $ticket_id Zoho ticket ID.
	 * @return array Comments data.
	 */
	public function get_ticket_comments( $ticket_id ) {
		$response = $this->get( "/tickets/{$ticket_id}/comments" );

		if ( isset( $response['data']['data'] ) ) {
			return $response['data']['data'];
		}

		return array();
	}

	/**
	 * Map local status to Desk status.
	 *
	 * @since  1.0.0
	 * @param  string $status Local status.
	 * @return string Desk status.
	 */
	protected function map_status_to_desk( $status ) {
		$mapping = array(
			'open'        => 'Open',
			'in-progress' => 'In Progress',
			'waiting'     => 'Waiting on Customer',
			'on-hold'     => 'On Hold',
			'resolved'    => 'Resolved',
			'closed'      => 'Closed',
		);

		return $mapping[ $status ] ?? 'Open';
	}

	/**
	 * Get Desk contact ID from WordPress user or client.
	 *
	 * @since  1.0.0
	 * @param  int $client_id Client/user ID.
	 * @return string|null Desk contact ID.
	 */
	protected function get_desk_contact_id( $client_id ) {
		return get_user_meta( $client_id, 'zoho_desk_contact_id', true );
	}

	/**
	 * Sync contacts to Zoho Desk.
	 *
	 * @since  1.0.0
	 * @return array Sync results.
	 */
	public function sync_contacts() {
		// Get WordPress users (clients)
		$users = get_users( array( 'role' => 'customer' ) );
		$synced = 0;

		foreach ( $users as $user ) {
			try {
				$existing_contact_id = get_user_meta( $user->ID, 'zoho_desk_contact_id', true );

				if ( $existing_contact_id ) {
					continue; // Already synced
				}

				$payload = array(
					'firstName' => $user->first_name ?? '',
					'lastName'  => $user->last_name ?? $user->display_name,
					'email'     => $user->user_email,
					'phone'     => get_user_meta( $user->ID, 'billing_phone', true ),
				);

				$response = $this->post( '/contacts', $payload );

				if ( isset( $response['data']['id'] ) ) {
					update_user_meta( $user->ID, 'zoho_desk_contact_id', $response['data']['id'] );
					$synced++;
				}
			} catch ( Exception $e ) {
				// Log error and continue
				error_log( "Failed to sync contact {$user->ID}: " . $e->getMessage() );
			}
		}

		return array(
			'success' => true,
			'synced'  => $synced,
		);
	}
}
