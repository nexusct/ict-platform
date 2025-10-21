<?php
/**
 * REST API Projects Controller
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Projects_Controller
 *
 * Handles REST API requests for projects.
 */
class ICT_REST_Projects_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'ict/v1';
		$this->rest_base = 'projects';
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);
	}

	/**
	 * Get projects.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$page     = $request->get_param( 'page' ) ?? 1;
		$per_page = $request->get_param( 'per_page' ) ?? 20;
		$status   = $request->get_param( 'status' );
		$search   = $request->get_param( 'search' );

		$offset = ( $page - 1 ) * $per_page;

		$where = array( '1=1' );
		$params = array();

		if ( $status ) {
			$statuses = explode( ',', $status );
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where[] = "status IN ($placeholders)";
			$params = array_merge( $params, $statuses );
		}

		if ( $search ) {
			$where[] = '(project_name LIKE %s OR project_number LIKE %s)';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . ICT_PROJECTS_TABLE . " WHERE $where_sql",
				$params
			)
		);

		$params[] = $per_page;
		$params[] = $offset;

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$params
			)
		);

		return new WP_REST_Response(
			array(
				'data'        => $projects,
				'total'       => (int) $total,
				'page'        => (int) $page,
				'per_page'    => (int) $per_page,
				'total_pages' => ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * Get single project.
	 */
	public function get_item( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
				$id
			)
		);

		if ( ! $project ) {
			return new WP_Error( 'project_not_found', 'Project not found', array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $project,
			),
			200
		);
	}

	/**
	 * Create project.
	 */
	public function create_item( $request ) {
		global $wpdb;

		try {
			$data = array(
				'project_name'       => ICT_Data_Validator::required_string( $request->get_param( 'project_name' ) ),
				'project_number'     => ICT_Helper::generate_project_number(),
				'client_id'          => $request->get_param( 'client_id' ) ? ICT_Data_Validator::id( $request->get_param( 'client_id' ) ) : null,
				'site_address'       => ICT_Data_Validator::optional_string( $request->get_param( 'site_address' ) ),
				'status'             => ICT_Data_Validator::enum( $request->get_param( 'status' ), array( 'pending', 'active', 'on-hold', 'completed', 'cancelled' ), 'pending' ),
				'priority'           => ICT_Data_Validator::enum( $request->get_param( 'priority' ), array( 'low', 'medium', 'high', 'urgent' ), 'medium' ),
				'start_date'         => ICT_Data_Validator::iso_datetime( $request->get_param( 'start_date' ) ),
				'end_date'           => ICT_Data_Validator::iso_datetime( $request->get_param( 'end_date' ) ),
				'estimated_hours'    => ICT_Data_Validator::non_negative_decimal( $request->get_param( 'estimated_hours' ) ?? 0 ),
				'budget_amount'      => ICT_Data_Validator::non_negative_decimal( $request->get_param( 'budget_amount' ) ?? 0, 2 ),
				'project_manager_id' => $request->get_param( 'project_manager_id' ) ? ICT_Data_Validator::id( $request->get_param( 'project_manager_id' ) ) : null,
				'notes'              => ICT_Data_Validator::optional_string( $request->get_param( 'notes' ) ),
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_params', $e->getMessage(), array( 'status' => 400 ) );
		}

		$result = $wpdb->insert(
			ICT_PROJECTS_TABLE,
			$data,
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'create_failed', 'Failed to create project', array( 'status' => 500 ) );
		}

		$project_id = $wpdb->insert_id;

		// Queue sync to Zoho CRM
		ICT_Helper::queue_sync( array(
			'entity_type'  => 'project',
			'entity_id'    => $project_id,
			'action'       => 'create',
			'zoho_service' => 'crm',
			'payload'      => wp_json_encode( $data ),
		) );

		$project = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d", $project_id )
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $project,
				'message' => 'Project created successfully',
			),
			201
		);
	}

	/**
	 * Update project.
	 */
	public function update_item( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$data = array();

		$fields = array( 'project_name', 'site_address', 'status', 'priority', 'start_date', 'end_date', 'estimated_hours', 'budget_amount', 'project_manager_id', 'notes' );
		foreach ( $fields as $field ) {
			if ( ! $request->has_param( $field ) ) {
				continue;
			}
			try {
				$value = $request->get_param( $field );
				if ( 'project_name' === $field ) {
					$data[ $field ] = ICT_Data_Validator::required_string( $value );
				} elseif ( 'site_address' === $field || 'notes' === $field ) {
					$data[ $field ] = ICT_Data_Validator::optional_string( $value );
				} elseif ( 'status' === $field ) {
					$data[ $field ] = ICT_Data_Validator::enum( $value, array( 'pending', 'active', 'on-hold', 'completed', 'cancelled' ) );
				} elseif ( 'priority' === $field ) {
					$data[ $field ] = ICT_Data_Validator::enum( $value, array( 'low', 'medium', 'high', 'urgent' ) );
				} elseif ( 'start_date' === $field || 'end_date' === $field ) {
					$data[ $field ] = ICT_Data_Validator::iso_datetime( $value );
				} elseif ( 'estimated_hours' === $field || 'budget_amount' === $field ) {
					$data[ $field ] = ICT_Data_Validator::non_negative_decimal( $value );
				} elseif ( 'project_manager_id' === $field ) {
					$data[ $field ] = ICT_Data_Validator::id( $value );
				}
			} catch ( InvalidArgumentException $e ) {
				return new WP_Error( 'invalid_params', $e->getMessage(), array( 'status' => 400 ) );
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$result = $wpdb->update(
			ICT_PROJECTS_TABLE,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', 'Failed to update project', array( 'status' => 500 ) );
		}

		// Queue sync
		ICT_Helper::queue_sync( array(
			'entity_type'  => 'project',
			'entity_id'    => $id,
			'action'       => 'update',
			'zoho_service' => 'crm',
		) );

		$project = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d", $id )
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $project,
				'message' => 'Project updated successfully',
			),
			200
		);
	}

	/**
	 * Delete project.
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$result = $wpdb->delete(
			ICT_PROJECTS_TABLE,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', 'Failed to delete project', array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Project deleted successfully',
			),
			200
		);
	}

	/**
	 * Sync project to Zoho.
	 */
	public function sync_item( $request ) {
		$id = $request->get_param( 'id' );

		ICT_Helper::queue_sync( array(
			'entity_type'  => 'project',
			'entity_id'    => $id,
			'action'       => 'update',
			'zoho_service' => 'crm',
			'priority'     => 1,
		) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Sync initiated',
			),
			200
		);
	}

	/**
	 * Permission callbacks.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'view_ict_projects' );
	}

	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'view_ict_projects' );
	}

	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_ict_projects' );
	}

	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_ict_projects' );
	}

	public function delete_item_permissions_check( $request ) {
		return current_user_can( 'delete_ict_projects' );
	}

	/**
	 * Get collection params.
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 20,
			),
			'status'   => array(
				'type' => 'string',
			),
			'search'   => array(
				'type' => 'string',
			),
		);
	}
}
