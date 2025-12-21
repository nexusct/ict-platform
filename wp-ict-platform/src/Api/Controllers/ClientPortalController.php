<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Client Portal REST API Controller
 *
 * Provides client-facing endpoints for project status, documents, and support.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class ClientPortalController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'client-portal';

	/**
	 * Register routes for client portal.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /client-portal/projects - Get client's projects
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getClientProjects' ),
				'permission_callback' => array( $this, 'isClientAuthenticated' ),
			)
		);

		// GET /client-portal/projects/{id} - Get project details
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProjectDetails' ),
				'permission_callback' => array( $this, 'canAccessProject' ),
			)
		);

		// GET /client-portal/projects/{id}/documents - Get project documents
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects/(?P<id>[\d]+)/documents',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProjectDocuments' ),
				'permission_callback' => array( $this, 'canAccessProject' ),
			)
		);

		// GET /client-portal/projects/{id}/timeline - Get project timeline
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects/(?P<id>[\d]+)/timeline',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProjectTimeline' ),
				'permission_callback' => array( $this, 'canAccessProject' ),
			)
		);

		// GET /client-portal/projects/{id}/invoices - Get project invoices
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects/(?P<id>[\d]+)/invoices',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProjectInvoices' ),
				'permission_callback' => array( $this, 'canAccessProject' ),
			)
		);

		// POST /client-portal/projects/{id}/approval - Submit approval
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/projects/(?P<id>[\d]+)/approval',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submitApproval' ),
				'permission_callback' => array( $this, 'canAccessProject' ),
			)
		);

		// POST /client-portal/support - Create support request
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/support',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'createSupportRequest' ),
				'permission_callback' => array( $this, 'isClientAuthenticated' ),
			)
		);

		// GET /client-portal/support - Get support requests
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/support',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getSupportRequests' ),
				'permission_callback' => array( $this, 'isClientAuthenticated' ),
			)
		);

		// GET /client-portal/profile - Get client profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProfile' ),
				'permission_callback' => array( $this, 'isClientAuthenticated' ),
			)
		);

		// PUT /client-portal/profile - Update client profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateProfile' ),
				'permission_callback' => array( $this, 'isClientAuthenticated' ),
			)
		);

		// GET /client-portal/dashboard - Get dashboard summary
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getDashboard' ),
				'permission_callback' => array( $this, 'isClientAuthenticated' ),
			)
		);

		// POST /client-portal/auth/request-access - Request portal access
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/auth/request-access',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'requestAccess' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get client's projects.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getClientProjects( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$client_id = $this->getClientId();

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, project_name, project_number, status, priority,
                        start_date, end_date, progress_percentage, site_address
                 FROM ' . ICT_PROJECTS_TABLE . '
                 WHERE client_id = %d
                 ORDER BY created_at DESC',
				$client_id
			)
		);

		// Add status labels
		foreach ( $projects as &$project ) {
			$project->status_label   = $this->getStatusLabel( $project->status );
			$project->priority_label = ucfirst( $project->priority );
		}

		return $this->success( $projects ?: array() );
	}

	/**
	 * Get project details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProjectDetails( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$project = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT p.*, u.display_name as manager_name
                 FROM ' . ICT_PROJECTS_TABLE . " p
                 LEFT JOIN {$wpdb->users} u ON p.project_manager_id = u.ID
                 WHERE p.id = %d",
				$id
			)
		);

		if ( ! $project ) {
			return $this->error( 'not_found', 'Project not found', 404 );
		}

		// Add computed fields
		$project->status_label        = $this->getStatusLabel( $project->status );
		$project->days_remaining      = $this->getDaysRemaining( $project->end_date );
		$project->budget_used_percent = $project->budget_amount > 0
			? round( ( $project->actual_cost / $project->budget_amount ) * 100, 1 )
			: 0;

		// Get team members
		$project->team = $this->getProjectTeam( $id );

		return $this->success( $project );
	}

	/**
	 * Get project documents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProjectDocuments( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// Only get public documents
		$documents = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, document_name, category, file_type, file_size, created_at
                 FROM ' . ICT_DOCUMENTS_TABLE . '
                 WHERE project_id = %d AND is_public = 1
                 ORDER BY created_at DESC',
				$id
			)
		);

		// Add download URLs
		$upload_dir = wp_upload_dir();
		foreach ( $documents as &$doc ) {
			$doc->category_label      = $this->getCategoryLabel( $doc->category );
			$doc->file_size_formatted = size_format( $doc->file_size );
		}

		return $this->success( $documents ?: array() );
	}

	/**
	 * Get project timeline.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProjectTimeline( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// Get activity for this project (limited client view)
		$activity = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT a.action, a.description, a.created_at, u.display_name as user_name
                 FROM ' . ICT_ACTIVITY_LOG_TABLE . " a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE a.entity_type = 'project' AND a.entity_id = %d
                 AND a.action IN ('create', 'update', 'status_change', 'milestone', 'document_upload')
                 ORDER BY a.created_at DESC
                 LIMIT 50",
				$id
			)
		);

		// Format for timeline
		$timeline = array();
		foreach ( $activity as $item ) {
			$timeline[] = array(
				'date'        => $item->created_at,
				'action'      => $item->action,
				'description' => $this->getClientFriendlyDescription( $item ),
				'user'        => $item->user_name,
			);
		}

		return $this->success( $timeline );
	}

	/**
	 * Get project invoices.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProjectInvoices( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// This would integrate with Zoho Books
		// For now, return placeholder
		$id = (int) $request->get_param( 'id' );

		return $this->success(
			array(
				'message'    => 'Invoice integration coming soon',
				'project_id' => $id,
			)
		);
	}

	/**
	 * Submit approval for work/quote.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submitApproval( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$project_id    = (int) $request->get_param( 'id' );
		$approval_type = sanitize_text_field( $request->get_param( 'type' ) ); // quote, change_order, completion
		$approved      = (bool) $request->get_param( 'approved' );
		$comments      = sanitize_textarea_field( $request->get_param( 'comments' ) );

		// Create signature record if approved
		if ( $approved ) {
			$signature_data = $request->get_param( 'signature' );
			if ( $signature_data ) {
				$wpdb->insert(
					ICT_SIGNATURES_TABLE,
					array(
						'entity_type'    => 'project_approval',
						'entity_id'      => $project_id,
						'signer_name'    => sanitize_text_field( $request->get_param( 'signer_name' ) ),
						'signer_email'   => sanitize_email( $request->get_param( 'signer_email' ) ),
						'signer_role'    => 'client',
						'signature_data' => $signature_data,
						'signed_at'      => current_time( 'mysql' ),
						'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? null,
					)
				);
			}
		}

		// Log activity
		$wpdb->insert(
			ICT_ACTIVITY_LOG_TABLE,
			array(
				'user_id'     => get_current_user_id(),
				'action'      => $approved ? 'approval_granted' : 'approval_denied',
				'entity_type' => 'project',
				'entity_id'   => $project_id,
				'description' => sprintf(
					'Client %s %s: %s',
					$approved ? 'approved' : 'rejected',
					$approval_type,
					$comments
				),
			)
		);

		// Notify project manager
		$project = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d', $project_id )
		);

		if ( $project && $project->project_manager_id ) {
			$wpdb->insert(
				ICT_NOTIFICATIONS_TABLE,
				array(
					'user_id'     => $project->project_manager_id,
					'type'        => 'client_approval',
					'title'       => $approved ? 'Approval Received' : 'Approval Denied',
					'message'     => sprintf(
						'Client has %s the %s for project %s',
						$approved ? 'approved' : 'rejected',
						$approval_type,
						$project->project_name
					),
					'entity_type' => 'project',
					'entity_id'   => $project_id,
				)
			);
		}

		return $this->success(
			array(
				'recorded'      => true,
				'approved'      => $approved,
				'approval_type' => $approval_type,
			)
		);
	}

	/**
	 * Create support request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function createSupportRequest( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$subject    = sanitize_text_field( $request->get_param( 'subject' ) );
		$message    = sanitize_textarea_field( $request->get_param( 'message' ) );
		$project_id = $request->get_param( 'project_id' ) ? (int) $request->get_param( 'project_id' ) : null;
		$priority   = sanitize_text_field( $request->get_param( 'priority' ) ?: 'normal' );

		if ( ! $subject || ! $message ) {
			return $this->error( 'validation', 'Subject and message are required', 400 );
		}

		$user = wp_get_current_user();

		// Create support ticket (this would integrate with Zoho Desk)
		// For now, send email and create notification

		$admin_email   = get_option( 'admin_email' );
		$email_subject = sprintf( '[Support Request] %s - %s', $subject, $user->display_name );
		$email_body    = sprintf(
			"Support Request from Client Portal\n\nFrom: %s (%s)\nProject: %s\nPriority: %s\n\nMessage:\n%s",
			$user->display_name,
			$user->user_email,
			$project_id ? "Project #$project_id" : 'General',
			ucfirst( $priority ),
			$message
		);

		wp_mail( $admin_email, $email_subject, $email_body );

		// Create notification for admins
		global $wpdb;
		$admins = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admins as $admin ) {
			$wpdb->insert(
				ICT_NOTIFICATIONS_TABLE,
				array(
					'user_id'     => $admin->ID,
					'type'        => 'support_request',
					'title'       => 'New Support Request',
					'message'     => sprintf( 'Support request from %s: %s', $user->display_name, $subject ),
					'priority'    => $priority,
					'entity_type' => 'support',
				)
			);
		}

		return $this->success(
			array(
				'submitted' => true,
				'message'   => 'Your support request has been submitted. We will respond shortly.',
			),
			201
		);
	}

	/**
	 * Get support requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getSupportRequests( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// This would integrate with Zoho Desk
		return $this->success(
			array(
				'message' => 'Support ticket history coming soon',
				'tickets' => array(),
			)
		);
	}

	/**
	 * Get client profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProfile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user = wp_get_current_user();

		return $this->success(
			array(
				'id'           => $user->ID,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'company'      => get_user_meta( $user->ID, 'company', true ),
				'phone'        => get_user_meta( $user->ID, 'phone', true ),
				'address'      => get_user_meta( $user->ID, 'address', true ),
			)
		);
	}

	/**
	 * Update client profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateProfile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();

		$updateable  = array( 'first_name', 'last_name', 'display_name' );
		$meta_fields = array( 'company', 'phone', 'address' );

		$user_data = array( 'ID' => $user_id );
		foreach ( $updateable as $field ) {
			if ( $request->has_param( $field ) ) {
				$user_data[ $field ] = sanitize_text_field( $request->get_param( $field ) );
			}
		}

		if ( count( $user_data ) > 1 ) {
			wp_update_user( $user_data );
		}

		foreach ( $meta_fields as $field ) {
			if ( $request->has_param( $field ) ) {
				update_user_meta( $user_id, $field, sanitize_text_field( $request->get_param( $field ) ) );
			}
		}

		return $this->success( array( 'updated' => true ) );
	}

	/**
	 * Get dashboard summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getDashboard( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$client_id = $this->getClientId();

		// Project counts by status
		$project_stats = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) as count
                 FROM ' . ICT_PROJECTS_TABLE . '
                 WHERE client_id = %d
                 GROUP BY status',
				$client_id
			)
		);

		// Active projects
		$active_projects = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, project_name, progress_percentage, status, end_date
                 FROM ' . ICT_PROJECTS_TABLE . "
                 WHERE client_id = %d AND status IN ('pending', 'in-progress')
                 ORDER BY end_date ASC
                 LIMIT 5",
				$client_id
			)
		);

		// Recent documents
		$recent_documents = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT d.id, d.document_name, d.category, d.created_at, p.project_name
                 FROM ' . ICT_DOCUMENTS_TABLE . ' d
                 LEFT JOIN ' . ICT_PROJECTS_TABLE . ' p ON d.project_id = p.id
                 WHERE p.client_id = %d AND d.is_public = 1
                 ORDER BY d.created_at DESC
                 LIMIT 5',
				$client_id
			)
		);

		return $this->success(
			array(
				'project_stats'    => $project_stats,
				'active_projects'  => $active_projects,
				'recent_documents' => $recent_documents,
				'welcome_message'  => sprintf( 'Welcome back, %s!', wp_get_current_user()->display_name ),
			)
		);
	}

	/**
	 * Request portal access.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function requestAccess( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$email   = sanitize_email( $request->get_param( 'email' ) );
		$name    = sanitize_text_field( $request->get_param( 'name' ) );
		$company = sanitize_text_field( $request->get_param( 'company' ) );
		$message = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( ! $email || ! $name ) {
			return $this->error( 'validation', 'Email and name are required', 400 );
		}

		// Send notification to admin
		$admin_email = get_option( 'admin_email' );
		wp_mail(
			$admin_email,
			'Client Portal Access Request',
			sprintf(
				"New client portal access request:\n\nName: %s\nEmail: %s\nCompany: %s\nMessage: %s",
				$name,
				$email,
				$company,
				$message
			)
		);

		return $this->success(
			array(
				'submitted' => true,
				'message'   => 'Your access request has been submitted. You will receive an email when your account is ready.',
			)
		);
	}

	/**
	 * Check if client is authenticated.
	 *
	 * @return bool
	 */
	protected function isClientAuthenticated(): bool {
		return is_user_logged_in();
	}

	/**
	 * Check if user can access project.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	protected function canAccessProject( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Admins can access all
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		global $wpdb;
		$id        = (int) $request->get_param( 'id' );
		$client_id = $this->getClientId();

		$project = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d AND client_id = %d',
				$id,
				$client_id
			)
		);

		return (bool) $project;
	}

	/**
	 * Get client ID from current user.
	 *
	 * @return int
	 */
	private function getClientId(): int {
		// Client ID could be the user ID or a linked client record
		// For simplicity, using user ID
		return get_current_user_id();
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status.
	 * @return string Label.
	 */
	private function getStatusLabel( string $status ): string {
		$labels = array(
			'pending'     => 'Pending',
			'in-progress' => 'In Progress',
			'on-hold'     => 'On Hold',
			'completed'   => 'Completed',
			'cancelled'   => 'Cancelled',
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Get category label.
	 *
	 * @param string $category Category.
	 * @return string Label.
	 */
	private function getCategoryLabel( string $category ): string {
		$labels = array(
			'general'  => 'General',
			'contract' => 'Contract',
			'permit'   => 'Permit',
			'invoice'  => 'Invoice',
			'photo'    => 'Photo',
			'drawing'  => 'Drawing',
			'report'   => 'Report',
		);

		return $labels[ $category ] ?? ucfirst( $category );
	}

	/**
	 * Get days remaining until date.
	 *
	 * @param string|null $date Date.
	 * @return int|null Days remaining.
	 */
	private function getDaysRemaining( ?string $date ): ?int {
		if ( ! $date ) {
			return null;
		}

		$end  = strtotime( $date );
		$now  = time();
		$diff = $end - $now;

		return (int) floor( $diff / ( 60 * 60 * 24 ) );
	}

	/**
	 * Get project team members.
	 *
	 * @param int $project_id Project ID.
	 * @return array Team members.
	 */
	private function getProjectTeam( int $project_id ): array {
		global $wpdb;

		$resources = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.resource_id, r.resource_type, u.display_name
                 FROM ' . ICT_PROJECT_RESOURCES_TABLE . " r
                 LEFT JOIN {$wpdb->users} u ON r.resource_id = u.ID
                 WHERE r.project_id = %d AND r.resource_type = 'technician'
                 AND r.status IN ('scheduled', 'active')",
				$project_id
			)
		);

		return array_map(
			function ( $r ) {
				return array(
					'id'   => $r->resource_id,
					'name' => $r->display_name,
					'role' => 'Technician',
				);
			},
			$resources
		);
	}

	/**
	 * Get client-friendly description.
	 *
	 * @param object $activity Activity item.
	 * @return string Description.
	 */
	private function getClientFriendlyDescription( object $activity ): string {
		$descriptions = array(
			'create'          => 'Project created',
			'update'          => 'Project updated',
			'status_change'   => 'Status changed',
			'milestone'       => 'Milestone reached',
			'document_upload' => 'New document uploaded',
		);

		return $descriptions[ $activity->action ] ?? $activity->description;
	}
}
