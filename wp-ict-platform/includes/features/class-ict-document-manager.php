<?php
/**
 * Document Management System
 *
 * Manages project documents, files, and attachments.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Document_Manager
 *
 * Handles document upload, storage, and retrieval.
 */
class ICT_Document_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Document_Manager|null
	 */
	private static $instance = null;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Allowed file types.
	 *
	 * @var array
	 */
	private $allowed_types = array(
		'pdf',
		'doc',
		'docx',
		'xls',
		'xlsx',
		'ppt',
		'pptx',
		'txt',
		'csv',
		'jpg',
		'jpeg',
		'png',
		'gif',
		'svg',
		'dwg',
		'dxf',
		'zip',
		'rar',
	);

	/**
	 * Max file size in bytes (50MB).
	 *
	 * @var int
	 */
	private $max_file_size = 52428800;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Document_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ict_documents';
	}

	/**
	 * Initialize the feature.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/documents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_documents' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_document' ),
					'permission_callback' => array( $this, 'check_upload_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/projects/(?P<project_id>\d+)/documents',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project_documents' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/documents/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_document' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_document' ),
					'permission_callback' => array( $this, 'check_upload_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_document' ),
					'permission_callback' => array( $this, 'check_delete_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/documents/(?P<id>\d+)/download',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_download_url' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/documents/(?P<id>\d+)/versions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_versions' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upload_version' ),
					'permission_callback' => array( $this, 'check_upload_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/documents/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/documents/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_documents' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'edit_ict_projects' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check upload permission.
	 *
	 * @return bool
	 */
	public function check_upload_permission() {
		return current_user_can( 'upload_files' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check delete permission.
	 *
	 * @return bool
	 */
	public function check_delete_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Create database table if needed.
	 */
	public function maybe_create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned,
            parent_id bigint(20) unsigned,
            attachment_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            original_filename varchar(255) NOT NULL,
            file_type varchar(50) NOT NULL,
            file_size bigint(20) unsigned NOT NULL,
            mime_type varchar(100) NOT NULL,
            category varchar(100) DEFAULT 'general',
            description text,
            tags varchar(500),
            version int DEFAULT 1,
            is_latest tinyint(1) DEFAULT 1,
            checksum varchar(64),
            download_count int DEFAULT 0,
            is_public tinyint(1) DEFAULT 0,
            expires_at datetime,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY parent_id (parent_id),
            KEY category (category),
            KEY file_type (file_type),
            KEY created_by (created_by),
            KEY is_latest (is_latest)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get all documents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_documents( $request ) {
		global $wpdb;

		$page      = (int) $request->get_param( 'page' ) ?: 1;
		$per_page  = (int) $request->get_param( 'per_page' ) ?: 20;
		$category  = $request->get_param( 'category' );
		$file_type = $request->get_param( 'file_type' );
		$offset    = ( $page - 1 ) * $per_page;

		$where  = array( 'd.is_latest = 1' );
		$values = array();

		if ( $category ) {
			$where[]  = 'd.category = %s';
			$values[] = $category;
		}

		if ( $file_type ) {
			$where[]  = 'd.file_type = %s';
			$values[] = $file_type;
		}

		$where_clause = implode( ' AND ', $where );

		$count_query = "SELECT COUNT(*) FROM {$this->table_name} d WHERE {$where_clause}";
		$total       = ! empty( $values )
			? $wpdb->get_var( $wpdb->prepare( $count_query, $values ) )
			: $wpdb->get_var( $count_query );

		$query = "SELECT d.*, u.display_name as uploaded_by_name,
                         p.name as project_name
                  FROM {$this->table_name} d
                  LEFT JOIN {$wpdb->users} u ON d.created_by = u.ID
                  LEFT JOIN {$wpdb->prefix}ict_projects p ON d.project_id = p.id
                  WHERE {$where_clause}
                  ORDER BY d.created_at DESC
                  LIMIT %d OFFSET %d";

		$values[] = $per_page;
		$values[] = $offset;

		$documents = $wpdb->get_results( $wpdb->prepare( $query, $values ) );

		foreach ( $documents as &$doc ) {
			$doc->tags                = $doc->tags ? explode( ',', $doc->tags ) : array();
			$doc->file_url            = wp_get_attachment_url( $doc->attachment_id );
			$doc->thumbnail_url       = $this->get_thumbnail_url( $doc );
			$doc->file_size_formatted = size_format( $doc->file_size );
		}

		return rest_ensure_response(
			array(
				'documents' => $documents,
				'total'     => (int) $total,
				'pages'     => ceil( $total / $per_page ),
				'page'      => $page,
			)
		);
	}

	/**
	 * Get project documents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_project_documents( $request ) {
		global $wpdb;

		$project_id = (int) $request->get_param( 'project_id' );
		$category   = $request->get_param( 'category' );

		$where  = array( 'd.project_id = %d', 'd.is_latest = 1' );
		$values = array( $project_id );

		if ( $category ) {
			$where[]  = 'd.category = %s';
			$values[] = $category;
		}

		$where_clause = implode( ' AND ', $where );

		$documents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, u.display_name as uploaded_by_name
                 FROM {$this->table_name} d
                 LEFT JOIN {$wpdb->users} u ON d.created_by = u.ID
                 WHERE {$where_clause}
                 ORDER BY d.category, d.name",
				$values
			)
		);

		foreach ( $documents as &$doc ) {
			$doc->tags                = $doc->tags ? explode( ',', $doc->tags ) : array();
			$doc->file_url            = wp_get_attachment_url( $doc->attachment_id );
			$doc->thumbnail_url       = $this->get_thumbnail_url( $doc );
			$doc->file_size_formatted = size_format( $doc->file_size );
		}

		// Group by category
		$grouped = array();
		foreach ( $documents as $doc ) {
			$grouped[ $doc->category ][] = $doc;
		}

		return rest_ensure_response(
			array(
				'documents' => $documents,
				'grouped'   => $grouped,
			)
		);
	}

	/**
	 * Get single document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_document( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$document = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT d.*, u.display_name as uploaded_by_name,
                        p.name as project_name
                 FROM {$this->table_name} d
                 LEFT JOIN {$wpdb->users} u ON d.created_by = u.ID
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON d.project_id = p.id
                 WHERE d.id = %d",
				$id
			)
		);

		if ( ! $document ) {
			return new WP_Error( 'not_found', 'Document not found', array( 'status' => 404 ) );
		}

		$document->tags                = $document->tags ? explode( ',', $document->tags ) : array();
		$document->file_url            = wp_get_attachment_url( $document->attachment_id );
		$document->thumbnail_url       = $this->get_thumbnail_url( $document );
		$document->file_size_formatted = size_format( $document->file_size );

		return rest_ensure_response( $document );
	}

	/**
	 * Upload a document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function upload_document( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
		}

		$file = $files['file'];

		// Validate file
		$validation = $this->validate_file( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Handle upload
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'upload_error', $upload['error'], array( 'status' => 500 ) );
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Create document record
		global $wpdb;

		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		$data = array(
			'project_id'        => $request->get_param( 'project_id' ) ?: null,
			'attachment_id'     => $attachment_id,
			'name'              => sanitize_text_field( $request->get_param( 'name' ) ?: pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'original_filename' => sanitize_file_name( $file['name'] ),
			'file_type'         => $file_extension,
			'file_size'         => $file['size'],
			'mime_type'         => $upload['type'],
			'category'          => sanitize_text_field( $request->get_param( 'category' ) ?: 'general' ),
			'description'       => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'tags'              => $this->sanitize_tags( $request->get_param( 'tags' ) ),
			'checksum'          => md5_file( $upload['file'] ),
			'created_by'        => get_current_user_id(),
		);

		$wpdb->insert( $this->table_name, $data );
		$document_id = $wpdb->insert_id;

		// Trigger activity
		do_action( 'ict_document_uploaded', $document_id, $data['project_id'], $file['name'] );

		return rest_ensure_response(
			array(
				'success'  => true,
				'id'       => $document_id,
				'file_url' => $upload['url'],
				'message'  => 'Document uploaded successfully',
			)
		);
	}

	/**
	 * Update document metadata.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_document( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$data   = array();
		$fields = array( 'name', 'description', 'category' );

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( $value );
			}
		}

		$tags = $request->get_param( 'tags' );
		if ( null !== $tags ) {
			$data['tags'] = $this->sanitize_tags( $tags );
		}

		$is_public = $request->get_param( 'is_public' );
		if ( null !== $is_public ) {
			$data['is_public'] = (int) $is_public;
		}

		$expires_at = $request->get_param( 'expires_at' );
		if ( null !== $expires_at ) {
			$data['expires_at'] = sanitize_text_field( $expires_at );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->table_name, $data, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Document updated successfully',
			)
		);
	}

	/**
	 * Delete a document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_document( $request ) {
		global $wpdb;

		$id          = (int) $request->get_param( 'id' );
		$delete_file = $request->get_param( 'delete_file' );

		$document = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		if ( ! $document ) {
			return new WP_Error( 'not_found', 'Document not found', array( 'status' => 404 ) );
		}

		// Delete all versions
		if ( $document->parent_id ) {
			$parent_id = $document->parent_id;
		} else {
			$parent_id = $document->id;
		}

		// Get all version IDs
		$version_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE id = %d OR parent_id = %d",
				$parent_id,
				$parent_id
			)
		);

		// Delete attachment files if requested
		if ( $delete_file ) {
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT attachment_id FROM {$this->table_name} WHERE id = %d OR parent_id = %d",
					$parent_id,
					$parent_id
				)
			);

			foreach ( $attachment_ids as $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}

		// Delete document records
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE id = %d OR parent_id = %d",
				$parent_id,
				$parent_id
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Document deleted successfully',
			)
		);
	}

	/**
	 * Get download URL with tracking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_download_url( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$document = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		if ( ! $document ) {
			return new WP_Error( 'not_found', 'Document not found', array( 'status' => 404 ) );
		}

		// Increment download count
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET download_count = download_count + 1 WHERE id = %d",
				$id
			)
		);

		$file_path = get_attached_file( $document->attachment_id );
		$file_url  = wp_get_attachment_url( $document->attachment_id );

		return rest_ensure_response(
			array(
				'url'      => $file_url,
				'filename' => $document->original_filename,
				'size'     => $document->file_size,
			)
		);
	}

	/**
	 * Get document versions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_versions( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$document = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		if ( ! $document ) {
			return new WP_Error( 'not_found', 'Document not found', array( 'status' => 404 ) );
		}

		$parent_id = $document->parent_id ?: $document->id;

		$versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, u.display_name as uploaded_by_name
                 FROM {$this->table_name} d
                 LEFT JOIN {$wpdb->users} u ON d.created_by = u.ID
                 WHERE d.id = %d OR d.parent_id = %d
                 ORDER BY d.version DESC",
				$parent_id,
				$parent_id
			)
		);

		foreach ( $versions as &$ver ) {
			$ver->file_url            = wp_get_attachment_url( $ver->attachment_id );
			$ver->file_size_formatted = size_format( $ver->file_size );
		}

		return rest_ensure_response( $versions );
	}

	/**
	 * Upload a new version.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function upload_version( $request ) {
		global $wpdb;

		$id    = (int) $request->get_param( 'id' );
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
		}

		$document = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		if ( ! $document ) {
			return new WP_Error( 'not_found', 'Document not found', array( 'status' => 404 ) );
		}

		$file = $files['file'];

		// Validate file
		$validation = $this->validate_file( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Handle upload
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'upload_error', $upload['error'], array( 'status' => 500 ) );
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		// Get parent ID
		$parent_id = $document->parent_id ?: $document->id;

		// Get current max version
		$max_version = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version) FROM {$this->table_name} WHERE id = %d OR parent_id = %d",
				$parent_id,
				$parent_id
			)
		);

		// Mark all existing versions as not latest
		$wpdb->update(
			$this->table_name,
			array( 'is_latest' => 0 ),
			array( 'parent_id' => $parent_id )
		);
		$wpdb->update(
			$this->table_name,
			array( 'is_latest' => 0 ),
			array( 'id' => $parent_id )
		);

		// Create new version record
		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		$data = array(
			'project_id'        => $document->project_id,
			'parent_id'         => $parent_id,
			'attachment_id'     => $attachment_id,
			'name'              => $document->name,
			'original_filename' => sanitize_file_name( $file['name'] ),
			'file_type'         => $file_extension,
			'file_size'         => $file['size'],
			'mime_type'         => $upload['type'],
			'category'          => $document->category,
			'description'       => sanitize_textarea_field( $request->get_param( 'description' ) ?: $document->description ),
			'tags'              => $document->tags,
			'version'           => $max_version + 1,
			'is_latest'         => 1,
			'checksum'          => md5_file( $upload['file'] ),
			'created_by'        => get_current_user_id(),
		);

		$wpdb->insert( $this->table_name, $data );
		$version_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'success'  => true,
				'id'       => $version_id,
				'version'  => $data['version'],
				'file_url' => $upload['url'],
				'message'  => 'New version uploaded successfully',
			)
		);
	}

	/**
	 * Get document categories.
	 *
	 * @return WP_REST_Response
	 */
	public function get_categories() {
		return rest_ensure_response(
			array(
				'general'      => 'General',
				'contracts'    => 'Contracts',
				'drawings'     => 'Drawings & Plans',
				'permits'      => 'Permits',
				'invoices'     => 'Invoices',
				'photos'       => 'Photos',
				'reports'      => 'Reports',
				'specs'        => 'Specifications',
				'manuals'      => 'Manuals',
				'certificates' => 'Certificates',
				'safety'       => 'Safety Documents',
				'other'        => 'Other',
			)
		);
	}

	/**
	 * Search documents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search_documents( $request ) {
		global $wpdb;

		$query      = sanitize_text_field( $request->get_param( 'q' ) );
		$project_id = (int) $request->get_param( 'project_id' );

		if ( strlen( $query ) < 2 ) {
			return new WP_Error( 'query_too_short', 'Search query must be at least 2 characters', array( 'status' => 400 ) );
		}

		$where  = array( 'd.is_latest = 1' );
		$values = array();

		$where[]     = '(d.name LIKE %s OR d.description LIKE %s OR d.tags LIKE %s OR d.original_filename LIKE %s)';
		$search_term = '%' . $wpdb->esc_like( $query ) . '%';
		$values[]    = $search_term;
		$values[]    = $search_term;
		$values[]    = $search_term;
		$values[]    = $search_term;

		if ( $project_id ) {
			$where[]  = 'd.project_id = %d';
			$values[] = $project_id;
		}

		$where_clause = implode( ' AND ', $where );

		$documents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, p.name as project_name
                 FROM {$this->table_name} d
                 LEFT JOIN {$wpdb->prefix}ict_projects p ON d.project_id = p.id
                 WHERE {$where_clause}
                 ORDER BY d.name
                 LIMIT 50",
				$values
			)
		);

		foreach ( $documents as &$doc ) {
			$doc->tags                = $doc->tags ? explode( ',', $doc->tags ) : array();
			$doc->file_url            = wp_get_attachment_url( $doc->attachment_id );
			$doc->file_size_formatted = size_format( $doc->file_size );
		}

		return rest_ensure_response( $documents );
	}

	/**
	 * Validate uploaded file.
	 *
	 * @param array $file File data.
	 * @return true|WP_Error
	 */
	private function validate_file( $file ) {
		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', 'File upload error', array( 'status' => 400 ) );
		}

		// Check file size
		if ( $file['size'] > $this->max_file_size ) {
			return new WP_Error( 'file_too_large', 'File exceeds maximum size of 50MB', array( 'status' => 400 ) );
		}

		// Check file type
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->allowed_types, true ) ) {
			return new WP_Error( 'invalid_type', 'File type not allowed', array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Sanitize tags.
	 *
	 * @param mixed $tags Tags input.
	 * @return string
	 */
	private function sanitize_tags( $tags ) {
		if ( is_array( $tags ) ) {
			$tags = implode( ',', array_map( 'sanitize_text_field', $tags ) );
		} elseif ( is_string( $tags ) ) {
			$tags = implode( ',', array_map( 'trim', array_map( 'sanitize_text_field', explode( ',', $tags ) ) ) );
		} else {
			$tags = '';
		}
		return $tags;
	}

	/**
	 * Get thumbnail URL for document.
	 *
	 * @param object $document Document object.
	 * @return string|null
	 */
	private function get_thumbnail_url( $document ) {
		$image_types = array( 'jpg', 'jpeg', 'png', 'gif' );

		if ( in_array( $document->file_type, $image_types, true ) ) {
			$thumbnail = wp_get_attachment_image_src( $document->attachment_id, 'thumbnail' );
			return $thumbnail ? $thumbnail[0] : null;
		}

		return null;
	}
}
