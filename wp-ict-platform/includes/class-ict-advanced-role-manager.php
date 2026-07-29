<?php
/**
 * Advanced Role Manager
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Advanced_Role_Manager
 *
 * Handles advanced role and permission management.
 */
class ICT_Advanced_Role_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Advanced_Role_Manager
	 */
	private static $instance = null;

	/**
	 * Custom capabilities.
	 *
	 * @var array
	 */
	private $capabilities = array(
		// Platform management
		'manage_ict_platform'         => 'Manage ICT Platform settings',
		'manage_ict_integrations'     => 'Manage integrations (Zoho, Teams, etc.)',
		'manage_ict_sync'             => 'Manage sync operations',

		// Projects
		'manage_ict_projects'         => 'Full project management access',
		'edit_ict_projects'           => 'Edit projects',
		'delete_ict_projects'         => 'Delete projects',
		'view_ict_projects'           => 'View projects',
		'create_ict_projects'         => 'Create new projects',
		'assign_ict_projects'         => 'Assign users to projects',

		// Time tracking
		'manage_ict_time_entries'     => 'Manage all time entries',
		'edit_ict_time_entry'         => 'Edit own time entries',
		'approve_ict_time_entries'    => 'Approve time entries',
		'view_all_time_entries'       => 'View all time entries',

		// Inventory
		'manage_ict_inventory'        => 'Full inventory management',
		'edit_ict_inventory'          => 'Edit inventory items',
		'view_ict_inventory'          => 'View inventory',

		// Purchase orders
		'manage_ict_purchase_orders'  => 'Manage all purchase orders',
		'create_ict_purchase_orders'  => 'Create purchase orders',
		'approve_ict_purchase_orders' => 'Approve purchase orders',
		'view_ict_purchase_orders'    => 'View purchase orders',

		// Resources
		'manage_ict_resources'        => 'Manage resource allocation',
		'view_ict_resources'          => 'View resource allocation',

		// Reports
		'view_ict_reports'            => 'View reports',
		'export_ict_reports'          => 'Export reports',
		'manage_ict_reports'          => 'Manage report schedules',

		// Tasks
		'manage_ict_tasks'            => 'Manage all tasks',
		'view_ict_tasks'              => 'View tasks',
		'edit_ict_tasks'              => 'Edit assigned tasks',

		// Users
		'manage_ict_users'            => 'Manage ICT users and roles',
		'view_ict_users'              => 'View user directory',

		// Custom fields
		'manage_custom_fields'        => 'Manage custom field definitions',

		// Notifications
		'manage_notifications'        => 'Manage notification settings',
	);

	/**
	 * Default roles.
	 *
	 * @var array
	 */
	private $default_roles = array(
		'ict_administrator'     => array(
			'display_name' => 'ICT Administrator',
			'capabilities' => 'all',
		),
		'ict_project_manager'   => array(
			'display_name' => 'ICT Project Manager',
			'capabilities' => array(
				'manage_ict_projects',
				'edit_ict_projects',
				'delete_ict_projects',
				'view_ict_projects',
				'create_ict_projects',
				'assign_ict_projects',
				'manage_ict_time_entries',
				'approve_ict_time_entries',
				'view_all_time_entries',
				'manage_ict_resources',
				'view_ict_resources',
				'view_ict_reports',
				'export_ict_reports',
				'manage_ict_tasks',
				'view_ict_tasks',
				'view_ict_users',
				'manage_ict_purchase_orders',
				'approve_ict_purchase_orders',
				'view_ict_purchase_orders',
				'view_ict_inventory',
			),
		),
		'ict_technician'        => array(
			'display_name' => 'ICT Technician',
			'capabilities' => array(
				'view_ict_projects',
				'edit_ict_time_entry',
				'view_ict_tasks',
				'edit_ict_tasks',
				'view_ict_inventory',
			),
		),
		'ict_inventory_manager' => array(
			'display_name' => 'ICT Inventory Manager',
			'capabilities' => array(
				'manage_ict_inventory',
				'edit_ict_inventory',
				'view_ict_inventory',
				'manage_ict_purchase_orders',
				'create_ict_purchase_orders',
				'view_ict_purchase_orders',
				'view_ict_reports',
			),
		),
		'ict_accountant'        => array(
			'display_name' => 'ICT Accountant',
			'capabilities' => array(
				'view_ict_projects',
				'view_all_time_entries',
				'approve_ict_time_entries',
				'view_ict_reports',
				'export_ict_reports',
				'view_ict_purchase_orders',
				'approve_ict_purchase_orders',
			),
		),
		'ict_viewer'            => array(
			'display_name' => 'ICT Viewer',
			'capabilities' => array(
				'view_ict_projects',
				'view_ict_tasks',
				'view_ict_inventory',
				'view_ict_reports',
			),
		),
	);

	/**
	 * Get singleton instance.
	 *
	 * @since  1.1.0
	 * @return ICT_Advanced_Role_Manager
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
		// Private constructor for singleton
	}

	/**
	 * Initialize the role manager.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function init() {
		add_filter( 'user_has_cap', array( $this, 'filter_user_capabilities' ), 10, 4 );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes() {
		$this->register_endpoints();
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_endpoints() {
		// Roles
		register_rest_route(
			'ict/v1',
			'/roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_roles' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/roles',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_role' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/roles/(?P<role>[a-z_]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_role' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/roles/(?P<role>[a-z_]+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_role' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/roles/(?P<role>[a-z_]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_role' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		// Capabilities
		register_rest_route(
			'ict/v1',
			'/capabilities',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_capabilities' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		// User roles
		register_rest_route(
			'ict/v1',
			'/users/(?P<user_id>\d+)/roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_roles' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/users/(?P<user_id>\d+)/roles',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_user_roles' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		// Permission groups
		register_rest_route(
			'ict/v1',
			'/permission-groups',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_permission_groups' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/permission-groups',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_permission_group' ),
				'permission_callback' => array( $this, 'can_manage_roles' ),
			)
		);
	}

	/**
	 * Check if user can manage roles.
	 *
	 * @since  1.1.0
	 * @return bool True if can manage.
	 */
	public function can_manage_roles() {
		return current_user_can( 'manage_ict_users' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get all ICT roles.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_roles( $request ) {
		global $wp_roles;

		$roles = array();

		foreach ( $wp_roles->roles as $role_id => $role ) {
			// Filter to ICT-related roles
			if ( strpos( $role_id, 'ict_' ) === 0 || $role_id === 'administrator' ) {
				$ict_caps = array_filter(
					$role['capabilities'],
					function ( $cap_val, $cap_name ) {
						return strpos( $cap_name, 'ict_' ) !== false || strpos( $cap_name, 'manage_' ) !== false;
					},
					ARRAY_FILTER_USE_BOTH
				);

				$roles[] = array(
					'id'           => $role_id,
					'name'         => $role['name'],
					'capabilities' => $ict_caps,
					'user_count'   => $this->count_users_in_role( $role_id ),
					'is_system'    => in_array( $role_id, array( 'administrator', 'editor', 'subscriber' ), true ),
				);
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'roles'   => $roles,
			),
			200
		);
	}

	/**
	 * Get single role details.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_role( $request ) {
		$role_id = $request->get_param( 'role' );
		$role    = get_role( $role_id );

		if ( ! $role ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Role not found',
				),
				404
			);
		}

		$users = get_users( array( 'role' => $role_id ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'role'    => array(
					'id'           => $role_id,
					'name'         => $this->get_role_display_name( $role_id ),
					'capabilities' => $role->capabilities,
					'users'        => array_map(
						function ( $user ) {
							return array(
								'id'           => $user->ID,
								'login'        => $user->user_login,
								'display_name' => $user->display_name,
								'email'        => $user->user_email,
							);
						},
						$users
					),
				),
			),
			200
		);
	}

	/**
	 * Create new role.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function create_role( $request ) {
		$params = $request->get_json_params();

		$role_id      = sanitize_key( 'ict_' . $params['name'] ?? '' );
		$display_name = sanitize_text_field( $params['display_name'] ?? '' );
		$capabilities = $params['capabilities'] ?? array();
		$clone_from   = $params['clone_from'] ?? null;

		if ( empty( $role_id ) || empty( $display_name ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Role name and display name are required',
				),
				400
			);
		}

		// Check if role exists
		if ( get_role( $role_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Role already exists',
				),
				400
			);
		}

		// Clone capabilities from another role
		if ( $clone_from ) {
			$source_role = get_role( $clone_from );
			if ( $source_role ) {
				$capabilities = array_merge( $source_role->capabilities, $capabilities );
			}
		}

		// Ensure only valid capabilities
		$valid_caps = array();
		foreach ( $capabilities as $cap => $grant ) {
			if ( isset( $this->capabilities[ $cap ] ) || strpos( $cap, 'ict_' ) === 0 ) {
				$valid_caps[ $cap ] = (bool) $grant;
			}
		}

		// Add read capability
		$valid_caps['read'] = true;

		$result = add_role( $role_id, $display_name, $valid_caps );

		if ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to create role',
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'role'    => array(
					'id'           => $role_id,
					'name'         => $display_name,
					'capabilities' => $valid_caps,
				),
			),
			201
		);
	}

	/**
	 * Update existing role.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function update_role( $request ) {
		$role_id = $request->get_param( 'role' );
		$params  = $request->get_json_params();
		$role    = get_role( $role_id );

		if ( ! $role ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Role not found',
				),
				404
			);
		}

		// Prevent modification of system roles
		if ( in_array( $role_id, array( 'administrator' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Cannot modify system role',
				),
				400
			);
		}

		$capabilities = $params['capabilities'] ?? array();

		// Update capabilities
		foreach ( $this->capabilities as $cap => $desc ) {
			if ( isset( $capabilities[ $cap ] ) ) {
				if ( $capabilities[ $cap ] ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'role'    => array(
					'id'           => $role_id,
					'capabilities' => $role->capabilities,
				),
			),
			200
		);
	}

	/**
	 * Delete role.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function delete_role( $request ) {
		$role_id = $request->get_param( 'role' );

		// Prevent deletion of default roles
		if ( isset( $this->default_roles[ $role_id ] ) || in_array( $role_id, array( 'administrator', 'editor', 'subscriber' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Cannot delete default role',
				),
				400
			);
		}

		// Check if users have this role
		$users = get_users( array( 'role' => $role_id ) );

		if ( ! empty( $users ) ) {
			return new WP_REST_Response(
				array(
					'success'    => false,
					'error'      => 'Role has assigned users',
					'user_count' => count( $users ),
				),
				400
			);
		}

		remove_role( $role_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Role deleted',
			),
			200
		);
	}

	/**
	 * Get all ICT capabilities.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_capabilities( $request ) {
		$grouped = $this->get_grouped_capabilities();

		return new WP_REST_Response(
			array(
				'success'      => true,
				'capabilities' => $this->capabilities,
				'grouped'      => $grouped,
			),
			200
		);
	}

	/**
	 * Get capabilities grouped by category.
	 *
	 * @since  1.1.0
	 * @return array Grouped capabilities.
	 */
	private function get_grouped_capabilities() {
		return array(
			'platform'      => array(
				'label'        => __( 'Platform Management', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_platform',
					'manage_ict_integrations',
					'manage_ict_sync',
				),
			),
			'projects'      => array(
				'label'        => __( 'Projects', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_projects',
					'edit_ict_projects',
					'delete_ict_projects',
					'view_ict_projects',
					'create_ict_projects',
					'assign_ict_projects',
				),
			),
			'time_tracking' => array(
				'label'        => __( 'Time Tracking', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_time_entries',
					'edit_ict_time_entry',
					'approve_ict_time_entries',
					'view_all_time_entries',
				),
			),
			'inventory'     => array(
				'label'        => __( 'Inventory', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_inventory',
					'edit_ict_inventory',
					'view_ict_inventory',
				),
			),
			'procurement'   => array(
				'label'        => __( 'Purchase Orders', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_purchase_orders',
					'create_ict_purchase_orders',
					'approve_ict_purchase_orders',
					'view_ict_purchase_orders',
				),
			),
			'resources'     => array(
				'label'        => __( 'Resources', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_resources',
					'view_ict_resources',
				),
			),
			'reports'       => array(
				'label'        => __( 'Reports', 'ict-platform' ),
				'capabilities' => array(
					'view_ict_reports',
					'export_ict_reports',
					'manage_ict_reports',
				),
			),
			'tasks'         => array(
				'label'        => __( 'Tasks', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_tasks',
					'view_ict_tasks',
					'edit_ict_tasks',
				),
			),
			'users'         => array(
				'label'        => __( 'Users', 'ict-platform' ),
				'capabilities' => array(
					'manage_ict_users',
					'view_ict_users',
				),
			),
			'other'         => array(
				'label'        => __( 'Other', 'ict-platform' ),
				'capabilities' => array(
					'manage_custom_fields',
					'manage_notifications',
				),
			),
		);
	}

	/**
	 * Get user roles.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_user_roles( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'User not found',
				),
				404
			);
		}

		$all_caps = array();
		foreach ( $user->allcaps as $cap => $granted ) {
			if ( $granted && ( isset( $this->capabilities[ $cap ] ) || strpos( $cap, 'ict_' ) !== false ) ) {
				$all_caps[] = $cap;
			}
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'user_id'      => $user_id,
				'roles'        => $user->roles,
				'capabilities' => $all_caps,
			),
			200
		);
	}

	/**
	 * Update user roles.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function update_user_roles( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$params  = $request->get_json_params();
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'User not found',
				),
				404
			);
		}

		$roles = $params['roles'] ?? array();

		// Remove all ICT roles
		foreach ( $user->roles as $role ) {
			if ( strpos( $role, 'ict_' ) === 0 ) {
				$user->remove_role( $role );
			}
		}

		// Add new roles
		foreach ( $roles as $role ) {
			if ( strpos( $role, 'ict_' ) === 0 || in_array( $role, array( 'administrator' ), true ) ) {
				$user->add_role( $role );
			}
		}

		// Apply additional capabilities if specified
		if ( isset( $params['additional_capabilities'] ) ) {
			foreach ( $params['additional_capabilities'] as $cap => $grant ) {
				if ( isset( $this->capabilities[ $cap ] ) ) {
					if ( $grant ) {
						$user->add_cap( $cap );
					} else {
						$user->remove_cap( $cap );
					}
				}
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'user_id' => $user_id,
				'roles'   => $user->roles,
			),
			200
		);
	}

	/**
	 * Get permission groups.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_permission_groups( $request ) {
		$groups = get_option( 'ict_permission_groups', array() );

		return new WP_REST_Response(
			array(
				'success' => true,
				'groups'  => $groups,
			),
			200
		);
	}

	/**
	 * Create permission group.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function create_permission_group( $request ) {
		$params = $request->get_json_params();

		$group_id     = sanitize_key( $params['name'] ?? '' );
		$display_name = sanitize_text_field( $params['display_name'] ?? '' );
		$capabilities = $params['capabilities'] ?? array();

		if ( empty( $group_id ) || empty( $display_name ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Group name and display name are required',
				),
				400
			);
		}

		$groups = get_option( 'ict_permission_groups', array() );

		$groups[ $group_id ] = array(
			'name'         => $display_name,
			'capabilities' => $capabilities,
			'created_at'   => current_time( 'mysql' ),
		);

		update_option( 'ict_permission_groups', $groups );

		return new WP_REST_Response(
			array(
				'success' => true,
				'group'   => $groups[ $group_id ],
			),
			201
		);
	}

	/**
	 * Filter user capabilities dynamically.
	 *
	 * @since  1.1.0
	 * @param  array   $allcaps All capabilities.
	 * @param  array   $caps    Required capabilities.
	 * @param  array   $args    Arguments.
	 * @param  WP_User $user    User object.
	 * @return array Modified capabilities.
	 */
	public function filter_user_capabilities( $allcaps, $caps, $args, $user ) {
		// Check for project-specific permissions
		if ( isset( $args[2] ) && in_array( $args[0], array( 'edit_ict_project', 'view_ict_project' ), true ) ) {
			$project_id = $args[2];
			$allcaps    = $this->check_project_permission( $allcaps, $user->ID, $project_id, $args[0] );
		}

		// Check for time-based permissions (e.g., can only edit today's entries)
		if ( $args[0] === 'edit_ict_time_entry' && isset( $args[2] ) ) {
			$allcaps = $this->check_time_entry_permission( $allcaps, $user->ID, $args[2] );
		}

		return $allcaps;
	}

	/**
	 * Check project-specific permission.
	 *
	 * @since  1.1.0
	 * @param  array  $allcaps    All capabilities.
	 * @param  int    $user_id    User ID.
	 * @param  int    $project_id Project ID.
	 * @param  string $capability Capability being checked.
	 * @return array Modified capabilities.
	 */
	private function check_project_permission( $allcaps, $user_id, $project_id, $capability ) {
		global $wpdb;

		// Check if user is project manager
		$is_pm = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d AND project_manager_id = %d',
				$project_id,
				$user_id
			)
		);

		if ( $is_pm ) {
			$allcaps['edit_ict_project'] = true;
			$allcaps['view_ict_project'] = true;
		}

		// Check if user is assigned to project
		$is_assigned = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . ICT_PROJECT_RESOURCES_TABLE . "
				WHERE project_id = %d AND resource_type = 'user' AND resource_id = %d",
				$project_id,
				$user_id
			)
		);

		if ( $is_assigned ) {
			$allcaps['view_ict_project'] = true;
		}

		return $allcaps;
	}

	/**
	 * Check time entry permission.
	 *
	 * @since  1.1.0
	 * @param  array $allcaps       All capabilities.
	 * @param  int   $user_id       User ID.
	 * @param  int   $time_entry_id Time entry ID.
	 * @return array Modified capabilities.
	 */
	private function check_time_entry_permission( $allcaps, $user_id, $time_entry_id ) {
		global $wpdb;

		// Get time entry
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT technician_id, clock_in, status FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$time_entry_id
			)
		);

		if ( ! $entry ) {
			return $allcaps;
		}

		// Can only edit own entries
		if ( (int) $entry->technician_id !== $user_id ) {
			$allcaps['edit_ict_time_entry'] = false;
			return $allcaps;
		}

		// Can only edit entries from today (unless approved)
		if ( $entry->status === 'approved' ) {
			$allcaps['edit_ict_time_entry'] = false;
		} elseif ( date( 'Y-m-d', strtotime( $entry->clock_in ) ) !== date( 'Y-m-d' ) ) {
			// Allow editing for 24 hours
			$entry_time = strtotime( $entry->clock_in );
			$now        = current_time( 'timestamp' );

			if ( ( $now - $entry_time ) > DAY_IN_SECONDS ) {
				$allcaps['edit_ict_time_entry'] = false;
			}
		}

		return $allcaps;
	}

	/**
	 * Create default roles on activation.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function create_default_roles() {
		// Add capabilities to administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $this->capabilities as $cap => $desc ) {
				$admin->add_cap( $cap );
			}
		}

		// Create custom roles
		foreach ( $this->default_roles as $role_id => $role_data ) {
			if ( get_role( $role_id ) ) {
				continue; // Skip if exists
			}

			$caps = array( 'read' => true );

			if ( $role_data['capabilities'] === 'all' ) {
				foreach ( $this->capabilities as $cap => $desc ) {
					$caps[ $cap ] = true;
				}
			} else {
				foreach ( $role_data['capabilities'] as $cap ) {
					$caps[ $cap ] = true;
				}
			}

			add_role( $role_id, $role_data['display_name'], $caps );
		}
	}

	/**
	 * Count users in role.
	 *
	 * @since  1.1.0
	 * @param  string $role Role ID.
	 * @return int User count.
	 */
	private function count_users_in_role( $role ) {
		$users = get_users(
			array(
				'role'   => $role,
				'fields' => 'ID',
			)
		);

		return count( $users );
	}

	/**
	 * Get role display name.
	 *
	 * @since  1.1.0
	 * @param  string $role_id Role ID.
	 * @return string Display name.
	 */
	private function get_role_display_name( $role_id ) {
		global $wp_roles;

		if ( isset( $wp_roles->roles[ $role_id ] ) ) {
			return $wp_roles->roles[ $role_id ]['name'];
		}

		return $role_id;
	}
}
