<?php
/**
 * REST API: Expenses Controller
 *
 * Handles expense management endpoints for mobile app.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Expenses_Controller
 *
 * Handles expense submission and management via REST API.
 */
class ICT_REST_Expenses_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'expenses';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /expenses
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_expenses' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /expenses/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_expense' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// POST /expenses
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_expense' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// PUT /expenses/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_expense' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// DELETE /expenses/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_expense' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// POST /expenses/{id}/submit
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/submit',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_expense' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// POST /expenses/{id}/receipt
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/receipt',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_receipt' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * Get expenses.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_expenses( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$status  = $request->get_param( 'status' );

		// Build query
		$where = "WHERE user_id = %d";
		$params = array( $user_id );

		if ( $status ) {
			$where .= " AND status = %s";
			$params[] = $status;
		}

		// Get expenses
		$expenses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_expenses
				$where
				ORDER BY date DESC, id DESC",
				...$params
			),
			ARRAY_A
		);

		// Format expenses
		$formatted_expenses = array_map( array( $this, 'format_expense' ), $expenses );

		return new WP_REST_Response( $formatted_expenses, 200 );
	}

	/**
	 * Get single expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_expense( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		$expense = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $expense ) {
			return new WP_Error(
				'expense_not_found',
				'Expense not found',
				array( 'status' => 404 )
			);
		}

		// Check ownership
		if ( $expense['user_id'] != get_current_user_id() && ! current_user_can( 'manage_ict_expenses' ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to view this expense',
				array( 'status' => 403 )
			);
		}

		return new WP_REST_Response( $this->format_expense( $expense ), 200 );
	}

	/**
	 * Create expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_expense( $request ) {
		global $wpdb;

		$this->maybe_create_expenses_table();

		$data = array(
			'user_id'      => get_current_user_id(),
			'project_id'   => absint( $request->get_param( 'project_id' ) ),
			'description'  => sanitize_text_field( $request->get_param( 'description' ) ),
			'category'     => sanitize_text_field( $request->get_param( 'category' ) ),
			'amount'       => floatval( $request->get_param( 'amount' ) ),
			'currency'     => sanitize_text_field( $request->get_param( 'currency' ) ?: 'USD' ),
			'date'         => $request->get_param( 'date' ),
			'status'       => 'draft',
			'notes'        => sanitize_textarea_field( $request->get_param( 'notes' ) ),
			'created_at'   => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'ict_expenses',
			$data,
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to create expense',
				array( 'status' => 500 )
			);
		}

		$expense_id = $wpdb->insert_id;
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $expense_id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->format_expense( $expense ), 201 );
	}

	/**
	 * Update expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_expense( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		// Get expense
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $expense ) {
			return new WP_Error(
				'expense_not_found',
				'Expense not found',
				array( 'status' => 404 )
			);
		}

		// Check ownership
		if ( $expense['user_id'] != get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to update this expense',
				array( 'status' => 403 )
			);
		}

		// Can only update draft expenses
		if ( $expense['status'] !== 'draft' ) {
			return new WP_Error(
				'invalid_status',
				'Only draft expenses can be updated',
				array( 'status' => 400 )
			);
		}

		// Update data
		$update_data = array();
		$update_format = array();

		$fields = array( 'project_id', 'description', 'category', 'amount', 'currency', 'date', 'notes' );
		foreach ( $fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$update_data[ $field ] = $request->get_param( $field );
				$update_format[] = in_array( $field, array( 'project_id' ) ) ? '%d' : ( $field === 'amount' ? '%f' : '%s' );
			}
		}

		if ( ! empty( $update_data ) ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'ict_expenses',
				$update_data,
				array( 'id' => $id ),
				$update_format,
				array( '%d' )
			);

			if ( false === $result ) {
				return new WP_Error(
					'database_error',
					'Failed to update expense',
					array( 'status' => 500 )
				);
			}
		}

		// Get updated expense
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->format_expense( $expense ), 200 );
	}

	/**
	 * Delete expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_expense( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		// Get expense
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $expense ) {
			return new WP_Error(
				'expense_not_found',
				'Expense not found',
				array( 'status' => 404 )
			);
		}

		// Check ownership
		if ( $expense['user_id'] != get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to delete this expense',
				array( 'status' => 403 )
			);
		}

		// Can only delete draft expenses
		if ( $expense['status'] !== 'draft' ) {
			return new WP_Error(
				'invalid_status',
				'Only draft expenses can be deleted',
				array( 'status' => 400 )
			);
		}

		// Delete expense
		$result = $wpdb->delete(
			$wpdb->prefix . 'ict_expenses',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to delete expense',
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array( 'message' => 'Expense deleted successfully' ),
			200
		);
	}

	/**
	 * Submit expense.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_expense( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		// Get expense
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $expense ) {
			return new WP_Error(
				'expense_not_found',
				'Expense not found',
				array( 'status' => 404 )
			);
		}

		// Check ownership
		if ( $expense['user_id'] != get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to submit this expense',
				array( 'status' => 403 )
			);
		}

		// Can only submit draft expenses
		if ( $expense['status'] !== 'draft' ) {
			return new WP_Error(
				'invalid_status',
				'Only draft expenses can be submitted',
				array( 'status' => 400 )
			);
		}

		// Update status to submitted
		$result = $wpdb->update(
			$wpdb->prefix . 'ict_expenses',
			array(
				'status'       => 'submitted',
				'submitted_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to submit expense',
				array( 'status' => 500 )
			);
		}

		// Queue for Zoho sync (if integration is enabled)
		ICT_Helper::queue_sync(
			array(
				'entity_type'  => 'expense',
				'entity_id'    => $id,
				'action'       => 'create',
				'zoho_service' => 'books',
				'priority'     => 5,
				'payload'      => $expense,
			)
		);

		// Get updated expense
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->format_expense( $expense ), 200 );
	}

	/**
	 * Upload receipt.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_receipt( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		// Get expense
		$expense = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_expenses WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $expense ) {
			return new WP_Error(
				'expense_not_found',
				'Expense not found',
				array( 'status' => 404 )
			);
		}

		// Check ownership
		if ( $expense['user_id'] != get_current_user_id() ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to upload receipt for this expense',
				array( 'status' => 403 )
			);
		}

		// Handle file upload
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error(
				'no_file',
				'No file uploaded',
				array( 'status' => 400 )
			);
		}

		$attachment_id = media_handle_sideload(
			$files['file'],
			0,
			null,
			array(
				'test_form' => false,
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$receipt_url = wp_get_attachment_url( $attachment_id );

		// Update expense with receipt URL
		$wpdb->update(
			$wpdb->prefix . 'ict_expenses',
			array( 'receipt_url' => $receipt_url ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return new WP_REST_Response(
			array(
				'id'  => $attachment_id,
				'url' => $receipt_url,
			),
			200
		);
	}

	/**
	 * Format expense data.
	 *
	 * @param array $expense Expense data.
	 * @return array
	 */
	private function format_expense( $expense ) {
		return array(
			'id'           => (int) $expense['id'],
			'project_id'   => (int) $expense['project_id'],
			'description'  => $expense['description'],
			'category'     => $expense['category'],
			'amount'       => (float) $expense['amount'],
			'currency'     => $expense['currency'],
			'date'         => $expense['date'],
			'receipt_url'  => $expense['receipt_url'] ?? null,
			'status'       => $expense['status'],
			'notes'        => $expense['notes'] ?? null,
			'created_at'   => $expense['created_at'],
			'submitted_at' => $expense['submitted_at'] ?? null,
		);
	}

	/**
	 * Create expenses table if it doesn't exist.
	 */
	private function maybe_create_expenses_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ict_expenses';
		$charset_collate = $wpdb->get_charset_collate();

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $table_exists !== $table_name ) {
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				project_id bigint(20) unsigned NOT NULL,
				description varchar(255) NOT NULL,
				category varchar(100) NOT NULL,
				amount decimal(10,2) NOT NULL,
				currency varchar(10) DEFAULT 'USD',
				date date NOT NULL,
				receipt_url varchar(500) DEFAULT NULL,
				status varchar(20) DEFAULT 'draft',
				notes text DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				submitted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY project_id (project_id),
				KEY status (status),
				KEY date (date)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}
}
