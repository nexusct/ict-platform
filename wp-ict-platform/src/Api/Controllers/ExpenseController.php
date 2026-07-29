<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Expense Tracking REST API Controller
 *
 * Handles expense submissions, receipt uploads, and reimbursement workflow.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class ExpenseController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'expenses';

	/**
	 * Register routes for expenses.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /expenses - List all expenses
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItems' ),
					'permission_callback' => array( $this, 'canViewExpenses' ),
					'args'                => $this->getCollectionParams(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createItem' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET/PUT/DELETE /expenses/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItem' ),
					'permission_callback' => array( $this, 'canViewExpense' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateItem' ),
					'permission_callback' => array( $this, 'canEditExpense' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'deleteItem' ),
					'permission_callback' => array( $this, 'canEditExpense' ),
				),
			)
		);

		// POST /expenses/{id}/approve - Approve expense
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'approveExpense' ),
				'permission_callback' => array( $this, 'canApproveExpenses' ),
			)
		);

		// POST /expenses/{id}/reject - Reject expense
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rejectExpense' ),
				'permission_callback' => array( $this, 'canApproveExpenses' ),
			)
		);

		// POST /expenses/{id}/reimburse - Mark as reimbursed
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reimburse',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reimburseExpense' ),
				'permission_callback' => array( $this, 'canApproveExpenses' ),
			)
		);

		// POST /expenses/upload-receipt - Upload receipt
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upload-receipt',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'uploadReceipt' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /expenses/my - Get current user's expenses
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/my',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getMyExpenses' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /expenses/pending - Get pending expenses for approval
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pending',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getPendingExpenses' ),
				'permission_callback' => array( $this, 'canApproveExpenses' ),
			)
		);

		// GET /expenses/categories - Get expense categories
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getCategories' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /expenses/summary - Get expense summary
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getSummary' ),
				'permission_callback' => array( $this, 'canViewExpenses' ),
			)
		);
	}

	/**
	 * Get all expenses with filtering.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItems( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$page     = (int) $request->get_param( 'page' ) ?: 1;
		$per_page = min( (int) $request->get_param( 'per_page' ) ?: 20, 100 );
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( $project_id = $request->get_param( 'project_id' ) ) {
			$where_clauses[] = 'project_id = %d';
			$where_values[]  = (int) $project_id;
		}

		if ( $technician_id = $request->get_param( 'technician_id' ) ) {
			$where_clauses[] = 'technician_id = %d';
			$where_values[]  = (int) $technician_id;
		}

		if ( $status = $request->get_param( 'status' ) ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = sanitize_text_field( $status );
		}

		if ( $category = $request->get_param( 'category' ) ) {
			$where_clauses[] = 'category = %s';
			$where_values[]  = sanitize_text_field( $category );
		}

		if ( $date_from = $request->get_param( 'date_from' ) ) {
			$where_clauses[] = 'expense_date >= %s';
			$where_values[]  = sanitize_text_field( $date_from );
		}

		if ( $date_to = $request->get_param( 'date_to' ) ) {
			$where_clauses[] = 'expense_date <= %s';
			$where_values[]  = sanitize_text_field( $date_to );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count
		$count_sql = 'SELECT COUNT(*) FROM ' . ICT_EXPENSES_TABLE . ' WHERE ' . $where_sql;
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get expenses
		$query        = 'SELECT e.*, u.display_name as technician_name
                  FROM ' . ICT_EXPENSES_TABLE . " e
                  LEFT JOIN {$wpdb->users} u ON e.technician_id = u.ID
                  WHERE " . $where_sql . '
                  ORDER BY expense_date DESC
                  LIMIT %d OFFSET %d';
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

		return $this->paginated( $items ?: array(), $total, $page, $per_page );
	}

	/**
	 * Get a single expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT e.*, u.display_name as technician_name
                 FROM ' . ICT_EXPENSES_TABLE . " e
                 LEFT JOIN {$wpdb->users} u ON e.technician_id = u.ID
                 WHERE e.id = %d",
				$id
			)
		);

		if ( ! $expense ) {
			return $this->error( 'not_found', 'Expense not found', 404 );
		}

		// Add receipt URL
		if ( $expense->receipt_path ) {
			$upload_dir           = wp_upload_dir();
			$expense->receipt_url = $upload_dir['baseurl'] . $expense->receipt_path;
		}

		return $this->success( $expense );
	}

	/**
	 * Create a new expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$data = array(
			'project_id'     => $request->get_param( 'project_id' ) ? (int) $request->get_param( 'project_id' ) : null,
			'technician_id'  => get_current_user_id(),
			'expense_date'   => sanitize_text_field( $request->get_param( 'expense_date' ) ?: date( 'Y-m-d' ) ),
			'category'       => sanitize_text_field( $request->get_param( 'category' ) ),
			'vendor'         => sanitize_text_field( $request->get_param( 'vendor' ) ),
			'description'    => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'amount'         => (float) $request->get_param( 'amount' ),
			'currency'       => sanitize_text_field( $request->get_param( 'currency' ) ?: 'USD' ),
			'receipt_path'   => sanitize_text_field( $request->get_param( 'receipt_path' ) ),
			'payment_method' => sanitize_text_field( $request->get_param( 'payment_method' ) ),
			'is_billable'    => (int) (bool) ( $request->get_param( 'is_billable' ) ?? true ),
			'notes'          => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'status'         => 'pending',
		);

		if ( ! $data['category'] || ! $data['amount'] ) {
			return $this->error( 'validation', 'Category and amount are required', 400 );
		}

		$result = $wpdb->insert( ICT_EXPENSES_TABLE, $data );

		if ( $result === false ) {
			return $this->error( 'insert_failed', 'Failed to create expense', 500 );
		}

		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $wpdb->insert_id )
		);

		// Send notification to manager
		$this->notifyManagers( 'expense_submitted', $expense );

		return $this->success( $expense, 201 );
	}

	/**
	 * Update an expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $expense ) {
			return $this->error( 'not_found', 'Expense not found', 404 );
		}

		// Only pending expenses can be edited
		if ( $expense->status !== 'pending' ) {
			return $this->error( 'not_editable', 'Only pending expenses can be edited', 400 );
		}

		$updateable = array( 'project_id', 'expense_date', 'category', 'vendor', 'description', 'amount', 'payment_method', 'is_billable', 'notes', 'receipt_path' );
		$data       = array();

		foreach ( $updateable as $field ) {
			if ( $request->has_param( $field ) ) {
				$value = $request->get_param( $field );
				if ( $field === 'amount' ) {
					$data[ $field ] = (float) $value;
				} elseif ( $field === 'is_billable' || $field === 'project_id' ) {
					$data[ $field ] = $value ? (int) $value : null;
				} else {
					$data[ $field ] = sanitize_text_field( $value );
				}
			}
		}

		if ( empty( $data ) ) {
			return $this->error( 'no_data', 'No data to update', 400 );
		}

		$wpdb->update( ICT_EXPENSES_TABLE, $data, array( 'id' => $id ) );

		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $expense );
	}

	/**
	 * Delete an expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deleteItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $expense ) {
			return $this->error( 'not_found', 'Expense not found', 404 );
		}

		// Only pending expenses can be deleted
		if ( $expense->status !== 'pending' ) {
			return $this->error( 'not_deletable', 'Only pending expenses can be deleted', 400 );
		}

		// Delete receipt file
		if ( $expense->receipt_path ) {
			$upload_dir = wp_upload_dir();
			$file_path  = $upload_dir['basedir'] . $expense->receipt_path;
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		$wpdb->delete( ICT_EXPENSES_TABLE, array( 'id' => $id ) );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * Approve an expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approveExpense( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $expense ) {
			return $this->error( 'not_found', 'Expense not found', 404 );
		}

		if ( $expense->status !== 'pending' ) {
			return $this->error( 'invalid_status', 'Expense is not pending approval', 400 );
		}

		$wpdb->update(
			ICT_EXPENSES_TABLE,
			array(
				'status'      => 'approved',
				'approved_by' => get_current_user_id(),
				'approved_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		// Notify technician
		$this->notifyUser( $expense->technician_id, 'expense_approved', $expense );

		return $this->success( $expense );
	}

	/**
	 * Reject an expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rejectExpense( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $expense ) {
			return $this->error( 'not_found', 'Expense not found', 404 );
		}

		if ( $expense->status !== 'pending' ) {
			return $this->error( 'invalid_status', 'Expense is not pending approval', 400 );
		}

		$reason = sanitize_textarea_field( $request->get_param( 'reason' ) );

		$wpdb->update(
			ICT_EXPENSES_TABLE,
			array(
				'status' => 'rejected',
				'notes'  => $expense->notes . "\n[Rejected] " . $reason,
			),
			array( 'id' => $id )
		);

		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		// Notify technician
		$this->notifyUser( $expense->technician_id, 'expense_rejected', $expense, array( 'reason' => $reason ) );

		return $this->success( $expense );
	}

	/**
	 * Mark expense as reimbursed.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reimburseExpense( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $expense ) {
			return $this->error( 'not_found', 'Expense not found', 404 );
		}

		if ( $expense->status !== 'approved' ) {
			return $this->error( 'invalid_status', 'Expense must be approved before reimbursement', 400 );
		}

		$wpdb->update(
			ICT_EXPENSES_TABLE,
			array(
				'status'        => 'reimbursed',
				'reimbursed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		// Notify technician
		$this->notifyUser( $expense->technician_id, 'expense_reimbursed', $expense );

		return $this->success( $expense );
	}

	/**
	 * Upload receipt image.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function uploadReceipt( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();

		if ( empty( $files['receipt'] ) ) {
			return $this->error( 'no_file', 'No receipt file uploaded', 400 );
		}

		$file = $files['receipt'];

		// Validate file type
		$allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'application/pdf' );
		if ( ! in_array( $file['type'], $allowed ) ) {
			return $this->error( 'invalid_type', 'Only images and PDF files are allowed', 400 );
		}

		// Check file size (max 10MB)
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			return $this->error( 'file_too_large', 'File size exceeds 10MB limit', 400 );
		}

		// Set up upload directory
		$upload_dir = wp_upload_dir();
		$ict_dir    = $upload_dir['basedir'] . '/ict-platform/receipts/' . date( 'Y/m' );

		if ( ! file_exists( $ict_dir ) ) {
			wp_mkdir_p( $ict_dir );
		}

		// Generate unique filename
		$filename  = wp_unique_filename( $ict_dir, sanitize_file_name( $file['name'] ) );
		$file_path = $ict_dir . '/' . $filename;

		// Move uploaded file
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return $this->error( 'upload_failed', 'Failed to move uploaded file', 500 );
		}

		// Get relative path for storage
		$relative_path = str_replace( $upload_dir['basedir'], '', $file_path );

		return $this->success(
			array(
				'receipt_path' => $relative_path,
				'url'          => $upload_dir['baseurl'] . $relative_path,
			),
			201
		);
	}

	/**
	 * Get current user's expenses.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getMyExpenses( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$user_id = get_current_user_id();
		$status  = $request->get_param( 'status' );

		$where_sql    = 'technician_id = %d';
		$where_values = array( $user_id );

		if ( $status ) {
			$where_sql     .= ' AND status = %s';
			$where_values[] = sanitize_text_field( $status );
		}

		$expenses = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_EXPENSES_TABLE . ' WHERE ' . $where_sql . ' ORDER BY expense_date DESC',
				...$where_values
			)
		);

		return $this->success( $expenses ?: array() );
	}

	/**
	 * Get pending expenses for approval.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getPendingExpenses( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$expenses = $wpdb->get_results(
			'SELECT e.*, u.display_name as technician_name
             FROM ' . ICT_EXPENSES_TABLE . " e
             LEFT JOIN {$wpdb->users} u ON e.technician_id = u.ID
             WHERE e.status = 'pending'
             ORDER BY e.expense_date DESC"
		);

		return $this->success( $expenses ?: array() );
	}

	/**
	 * Get expense categories.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getCategories( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$categories = array(
			'fuel'          => 'Fuel & Gas',
			'materials'     => 'Materials & Supplies',
			'tools'         => 'Tools & Equipment',
			'meals'         => 'Meals & Entertainment',
			'travel'        => 'Travel & Mileage',
			'accommodation' => 'Accommodation',
			'parking'       => 'Parking & Tolls',
			'communication' => 'Phone & Internet',
			'training'      => 'Training & Education',
			'safety'        => 'Safety Equipment',
			'office'        => 'Office Supplies',
			'other'         => 'Other',
		);

		return $this->success( $categories );
	}

	/**
	 * Get expense summary.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getSummary( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$date_from = sanitize_text_field( $request->get_param( 'date_from' ) ?: date( 'Y-m-01' ) );
		$date_to   = sanitize_text_field( $request->get_param( 'date_to' ) ?: date( 'Y-m-d' ) );

		// Total by status
		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) as count, SUM(amount) as total
                 FROM ' . ICT_EXPENSES_TABLE . '
                 WHERE expense_date BETWEEN %s AND %s
                 GROUP BY status',
				$date_from,
				$date_to
			)
		);

		// Total by category
		$by_category = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT category, COUNT(*) as count, SUM(amount) as total
                 FROM ' . ICT_EXPENSES_TABLE . '
                 WHERE expense_date BETWEEN %s AND %s
                 GROUP BY category
                 ORDER BY total DESC',
				$date_from,
				$date_to
			)
		);

		// Grand total
		$grand_total = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(amount) FROM ' . ICT_EXPENSES_TABLE . ' WHERE expense_date BETWEEN %s AND %s',
				$date_from,
				$date_to
			)
		);

		return $this->success(
			array(
				'by_status'   => $by_status,
				'by_category' => $by_category,
				'grand_total' => (float) ( $grand_total ?: 0 ),
				'date_from'   => $date_from,
				'date_to'     => $date_to,
			)
		);
	}

	/**
	 * Check if user can view expenses.
	 *
	 * @return bool
	 */
	protected function canViewExpenses(): bool {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can view a specific expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	protected function canViewExpense( WP_REST_Request $request ): bool {
		global $wpdb;

		if ( $this->canViewExpenses() ) {
			return true;
		}

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT technician_id FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		return $expense && $expense->technician_id === get_current_user_id();
	}

	/**
	 * Check if user can edit a specific expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	protected function canEditExpense( WP_REST_Request $request ): bool {
		global $wpdb;

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$id      = (int) $request->get_param( 'id' );
		$expense = $wpdb->get_row(
			$wpdb->prepare( 'SELECT technician_id, status FROM ' . ICT_EXPENSES_TABLE . ' WHERE id = %d', $id )
		);

		// Only owner can edit pending expenses
		return $expense && $expense->technician_id === get_current_user_id() && $expense->status === 'pending';
	}

	/**
	 * Check if user can approve expenses.
	 *
	 * @return bool
	 */
	protected function canApproveExpenses(): bool {
		return current_user_can( 'approve_ict_time_entries' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Notify managers about expense.
	 *
	 * @param string $type Notification type.
	 * @param object $expense Expense object.
	 * @return void
	 */
	private function notifyManagers( string $type, object $expense ): void {
		global $wpdb;

		$managers = get_users( array( 'role' => 'ict_project_manager' ) );

		foreach ( $managers as $manager ) {
			$wpdb->insert(
				ICT_NOTIFICATIONS_TABLE,
				array(
					'user_id'     => $manager->ID,
					'type'        => $type,
					'title'       => 'New Expense Submitted',
					'message'     => sprintf( 'An expense of %s has been submitted for approval.', $this->helper->formatCurrency( $expense->amount ) ),
					'entity_type' => 'expense',
					'entity_id'   => $expense->id,
					'action_url'  => admin_url( 'admin.php?page=ict-expenses&id=' . $expense->id ),
				)
			);
		}
	}

	/**
	 * Notify a specific user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Notification type.
	 * @param object $expense Expense object.
	 * @param array  $extra Extra data.
	 * @return void
	 */
	private function notifyUser( int $user_id, string $type, object $expense, array $extra = array() ): void {
		global $wpdb;

		$titles = array(
			'expense_approved'   => 'Expense Approved',
			'expense_rejected'   => 'Expense Rejected',
			'expense_reimbursed' => 'Expense Reimbursed',
		);

		$messages = array(
			'expense_approved'   => sprintf( 'Your expense of %s has been approved.', $this->helper->formatCurrency( $expense->amount ) ),
			'expense_rejected'   => sprintf( 'Your expense of %s has been rejected. Reason: %s', $this->helper->formatCurrency( $expense->amount ), $extra['reason'] ?? 'Not specified' ),
			'expense_reimbursed' => sprintf( 'Your expense of %s has been reimbursed.', $this->helper->formatCurrency( $expense->amount ) ),
		);

		$wpdb->insert(
			ICT_NOTIFICATIONS_TABLE,
			array(
				'user_id'     => $user_id,
				'type'        => $type,
				'title'       => $titles[ $type ] ?? 'Expense Update',
				'message'     => $messages[ $type ] ?? 'Your expense has been updated.',
				'entity_type' => 'expense',
				'entity_id'   => $expense->id,
			)
		);
	}
}
