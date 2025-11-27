<?php
/**
 * Project custom post type
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_PostType_Project
 *
 * Registers the Project custom post type.
 */
class ICT_PostType_Project {

	/**
	 * Register the custom post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Projects', 'Post Type General Name', 'ict-platform' ),
			'singular_name'         => _x( 'Project', 'Post Type Singular Name', 'ict-platform' ),
			'menu_name'             => __( 'Projects', 'ict-platform' ),
			'name_admin_bar'        => __( 'Project', 'ict-platform' ),
			'archives'              => __( 'Project Archives', 'ict-platform' ),
			'attributes'            => __( 'Project Attributes', 'ict-platform' ),
			'parent_item_colon'     => __( 'Parent Project:', 'ict-platform' ),
			'all_items'             => __( 'All Projects', 'ict-platform' ),
			'add_new_item'          => __( 'Add New Project', 'ict-platform' ),
			'add_new'               => __( 'Add New', 'ict-platform' ),
			'new_item'              => __( 'New Project', 'ict-platform' ),
			'edit_item'             => __( 'Edit Project', 'ict-platform' ),
			'update_item'           => __( 'Update Project', 'ict-platform' ),
			'view_item'             => __( 'View Project', 'ict-platform' ),
			'view_items'            => __( 'View Projects', 'ict-platform' ),
			'search_items'          => __( 'Search Project', 'ict-platform' ),
			'not_found'             => __( 'Not found', 'ict-platform' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ict-platform' ),
			'featured_image'        => __( 'Featured Image', 'ict-platform' ),
			'set_featured_image'    => __( 'Set featured image', 'ict-platform' ),
			'remove_featured_image' => __( 'Remove featured image', 'ict-platform' ),
			'use_featured_image'    => __( 'Use as featured image', 'ict-platform' ),
			'insert_into_item'      => __( 'Insert into project', 'ict-platform' ),
			'uploaded_to_this_item' => __( 'Uploaded to this project', 'ict-platform' ),
			'items_list'            => __( 'Projects list', 'ict-platform' ),
			'items_list_navigation' => __( 'Projects list navigation', 'ict-platform' ),
			'filter_items_list'     => __( 'Filter projects list', 'ict-platform' ),
		);

		$args = array(
			'label'               => __( 'Project', 'ict-platform' ),
			'description'         => __( 'ICT Projects', 'ict-platform' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ),
			'taxonomies'          => array( 'project_status', 'project_type', 'client_tier' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // We handle menu in our own admin menu
			'menu_position'       => 30,
			'menu_icon'           => 'dashicons-portfolio',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
			'rest_base'           => 'ict-projects',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		);

		register_post_type( 'ict_project', $args );

		// Disable Divi Builder for ICT projects - they use custom React admin UI
		add_filter( 'et_builder_post_types', array( $this, 'exclude_from_divi_builder' ) );
		add_filter( 'et_builder_enabled_for_post', array( $this, 'disable_divi_for_ict_projects' ), 10, 2 );
	}

	/**
	 * Exclude ICT post types from Divi Builder post types.
	 *
	 * @since  1.0.0
	 * @param  array $post_types Array of post types.
	 * @return array
	 */
	public function exclude_from_divi_builder( $post_types ) {
		// Remove ict_project from Divi builder-enabled post types
		$exclude = array( 'ict_project' );

		return array_diff( $post_types, $exclude );
	}

	/**
	 * Disable Divi builder for individual ICT project posts.
	 *
	 * @since  1.0.0
	 * @param  bool     $enabled  Whether builder is enabled.
	 * @param  int|null $post_id  Post ID.
	 * @return bool
	 */
	public function disable_divi_for_ict_projects( $enabled, $post_id ) {
		if ( ! $post_id ) {
			return $enabled;
		}

		$post_type = get_post_type( $post_id );

		// Disable for all ICT Platform post types
		$ict_post_types = array( 'ict_project' );

		if ( in_array( $post_type, $ict_post_types, true ) ) {
			return false;
		}

		return $enabled;
	}
}
