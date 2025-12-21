<?php
/**
 * REST API: Files and Tasks Controller
 *
 * Handles file uploads and task management endpoints for mobile app.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Files_Tasks_Controller
 *
 * Handles files and tasks via REST API.
 */
class ICT_REST_Files_Tasks_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ict/v1';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// ==========================
		// Files Endpoints
		// ==========================

		// POST /projects/{id}/files
		register_rest_route(
			$this->namespace,
			'/projects/(?P<project_id>\d+)/files',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_file' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /projects/{id}/files
		register_rest_route(
			$this->namespace,
			'/projects/(?P<project_id>\d+)/files',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_files' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// DELETE /files/{id}
		register_rest_route(
			$this->namespace,
			'/files/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_file' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// ==========================
		// Tasks Endpoints
		// ==========================

		// GET /tasks
		register_rest_route(
			$this->namespace,
			'/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tasks' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /tasks/{id}
		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_task' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// PUT /tasks/{id}
		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_task' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /projects/{id}/tasks
		register_rest_route(
			$this->namespace,
			'/projects/(?P<project_id>\d+)/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project_tasks' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /time/active
		register_rest_route(
			$this->namespace,
			'/time/active',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_active_time_entry' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	// ============================================================================
	// Files Methods
	// ============================================================================

	/**
	 * Upload file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_file( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );

		// Verify project exists
		global $wpdb;
		$project = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d',
				$project_id
			)
		);

		if ( ! $project ) {
			return new WP_Error(
				'project_not_found',
				'Project not found',
				array( 'status' => 404 )
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

		$file_url = wp_get_attachment_url( $attachment_id );

		// Store metadata linking file to project
		update_post_meta( $attachment_id, '_ict_project_id', $project_id );
		update_post_meta( $attachment_id, '_ict_uploaded_by', get_current_user_id() );
		update_post_meta( $attachment_id, '_ict_uploaded_at', current_time( 'mysql' ) );

		// TODO: Upload to Zoho WorkDrive if integration is enabled

		return new WP_REST_Response(
			array(
				'id'           => $attachment_id,
				'url'          => $file_url,
				'workdrive_id' => null, // Would be set after Zoho sync
			),
			201
		);
	}

	/**
	 * Get files for a project.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_files( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );

		// Get all attachments for this project
		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'numberposts' => -1,
				'meta_key'    => '_ict_project_id',
				'meta_value'  => $project_id,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$files = array();
		foreach ( $attachments as $attachment ) {
			$files[] = array(
				'id'          => $attachment->ID,
				'name'        => $attachment->post_title,
				'url'         => wp_get_attachment_url( $attachment->ID ),
				'type'        => $attachment->post_mime_type,
				'size'        => filesize( get_attached_file( $attachment->ID ) ),
				'uploaded_by' => get_post_meta( $attachment->ID, '_ict_uploaded_by', true ),
				'uploaded_at' => get_post_meta( $attachment->ID, '_ict_uploaded_at', true ),
			);
		}

		return new WP_REST_Response( $files, 200 );
	}

	/**
	 * Delete file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_file( $request ) {
		$file_id = $request->get_param( 'id' );

		// Check if it's a WordPress attachment ID or WorkDrive ID
		if ( is_numeric( $file_id ) ) {
			// WordPress attachment
			$attachment = get_post( $file_id );

			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				return new WP_Error(
					'file_not_found',
					'File not found',
					array( 'status' => 404 )
				);
			}

			// Check permission
			$uploaded_by = get_post_meta( $file_id, '_ict_uploaded_by', true );
			if ( $uploaded_by != get_current_user_id() && ! current_user_can( 'manage_ict_projects' ) ) {
				return new WP_Error(
					'forbidden',
					'You do not have permission to delete this file',
					array( 'status' => 403 )
				);
			}

			// Delete attachment
			$result = wp_delete_attachment( $file_id, true );

			if ( ! $result ) {
				return new WP_Error(
					'delete_failed',
					'Failed to delete file',
					array( 'status' => 500 )
				);
			}
		} else {
			// TODO: Delete from Zoho WorkDrive
		}

		return new WP_REST_Response(
			array( 'message' => 'File deleted successfully' ),
			200
		);
	}

	// ============================================================================
	// Tasks Methods
	// ============================================================================

	/**
	 * Get tasks.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_tasks( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$status  = $request->get_param( 'status' );

		// Create tasks table if it doesn't exist
		$this->maybe_create_tasks_table();

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		// Get tasks
		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_tasks
				$where
				ORDER BY due_date ASC, priority DESC",
				...$params
			),
			ARRAY_A
		);

		// Format tasks
		$formatted_tasks = array_map( array( $this, 'format_task' ), $tasks );

		return new WP_REST_Response( $formatted_tasks, 200 );
	}

	/**
	 * Get single task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_task( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		$task = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_tasks WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $task ) {
			return new WP_Error(
				'task_not_found',
				'Task not found',
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->format_task( $task ), 200 );
	}

	/**
	 * Update task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_task( $request ) {
		global $wpdb;

		$id = absint( $request->get_param( 'id' ) );

		// Get task
		$task = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_tasks WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $task ) {
			return new WP_Error(
				'task_not_found',
				'Task not found',
				array( 'status' => 404 )
			);
		}

		// Update data
		$update_data   = array();
		$update_format = array();

		$fields = array( 'status', 'priority', 'title', 'description', 'due_date', 'estimated_hours', 'actual_hours' );
		foreach ( $fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$update_data[ $field ] = $request->get_param( $field );
				$update_format[]       = in_array( $field, array( 'estimated_hours', 'actual_hours' ) ) ? '%f' : '%s';
			}
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$update_format[]           = '%s';

		if ( ! empty( $update_data ) ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'ict_tasks',
				$update_data,
				array( 'id' => $id ),
				$update_format,
				array( '%d' )
			);

			if ( false === $result ) {
				return new WP_Error(
					'database_error',
					'Failed to update task',
					array( 'status' => 500 )
				);
			}
		}

		// Get updated task
		$task = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ict_tasks WHERE id = %d", $id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->format_task( $task ), 200 );
	}

	/**
	 * Get project tasks.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_project_tasks( $request ) {
		global $wpdb;

		$project_id = absint( $request->get_param( 'project_id' ) );

		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_tasks
				WHERE project_id = %d
				ORDER BY due_date ASC, priority DESC",
				$project_id
			),
			ARRAY_A
		);

		$formatted_tasks = array_map( array( $this, 'format_task' ), $tasks );

		return new WP_REST_Response( $formatted_tasks, 200 );
	}

	/**
	 * Get active time entry.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_active_time_entry( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . "
				WHERE user_id = %d
				AND clock_out IS NULL
				AND status = 'active'
				ORDER BY clock_in DESC
				LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $entry ) {
			return new WP_REST_Response( null, 200 );
		}

		return new WP_REST_Response( $this->format_time_entry( $entry ), 200 );
	}

	/**
	 * Format task data.
	 *
	 * @param array $task Task data.
	 * @return array
	 */
	private function format_task( $task ) {
		return array(
			'id'              => (int) $task['id'],
			'project_id'      => (int) $task['project_id'],
			'title'           => $task['title'],
			'description'     => $task['description'] ?? null,
			'status'          => $task['status'],
			'priority'        => $task['priority'],
			'assigned_to'     => ! empty( $task['assigned_to'] ) ? json_decode( $task['assigned_to'] ) : array(),
			'due_date'        => $task['due_date'] ?? null,
			'estimated_hours' => $task['estimated_hours'] ? (float) $task['estimated_hours'] : null,
			'actual_hours'    => $task['actual_hours'] ? (float) $task['actual_hours'] : null,
			'location'        => ! empty( $task['location'] ) ? json_decode( $task['location'] ) : null,
			'created_at'      => $task['created_at'],
			'updated_at'      => $task['updated_at'],
		);
	}

	/**
	 * Format time entry data.
	 *
	 * @param array $entry Time entry data.
	 * @return array
	 */
	private function format_time_entry( $entry ) {
		return array(
			'id'         => (int) $entry['id'],
			'user_id'    => (int) $entry['user_id'],
			'project_id' => (int) $entry['project_id'],
			'task_id'    => ! empty( $entry['task_id'] ) ? (int) $entry['task_id'] : null,
			'clock_in'   => $entry['clock_in'],
			'clock_out'  => $entry['clock_out'] ?? null,
			'duration'   => $entry['duration'] ? (int) $entry['duration'] : null,
			'notes'      => $entry['notes'] ?? null,
			'location'   => ! empty( $entry['location'] ) ? json_decode( $entry['location'] ) : null,
			'status'     => $entry['status'],
			'created_at' => $entry['created_at'],
			'updated_at' => $entry['updated_at'],
		);
	}

	/**
	 * Create tasks table if it doesn't exist.
	 */
	private function maybe_create_tasks_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ict_tasks';
		$charset_collate = $wpdb->get_charset_collate();

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $table_exists !== $table_name ) {
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				title varchar(255) NOT NULL,
				description text DEFAULT NULL,
				status varchar(20) DEFAULT 'pending',
				priority varchar(20) DEFAULT 'medium',
				assigned_to text DEFAULT NULL,
				due_date date DEFAULT NULL,
				estimated_hours decimal(8,2) DEFAULT NULL,
				actual_hours decimal(8,2) DEFAULT NULL,
				location text DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY project_id (project_id),
				KEY status (status),
				KEY due_date (due_date)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}
}
