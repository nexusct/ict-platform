<?php
/**
 * Project Status taxonomy
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Taxonomy_Project_Status
 *
 * Registers the Project Status taxonomy.
 */
class ICT_Taxonomy_Project_Status {

	/**
	 * Register the taxonomy.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register() {
		$labels = array(
			'name'                       => _x( 'Project Statuses', 'Taxonomy General Name', 'ict-platform' ),
			'singular_name'              => _x( 'Project Status', 'Taxonomy Singular Name', 'ict-platform' ),
			'menu_name'                  => __( 'Status', 'ict-platform' ),
			'all_items'                  => __( 'All Statuses', 'ict-platform' ),
			'parent_item'                => __( 'Parent Status', 'ict-platform' ),
			'parent_item_colon'          => __( 'Parent Status:', 'ict-platform' ),
			'new_item_name'              => __( 'New Status Name', 'ict-platform' ),
			'add_new_item'               => __( 'Add New Status', 'ict-platform' ),
			'edit_item'                  => __( 'Edit Status', 'ict-platform' ),
			'update_item'                => __( 'Update Status', 'ict-platform' ),
			'view_item'                  => __( 'View Status', 'ict-platform' ),
			'separate_items_with_commas' => __( 'Separate statuses with commas', 'ict-platform' ),
			'add_or_remove_items'        => __( 'Add or remove statuses', 'ict-platform' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'ict-platform' ),
			'popular_items'              => __( 'Popular Statuses', 'ict-platform' ),
			'search_items'               => __( 'Search Statuses', 'ict-platform' ),
			'not_found'                  => __( 'Not Found', 'ict-platform' ),
			'no_terms'                   => __( 'No statuses', 'ict-platform' ),
			'items_list'                 => __( 'Statuses list', 'ict-platform' ),
			'items_list_navigation'      => __( 'Statuses list navigation', 'ict-platform' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'rest_base'         => 'project-status',
		);

		register_taxonomy( 'project_status', array( 'ict_project' ), $args );

		// Add default terms
		$this->add_default_terms();
	}

	/**
	 * Add default project status terms.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_default_terms() {
		$default_statuses = array(
			'pending'     => __( 'Pending', 'ict-platform' ),
			'in-progress' => __( 'In Progress', 'ict-platform' ),
			'on-hold'     => __( 'On Hold', 'ict-platform' ),
			'completed'   => __( 'Completed', 'ict-platform' ),
			'cancelled'   => __( 'Cancelled', 'ict-platform' ),
		);

		foreach ( $default_statuses as $slug => $name ) {
			if ( ! term_exists( $slug, 'project_status' ) ) {
				wp_insert_term( $name, 'project_status', array( 'slug' => $slug ) );
			}
		}
	}
}
