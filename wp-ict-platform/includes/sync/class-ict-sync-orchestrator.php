<?php
/**
 * Sync Orchestrator
 *
 * Orchestrates bidirectional sync between QuoteWerks, WordPress, and Zoho services.
 * Ensures data consistency across all platforms.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Sync_Orchestrator
 *
 * Manages synchronization workflows across multiple platforms.
 */
class ICT_Sync_Orchestrator {

	/**
	 * QuoteWerks adapter.
	 *
	 * @var ICT_QuoteWerks_Adapter
	 */
	private $quotewerks;

	/**
	 * Zoho CRM adapter.
	 *
	 * @var ICT_Zoho_CRM_Adapter
	 */
	private $zoho_crm;

	/**
	 * Zoho Books adapter.
	 *
	 * @var ICT_Zoho_Books_Adapter
	 */
	private $zoho_books;

	/**
	 * Zoho FSM adapter.
	 *
	 * @var ICT_Zoho_FSM_Adapter
	 */
	private $zoho_fsm;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->quotewerks = new ICT_QuoteWerks_Adapter();
		$this->zoho_crm   = new ICT_Zoho_CRM_Adapter();
		$this->zoho_books = new ICT_Zoho_Books_Adapter();
		$this->zoho_fsm   = new ICT_Zoho_FSM_Adapter();
	}

	/**
	 * Full sync workflow: QuoteWerks → WordPress → Zoho.
	 *
	 * @param string $quote_id QuoteWerks quote ID.
	 * @return array Sync results.
	 */
	public function full_quote_sync( $quote_id ) {
		$results = array(
			'quotewerks_to_wordpress' => null,
			'wordpress_to_zoho_crm'   => null,
			'wordpress_to_zoho_fsm'   => null,
			'success'                 => false,
			'errors'                  => array(),
		);

		// Step 1: QuoteWerks → WordPress
		$project_id = $this->quotewerks->sync_quote_to_project( $quote_id );

		if ( is_wp_error( $project_id ) ) {
			$results['errors'][] = 'QuoteWerks to WordPress: ' . $project_id->get_error_message();
			return $results;
		}

		$results['quotewerks_to_wordpress'] = $project_id;

		// Step 2: WordPress → Zoho CRM (Deal)
		$crm_result = $this->sync_project_to_zoho_crm( $project_id );

		if ( is_wp_error( $crm_result ) ) {
			$results['errors'][] = 'WordPress to Zoho CRM: ' . $crm_result->get_error_message();
		} else {
			$results['wordpress_to_zoho_crm'] = $crm_result;
		}

		// Step 3: WordPress → Zoho FSM (Work Order)
		$fsm_result = $this->sync_project_to_zoho_fsm( $project_id );

		if ( is_wp_error( $fsm_result ) ) {
			$results['errors'][] = 'WordPress to Zoho FSM: ' . $fsm_result->get_error_message();
		} else {
			$results['wordpress_to_zoho_fsm'] = $fsm_result;
		}

		$results['success'] = empty( $results['errors'] );

		return $results;
	}

	/**
	 * Sync project to Zoho CRM.
	 *
	 * @param int $project_id Project ID.
	 * @return string|WP_Error Deal ID or error.
	 */
	private function sync_project_to_zoho_crm( $project_id ) {
		global $wpdb;

		$project = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d',
				$project_id
			),
			ARRAY_A
		);

		if ( ! $project ) {
			return new WP_Error( 'project_not_found', 'Project not found' );
		}

		// Map to CRM deal format
		$deal_data = array(
			'Deal_Name'    => $project['name'],
			'Stage'        => $this->map_status_to_crm_stage( $project['status'] ),
			'Amount'       => $project['budget_amount'],
			'Closing_Date' => $project['end_date'] ?? date( 'Y-m-d', strtotime( '+30 days' ) ),
			'Description'  => $project['description'],
		);

		// Check if already synced
		if ( ! empty( $project['zoho_crm_id'] ) ) {
			// Update existing deal
			$result = $this->zoho_crm->update( 'Deals', $project['zoho_crm_id'], $deal_data );
		} else {
			// Create new deal
			$result = $this->zoho_crm->create( 'Deals', $deal_data );

			if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
				// Save CRM ID to project
				$wpdb->update(
					ICT_PROJECTS_TABLE,
					array(
						'zoho_crm_id'      => $result['id'],
						'zoho_crm_sync_at' => current_time( 'mysql' ),
					),
					array( 'id' => $project_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		return $result;
	}

	/**
	 * Sync project to Zoho FSM.
	 *
	 * @param int $project_id Project ID.
	 * @return string|WP_Error Work Order ID or error.
	 */
	private function sync_project_to_zoho_fsm( $project_id ) {
		global $wpdb;

		$project = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d',
				$project_id
			),
			ARRAY_A
		);

		if ( ! $project ) {
			return new WP_Error( 'project_not_found', 'Project not found' );
		}

		// Map to FSM work order format
		$wo_data = array(
			'Subject'         => $project['name'],
			'Status'          => $this->map_status_to_fsm_status( $project['status'] ),
			'Priority'        => ucfirst( $project['priority'] ),
			'Scheduled_Start' => $project['start_date'],
			'Scheduled_End'   => $project['end_date'],
			'Description'     => $project['description'],
		);

		// Check if already synced
		if ( ! empty( $project['zoho_fsm_id'] ) ) {
			$result = $this->zoho_fsm->update( 'WorkOrders', $project['zoho_fsm_id'], $wo_data );
		} else {
			$result = $this->zoho_fsm->create( 'WorkOrders', $wo_data );

			if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
				$wpdb->update(
					ICT_PROJECTS_TABLE,
					array(
						'zoho_fsm_id'      => $result['id'],
						'zoho_fsm_sync_at' => current_time( 'mysql' ),
					),
					array( 'id' => $project_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		return $result;
	}

	/**
	 * Sync inventory item to Zoho Books.
	 *
	 * @param int $item_id Inventory item ID.
	 * @return string|WP_Error Item ID or error.
	 */
	public function sync_inventory_to_zoho_books( $item_id ) {
		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_INVENTORY_ITEMS_TABLE . ' WHERE id = %d',
				$item_id
			),
			ARRAY_A
		);

		if ( ! $item ) {
			return new WP_Error( 'item_not_found', 'Inventory item not found' );
		}

		$item_data = array(
			'name'        => $item['name'],
			'sku'         => $item['sku'],
			'unit'        => $item['unit'] ?? 'pcs',
			'rate'        => $item['unit_price'],
			'description' => $item['description'] ?? '',
			'stock'       => $item['quantity'],
		);

		if ( ! empty( $item['zoho_books_id'] ) ) {
			$result = $this->zoho_books->update( 'items', $item['zoho_books_id'], $item_data );
		} else {
			$result = $this->zoho_books->create( 'items', $item_data );

			if ( ! is_wp_error( $result ) && ! empty( $result['item_id'] ) ) {
				$wpdb->update(
					ICT_INVENTORY_ITEMS_TABLE,
					array(
						'zoho_books_id'      => $result['item_id'],
						'zoho_books_sync_at' => current_time( 'mysql' ),
					),
					array( 'id' => $item_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		return $result;
	}

	/**
	 * Test all integrations.
	 *
	 * @return array Test results.
	 */
	public function test_all_connections() {
		$results = array(
			'quotewerks' => $this->quotewerks->test_connection(),
			'zoho_crm'   => $this->zoho_crm->test_connection(),
			'zoho_books' => $this->zoho_books->test_connection(),
			'zoho_fsm'   => $this->zoho_fsm->test_connection(),
		);

		$all_success = true;
		foreach ( $results as $service => $result ) {
			if ( is_wp_error( $result ) ) {
				$all_success         = false;
				$results[ $service ] = array(
					'success' => false,
					'error'   => $result->get_error_message(),
				);
			} else {
				$results[ $service ] = array( 'success' => true );
			}
		}

		$results['all_connected'] = $all_success;

		return $results;
	}

	/**
	 * Map project status to CRM stage.
	 *
	 * @param string $status Project status.
	 * @return string CRM stage.
	 */
	private function map_status_to_crm_stage( $status ) {
		$stage_map = array(
			'planning'    => 'Qualification',
			'in-progress' => 'Proposal/Price Quote',
			'on-hold'     => 'Negotiation/Review',
			'completed'   => 'Closed Won',
			'cancelled'   => 'Closed Lost',
		);

		return $stage_map[ $status ] ?? 'Qualification';
	}

	/**
	 * Map project status to FSM status.
	 *
	 * @param string $status Project status.
	 * @return string FSM status.
	 */
	private function map_status_to_fsm_status( $status ) {
		$status_map = array(
			'planning'    => 'Scheduled',
			'in-progress' => 'In Progress',
			'on-hold'     => 'On Hold',
			'completed'   => 'Completed',
			'cancelled'   => 'Cancelled',
		);

		return $status_map[ $status ] ?? 'Scheduled';
	}

	/**
	 * Get sync health status.
	 *
	 * @return array Health status.
	 */
	public function get_sync_health() {
		global $wpdb;

		// Get failed syncs in last 24 hours
		$failed_syncs = $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . ICT_SYNC_LOG_TABLE . "
			WHERE status = 'error'
			AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		// Get pending queue items
		$pending_queue = $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . ICT_SYNC_QUEUE_TABLE . "
			WHERE status = 'pending'"
		);

		// Get last successful sync
		$last_sync = $wpdb->get_var(
			'SELECT MAX(created_at) FROM ' . ICT_SYNC_LOG_TABLE . "
			WHERE status = 'success'"
		);

		$health_status = 'healthy';
		if ( $failed_syncs > 10 ) {
			$health_status = 'critical';
		} elseif ( $failed_syncs > 3 || $pending_queue > 50 ) {
			$health_status = 'warning';
		}

		return array(
			'status'               => $health_status,
			'failed_syncs_24h'     => (int) $failed_syncs,
			'pending_queue_items'  => (int) $pending_queue,
			'last_successful_sync' => $last_sync,
			'connections'          => $this->test_all_connections(),
		);
	}
}
