<?php
/**
 * Import Wizard
 *
 * Step-by-step import wizard for various data types.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Import_Wizard {

	private static $instance = null;
	private $imports_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->imports_table = $wpdb->prefix . 'ict_imports';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/import/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/(?P<id>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'preview_import' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/(?P<id>\d+)/mapping',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mapping' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_mapping' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/(?P<id>\d+)/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_import' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/(?P<id>\d+)/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_import' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/import/templates/(?P<type>[a-z_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'download_template' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'manage_ict_settings' ) || current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->imports_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            import_type varchar(50) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            original_name varchar(255),
            file_size bigint(20) unsigned,
            total_rows int DEFAULT 0,
            processed_rows int DEFAULT 0,
            successful_rows int DEFAULT 0,
            failed_rows int DEFAULT 0,
            skipped_rows int DEFAULT 0,
            column_mapping longtext,
            options longtext,
            validation_errors longtext,
            import_errors longtext,
            status enum('uploaded','mapped','validating','validated','importing','completed','failed') DEFAULT 'uploaded',
            started_at datetime,
            completed_at datetime,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY import_type (import_type),
            KEY status (status),
            KEY created_by (created_by)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function upload_file( $request ) {
		global $wpdb;

		$files       = $request->get_file_params();
		$import_type = sanitize_text_field( $request->get_param( 'import_type' ) );

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
		}

		$file    = $files['file'];
		$allowed = array( 'csv', 'xlsx', 'xls', 'json' );
		$ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed, true ) ) {
			return new WP_Error( 'invalid_type', 'Invalid file type. Allowed: ' . implode( ', ', $allowed ), array( 'status' => 400 ) );
		}

		// Move file
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . '/ict-imports/';

		if ( ! file_exists( $import_dir ) ) {
			wp_mkdir_p( $import_dir );
		}

		$new_name  = 'import-' . uniqid() . '.' . $ext;
		$file_path = $import_dir . $new_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return new WP_Error( 'upload_failed', 'Failed to save file', array( 'status' => 500 ) );
		}

		// Parse file to get row count
		$data       = $this->parse_file( $file_path, $ext );
		$total_rows = count( $data );

		// Create import record
		$wpdb->insert(
			$this->imports_table,
			array(
				'import_type'   => $import_type,
				'file_name'     => $new_name,
				'file_path'     => $file_path,
				'original_name' => $file['name'],
				'file_size'     => $file['size'],
				'total_rows'    => $total_rows,
				'status'        => 'uploaded',
				'created_by'    => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'id'         => $wpdb->insert_id,
				'total_rows' => $total_rows,
				'columns'    => ! empty( $data ) ? array_keys( $data[0] ) : array(),
			)
		);
	}

	public function preview_import( $request ) {
		global $wpdb;
		$id    = (int) $request->get_param( 'id' );
		$limit = (int) $request->get_param( 'limit' ) ?: 10;

		$import = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->imports_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $import ) {
			return new WP_Error( 'not_found', 'Import not found', array( 'status' => 404 ) );
		}

		$ext  = pathinfo( $import->file_path, PATHINFO_EXTENSION );
		$data = $this->parse_file( $import->file_path, $ext, $limit );

		return rest_ensure_response(
			array(
				'import'  => $import,
				'preview' => $data,
				'columns' => ! empty( $data ) ? array_keys( $data[0] ) : array(),
			)
		);
	}

	public function get_mapping( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$import = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT import_type, column_mapping FROM {$this->imports_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $import ) {
			return new WP_Error( 'not_found', 'Import not found', array( 'status' => 404 ) );
		}

		$current_mapping = json_decode( $import->column_mapping, true ) ?: array();
		$target_fields   = $this->get_target_fields( $import->import_type );

		return rest_ensure_response(
			array(
				'current_mapping' => $current_mapping,
				'target_fields'   => $target_fields,
			)
		);
	}

	public function save_mapping( $request ) {
		global $wpdb;
		$id      = (int) $request->get_param( 'id' );
		$mapping = $request->get_param( 'mapping' );
		$options = $request->get_param( 'options' ) ?: array();

		$wpdb->update(
			$this->imports_table,
			array(
				'column_mapping' => wp_json_encode( $mapping ),
				'options'        => wp_json_encode( $options ),
				'status'         => 'mapped',
			),
			array( 'id' => $id )
		);

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function validate_import( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$import = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->imports_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $import ) {
			return new WP_Error( 'not_found', 'Import not found', array( 'status' => 404 ) );
		}

		$wpdb->update( $this->imports_table, array( 'status' => 'validating' ), array( 'id' => $id ) );

		$mapping = json_decode( $import->column_mapping, true );
		$ext     = pathinfo( $import->file_path, PATHINFO_EXTENSION );
		$data    = $this->parse_file( $import->file_path, $ext );

		$errors   = array();
		$warnings = array();

		foreach ( $data as $row_num => $row ) {
			$row_errors = $this->validate_row( $row, $mapping, $import->import_type, $row_num + 1 );

			if ( ! empty( $row_errors['errors'] ) ) {
				$errors = array_merge( $errors, $row_errors['errors'] );
			}
			if ( ! empty( $row_errors['warnings'] ) ) {
				$warnings = array_merge( $warnings, $row_errors['warnings'] );
			}
		}

		$status = empty( $errors ) ? 'validated' : 'mapped';

		$wpdb->update(
			$this->imports_table,
			array(
				'status'            => $status,
				'validation_errors' => wp_json_encode(
					array(
						'errors'   => $errors,
						'warnings' => $warnings,
					)
				),
			),
			array( 'id' => $id )
		);

		return rest_ensure_response(
			array(
				'valid'    => empty( $errors ),
				'errors'   => $errors,
				'warnings' => $warnings,
			)
		);
	}

	public function run_import( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$import = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->imports_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $import ) {
			return new WP_Error( 'not_found', 'Import not found', array( 'status' => 404 ) );
		}

		if ( ! in_array( $import->status, array( 'validated', 'mapped' ), true ) ) {
			return new WP_Error( 'invalid_status', 'Import must be validated first', array( 'status' => 400 ) );
		}

		$wpdb->update(
			$this->imports_table,
			array(
				'status'     => 'importing',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$mapping = json_decode( $import->column_mapping, true );
		$options = json_decode( $import->options, true ) ?: array();
		$ext     = pathinfo( $import->file_path, PATHINFO_EXTENSION );
		$data    = $this->parse_file( $import->file_path, $ext );

		$results = array(
			'successful' => 0,
			'failed'     => 0,
			'skipped'    => 0,
			'errors'     => array(),
		);

		foreach ( $data as $row_num => $row ) {
			try {
				$result = $this->import_row( $row, $mapping, $import->import_type, $options );

				if ( $result === 'skipped' ) {
					++$results['skipped'];
				} else {
					++$results['successful'];
				}

				// Update progress
				$wpdb->update(
					$this->imports_table,
					array(
						'processed_rows'  => $row_num + 1,
						'successful_rows' => $results['successful'],
						'failed_rows'     => $results['failed'],
						'skipped_rows'    => $results['skipped'],
					),
					array( 'id' => $id )
				);

			} catch ( Exception $e ) {
				++$results['failed'];
				$results['errors'][] = array(
					'row'     => $row_num + 1,
					'message' => $e->getMessage(),
				);
			}
		}

		$wpdb->update(
			$this->imports_table,
			array(
				'status'        => 'completed',
				'completed_at'  => current_time( 'mysql' ),
				'import_errors' => wp_json_encode( $results['errors'] ),
			),
			array( 'id' => $id )
		);

		do_action( 'ict_import_completed', $id, $import->import_type, $results );

		return rest_ensure_response( $results );
	}

	public function get_status( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$import = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->imports_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $import ) {
			return new WP_Error( 'not_found', 'Import not found', array( 'status' => 404 ) );
		}

		$import->column_mapping    = json_decode( $import->column_mapping, true );
		$import->validation_errors = json_decode( $import->validation_errors, true );
		$import->import_errors     = json_decode( $import->import_errors, true );

		$progress = $import->total_rows > 0
			? round( ( $import->processed_rows / $import->total_rows ) * 100, 1 )
			: 0;

		return rest_ensure_response(
			array(
				'import'   => $import,
				'progress' => $progress,
			)
		);
	}

	public function get_history( $request ) {
		global $wpdb;
		$limit = (int) $request->get_param( 'limit' ) ?: 20;

		$imports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, u.display_name as created_by_name
             FROM {$this->imports_table} i
             LEFT JOIN {$wpdb->users} u ON i.created_by = u.ID
             ORDER BY i.created_at DESC LIMIT %d",
				$limit
			)
		);

		return rest_ensure_response( $imports );
	}

	public function get_templates( $request ) {
		$templates = array(
			array(
				'type'        => 'projects',
				'name'        => 'Projects Import Template',
				'description' => 'Import projects with name, dates, budget, and status',
				'columns'     => array( 'project_number', 'name', 'description', 'client_name', 'start_date', 'end_date', 'budget', 'status' ),
			),
			array(
				'type'        => 'inventory',
				'name'        => 'Inventory Import Template',
				'description' => 'Import inventory items with SKU, quantities, and pricing',
				'columns'     => array( 'sku', 'name', 'description', 'category', 'quantity', 'unit_cost', 'reorder_point' ),
			),
			array(
				'type'        => 'suppliers',
				'name'        => 'Suppliers Import Template',
				'description' => 'Import supplier information',
				'columns'     => array( 'name', 'code', 'type', 'email', 'phone', 'address_line1', 'city', 'state', 'country' ),
			),
			array(
				'type'        => 'time_entries',
				'name'        => 'Time Entries Import Template',
				'description' => 'Import historical time entries',
				'columns'     => array( 'user_email', 'project_number', 'start_time', 'end_time', 'description', 'is_billable' ),
			),
			array(
				'type'        => 'users',
				'name'        => 'Users Import Template',
				'description' => 'Import users with roles',
				'columns'     => array( 'email', 'first_name', 'last_name', 'role', 'phone' ),
			),
		);

		return rest_ensure_response( $templates );
	}

	public function download_template( $request ) {
		$type = sanitize_text_field( $request->get_param( 'type' ) );

		$templates = array(
			'projects'     => array( 'project_number', 'name', 'description', 'client_name', 'start_date', 'end_date', 'budget', 'status' ),
			'inventory'    => array( 'sku', 'name', 'description', 'category', 'quantity', 'unit_cost', 'reorder_point' ),
			'suppliers'    => array( 'name', 'code', 'type', 'email', 'phone', 'address_line1', 'city', 'state', 'country' ),
			'time_entries' => array( 'user_email', 'project_number', 'start_time', 'end_time', 'description', 'is_billable' ),
			'users'        => array( 'email', 'first_name', 'last_name', 'role', 'phone' ),
		);

		if ( ! isset( $templates[ $type ] ) ) {
			return new WP_Error( 'invalid_type', 'Invalid template type', array( 'status' => 400 ) );
		}

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, $templates[ $type ] );

		// Add sample row
		$sample = array_fill( 0, count( $templates[ $type ] ), 'Sample Value' );
		fputcsv( $output, $sample );

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return new WP_REST_Response(
			$csv,
			200,
			array(
				'Content-Type'        => 'text/csv; charset=utf-8',
				'Content-Disposition' => "attachment; filename=\"{$type}-import-template.csv\"",
			)
		);
	}

	private function parse_file( $file_path, $ext, $limit = null ) {
		switch ( $ext ) {
			case 'csv':
				return $this->parse_csv( $file_path, $limit );
			case 'json':
				return $this->parse_json( $file_path, $limit );
			case 'xlsx':
			case 'xls':
				return $this->parse_excel( $file_path, $limit );
			default:
				return array();
		}
	}

	private function parse_csv( $file_path, $limit = null ) {
		$data   = array();
		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			return array();
		}

		$headers   = fgetcsv( $handle );
		$headers   = array_map( 'trim', $headers );
		$row_count = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( $limit && $row_count >= $limit ) {
				break;
			}

			$data[] = array_combine( $headers, array_pad( $row, count( $headers ), '' ) );
			++$row_count;
		}

		fclose( $handle );

		return $data;
	}

	private function parse_json( $file_path, $limit = null ) {
		$content = file_get_contents( $file_path );
		$data    = json_decode( $content, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( $limit ) {
			return array_slice( $data, 0, $limit );
		}

		return $data;
	}

	private function parse_excel( $file_path, $limit = null ) {
		// Would require PhpSpreadsheet
		return array();
	}

	private function get_target_fields( $import_type ) {
		$fields = array(
			'projects'  => array(
				array(
					'key'      => 'project_number',
					'label'    => 'Project Number',
					'required' => false,
				),
				array(
					'key'      => 'name',
					'label'    => 'Project Name',
					'required' => true,
				),
				array(
					'key'      => 'description',
					'label'    => 'Description',
					'required' => false,
				),
				array(
					'key'      => 'client_id',
					'label'    => 'Client ID',
					'required' => false,
				),
				array(
					'key'      => 'start_date',
					'label'    => 'Start Date',
					'required' => false,
				),
				array(
					'key'      => 'end_date',
					'label'    => 'End Date',
					'required' => false,
				),
				array(
					'key'      => 'budget',
					'label'    => 'Budget',
					'required' => false,
				),
				array(
					'key'      => 'status',
					'label'    => 'Status',
					'required' => false,
				),
			),
			'inventory' => array(
				array(
					'key'      => 'sku',
					'label'    => 'SKU',
					'required' => true,
				),
				array(
					'key'      => 'name',
					'label'    => 'Item Name',
					'required' => true,
				),
				array(
					'key'      => 'description',
					'label'    => 'Description',
					'required' => false,
				),
				array(
					'key'      => 'category',
					'label'    => 'Category',
					'required' => false,
				),
				array(
					'key'      => 'quantity',
					'label'    => 'Quantity',
					'required' => true,
				),
				array(
					'key'      => 'unit_cost',
					'label'    => 'Unit Cost',
					'required' => false,
				),
				array(
					'key'      => 'reorder_point',
					'label'    => 'Reorder Point',
					'required' => false,
				),
			),
			'suppliers' => array(
				array(
					'key'      => 'name',
					'label'    => 'Supplier Name',
					'required' => true,
				),
				array(
					'key'      => 'code',
					'label'    => 'Supplier Code',
					'required' => false,
				),
				array(
					'key'      => 'type',
					'label'    => 'Type',
					'required' => false,
				),
				array(
					'key'      => 'email',
					'label'    => 'Email',
					'required' => false,
				),
				array(
					'key'      => 'phone',
					'label'    => 'Phone',
					'required' => false,
				),
				array(
					'key'      => 'address_line1',
					'label'    => 'Address',
					'required' => false,
				),
				array(
					'key'      => 'city',
					'label'    => 'City',
					'required' => false,
				),
				array(
					'key'      => 'state',
					'label'    => 'State',
					'required' => false,
				),
				array(
					'key'      => 'country',
					'label'    => 'Country',
					'required' => false,
				),
			),
		);

		return $fields[ $import_type ] ?? array();
	}

	private function validate_row( $row, $mapping, $import_type, $row_num ) {
		$errors   = array();
		$warnings = array();

		$fields   = $this->get_target_fields( $import_type );
		$required = array_filter(
			$fields,
			function ( $f ) {
				return $f['required'];
			}
		);

		foreach ( $required as $field ) {
			$source_col = array_search( $field['key'], $mapping, true );
			if ( $source_col === false || empty( $row[ $source_col ] ) ) {
				$errors[] = "Row {$row_num}: Missing required field '{$field['label']}'";
			}
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	private function import_row( $row, $mapping, $import_type, $options ) {
		global $wpdb;

		$data = array();
		foreach ( $mapping as $source => $target ) {
			if ( ! empty( $target ) && isset( $row[ $source ] ) ) {
				$data[ $target ] = $row[ $source ];
			}
		}

		if ( empty( $data ) ) {
			return 'skipped';
		}

		switch ( $import_type ) {
			case 'projects':
				return $this->import_project( $data, $options );
			case 'inventory':
				return $this->import_inventory_item( $data, $options );
			case 'suppliers':
				return $this->import_supplier( $data, $options );
			default:
				throw new Exception( 'Unknown import type' );
		}
	}

	private function import_project( $data, $options ) {
		global $wpdb;

		$data['created_by'] = get_current_user_id();

		if ( empty( $data['project_number'] ) ) {
			$data['project_number'] = ICT_Helper::generate_project_number();
		}

		$wpdb->insert( $wpdb->prefix . 'ict_projects', $data );

		return $wpdb->insert_id;
	}

	private function import_inventory_item( $data, $options ) {
		global $wpdb;

		// Check for existing by SKU
		if ( ! empty( $data['sku'] ) && ! empty( $options['update_existing'] ) ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ict_inventory_items WHERE sku = %s",
					$data['sku']
				)
			);

			if ( $existing ) {
				$wpdb->update( $wpdb->prefix . 'ict_inventory_items', $data, array( 'id' => $existing ) );
				return $existing;
			}
		}

		$data['created_by'] = get_current_user_id();
		$wpdb->insert( $wpdb->prefix . 'ict_inventory_items', $data );

		return $wpdb->insert_id;
	}

	private function import_supplier( $data, $options ) {
		global $wpdb;

		$data['created_by'] = get_current_user_id();
		$wpdb->insert( $wpdb->prefix . 'ict_suppliers', $data );

		return $wpdb->insert_id;
	}
}
