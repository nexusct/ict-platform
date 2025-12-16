<?php
/**
 * Project Templates System
 *
 * Allows saving and reusing project configurations as templates.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Project_Templates
 *
 * Manages project templates for quick project creation.
 */
class ICT_Project_Templates {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Project_Templates|null
	 */
	private static $instance = null;

	/**
	 * Table name for templates.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Project_Templates
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
		$this->table_name = $wpdb->prefix . 'ict_project_templates';
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
			'/project-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/project-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_template' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/project-templates/(?P<id>\d+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_template' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/projects/(?P<id>\d+)/save-as-template',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_project_as_template' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check user permission.
	 *
	 * @return bool
	 */
	public function check_permission() {
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
            name varchar(255) NOT NULL,
            description text,
            category varchar(100) DEFAULT 'general',
            template_data longtext NOT NULL,
            default_duration int DEFAULT 0,
            default_budget decimal(15,2) DEFAULT 0,
            milestones longtext,
            tasks longtext,
            resources longtext,
            custom_fields longtext,
            is_active tinyint(1) DEFAULT 1,
            usage_count int DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active),
            KEY created_by (created_by)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get all templates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_templates( $request ) {
		global $wpdb;

		$category  = $request->get_param( 'category' );
		$is_active = $request->get_param( 'is_active' );

		$where  = array( '1=1' );
		$values = array();

		if ( $category ) {
			$where[]  = 'category = %s';
			$values[] = $category;
		}

		if ( null !== $is_active ) {
			$where[]  = 'is_active = %d';
			$values[] = (int) $is_active;
		}

		$where_clause = implode( ' AND ', $where );
		$query        = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY name ASC";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$templates = $wpdb->get_results( $query );

		foreach ( $templates as &$template ) {
			$template->template_data = json_decode( $template->template_data, true );
			$template->milestones    = json_decode( $template->milestones, true );
			$template->tasks         = json_decode( $template->tasks, true );
			$template->resources     = json_decode( $template->resources, true );
			$template->custom_fields = json_decode( $template->custom_fields, true );
		}

		return rest_ensure_response( $templates );
	}

	/**
	 * Get single template.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_template( $request ) {
		global $wpdb;

		$id       = (int) $request->get_param( 'id' );
		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$template->template_data = json_decode( $template->template_data, true );
		$template->milestones    = json_decode( $template->milestones, true );
		$template->tasks         = json_decode( $template->tasks, true );
		$template->resources     = json_decode( $template->resources, true );
		$template->custom_fields = json_decode( $template->custom_fields, true );

		return rest_ensure_response( $template );
	}

	/**
	 * Create a new template.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_template( $request ) {
		global $wpdb;

		$data = array(
			'name'             => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'      => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'category'         => sanitize_text_field( $request->get_param( 'category' ) ?: 'general' ),
			'template_data'    => wp_json_encode( $request->get_param( 'template_data' ) ?: array() ),
			'default_duration' => (int) $request->get_param( 'default_duration' ),
			'default_budget'   => (float) $request->get_param( 'default_budget' ),
			'milestones'       => wp_json_encode( $request->get_param( 'milestones' ) ?: array() ),
			'tasks'            => wp_json_encode( $request->get_param( 'tasks' ) ?: array() ),
			'resources'        => wp_json_encode( $request->get_param( 'resources' ) ?: array() ),
			'custom_fields'    => wp_json_encode( $request->get_param( 'custom_fields' ) ?: array() ),
			'created_by'       => get_current_user_id(),
		);

		$wpdb->insert( $this->table_name, $data );
		$template_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $template_id,
				'message' => 'Template created successfully',
			)
		);
	}

	/**
	 * Update a template.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_template( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$data   = array();
		$fields = array( 'name', 'description', 'category', 'default_duration', 'default_budget', 'is_active' );

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				if ( in_array( $field, array( 'name', 'description', 'category' ), true ) ) {
					$data[ $field ] = sanitize_text_field( $value );
				} else {
					$data[ $field ] = $value;
				}
			}
		}

		$json_fields = array( 'template_data', 'milestones', 'tasks', 'resources', 'custom_fields' );
		foreach ( $json_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = wp_json_encode( $value );
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->table_name, $data, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Template updated successfully',
			)
		);
	}

	/**
	 * Delete a template.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_template( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );
		$wpdb->delete( $this->table_name, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Template deleted successfully',
			)
		);
	}

	/**
	 * Apply template to create a new project.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function apply_template( $request ) {
		global $wpdb;

		$template_id  = (int) $request->get_param( 'id' );
		$project_name = sanitize_text_field( $request->get_param( 'project_name' ) );
		$start_date   = sanitize_text_field( $request->get_param( 'start_date' ) );
		$client_id    = (int) $request->get_param( 'client_id' );

		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $template_id )
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$template_data = json_decode( $template->template_data, true );
		$milestones    = json_decode( $template->milestones, true );
		$tasks         = json_decode( $template->tasks, true );

		// Create project
		$projects_table = $wpdb->prefix . 'ict_projects';
		$project_data   = array_merge(
			$template_data,
			array(
				'name'           => $project_name,
				'client_id'      => $client_id,
				'start_date'     => $start_date,
				'status'         => 'planning',
				'budget'         => $template->default_budget,
				'created_by'     => get_current_user_id(),
				'project_number' => $this->generate_project_number(),
			)
		);

		if ( $template->default_duration > 0 && $start_date ) {
			$project_data['end_date'] = date( 'Y-m-d', strtotime( $start_date . ' + ' . $template->default_duration . ' days' ) );
		}

		$wpdb->insert( $projects_table, $project_data );
		$project_id = $wpdb->insert_id;

		// Create milestones
		if ( ! empty( $milestones ) ) {
			$milestones_table = $wpdb->prefix . 'ict_project_milestones';
			foreach ( $milestones as $index => $milestone ) {
				$milestone_date = null;
				if ( $start_date && isset( $milestone['day_offset'] ) ) {
					$milestone_date = date( 'Y-m-d', strtotime( $start_date . ' + ' . $milestone['day_offset'] . ' days' ) );
				}

				$wpdb->insert(
					$milestones_table,
					array(
						'project_id'  => $project_id,
						'name'        => $milestone['name'],
						'description' => $milestone['description'] ?? '',
						'due_date'    => $milestone_date,
						'sort_order'  => $index,
						'status'      => 'pending',
					)
				);
			}
		}

		// Update usage count
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET usage_count = usage_count + 1 WHERE id = %d",
				$template_id
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'project_id' => $project_id,
				'message'    => 'Project created from template successfully',
			)
		);
	}

	/**
	 * Save existing project as template.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function save_project_as_template( $request ) {
		global $wpdb;

		$project_id    = (int) $request->get_param( 'id' );
		$template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
		$category      = sanitize_text_field( $request->get_param( 'category' ) ?: 'general' );

		$projects_table = $wpdb->prefix . 'ict_projects';
		$project        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$projects_table} WHERE id = %d", $project_id )
		);

		if ( ! $project ) {
			return new WP_Error( 'not_found', 'Project not found', array( 'status' => 404 ) );
		}

		// Get project milestones
		$milestones_table = $wpdb->prefix . 'ict_project_milestones';
		$milestones       = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$milestones_table} WHERE project_id = %d ORDER BY sort_order", $project_id ),
			ARRAY_A
		);

		// Calculate day offsets from start date
		$start_date = strtotime( $project->start_date );
		foreach ( $milestones as &$milestone ) {
			if ( $milestone['due_date'] ) {
				$milestone['day_offset'] = ( strtotime( $milestone['due_date'] ) - $start_date ) / 86400;
			}
			unset( $milestone['id'], $milestone['project_id'], $milestone['due_date'], $milestone['status'] );
		}

		// Calculate duration
		$duration = 0;
		if ( $project->start_date && $project->end_date ) {
			$duration = ( strtotime( $project->end_date ) - strtotime( $project->start_date ) ) / 86400;
		}

		// Create template data
		$template_data = array(
			'type'        => $project->type ?? '',
			'priority'    => $project->priority ?? 'medium',
			'description' => $project->description ?? '',
		);

		$data = array(
			'name'             => $template_name ?: 'Template from ' . $project->name,
			'description'      => 'Created from project: ' . $project->name,
			'category'         => $category,
			'template_data'    => wp_json_encode( $template_data ),
			'default_duration' => (int) $duration,
			'default_budget'   => $project->budget ?? 0,
			'milestones'       => wp_json_encode( $milestones ),
			'tasks'            => wp_json_encode( array() ),
			'resources'        => wp_json_encode( array() ),
			'custom_fields'    => wp_json_encode( array() ),
			'created_by'       => get_current_user_id(),
		);

		$wpdb->insert( $this->table_name, $data );
		$template_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'success'     => true,
				'template_id' => $template_id,
				'message'     => 'Project saved as template successfully',
			)
		);
	}

	/**
	 * Generate unique project number.
	 *
	 * @return string
	 */
	private function generate_project_number() {
		return 'PRJ-' . date( 'Y' ) . '-' . str_pad( wp_rand( 1, 99999 ), 5, '0', STR_PAD_LEFT );
	}

	/**
	 * Get template categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		global $wpdb;

		$categories = $wpdb->get_col(
			"SELECT DISTINCT category FROM {$this->table_name} WHERE is_active = 1 ORDER BY category"
		);

		return array_filter( $categories );
	}
}
