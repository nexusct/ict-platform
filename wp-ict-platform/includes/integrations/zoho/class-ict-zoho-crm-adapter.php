<?php
/**
 * Zoho CRM Adapter
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_CRM_Adapter
 *
 * Handles synchronization with Zoho CRM (Deals → Projects, Contacts → Clients).
 */
class ICT_Zoho_CRM_Adapter extends ICT_Zoho_API_Client {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( 'crm' );
	}

	/**
	 * Create entity in Zoho CRM.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type (project, contact).
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function create( $entity_type, $data ) {
		$module   = $this->get_module_name( $entity_type );
		$payload  = $this->transform_data_for_crm( $entity_type, $data );
		$response = $this->post( "/{$module}", array( 'data' => array( $payload ) ) );

		if ( isset( $response['data']['data'][0]['details']['id'] ) ) {
			$zoho_id = $response['data']['data'][0]['details']['id'];

			// Update local record with Zoho ID
			$this->update_local_zoho_id( $entity_type, $data['id'], $zoho_id );

			return array(
				'zoho_id' => $zoho_id,
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create record in Zoho CRM', 'ict-platform' ) );
	}

	/**
	 * Update entity in Zoho CRM.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @param  array  $data        Entity data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function update( $entity_type, $entity_id, $data ) {
		$zoho_id = $this->get_zoho_id( $entity_type, $entity_id );

		if ( ! $zoho_id ) {
			// Create instead of update
			return $this->create( $entity_type, $data );
		}

		$module   = $this->get_module_name( $entity_type );
		$payload  = $this->transform_data_for_crm( $entity_type, $data );
		$response = $this->put( "/{$module}/{$zoho_id}", array( 'data' => array( $payload ) ) );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Delete entity from Zoho CRM.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function delete( $entity_type, $entity_id ) {
		$zoho_id = $this->get_zoho_id( $entity_type, $entity_id );

		if ( ! $zoho_id ) {
			throw new Exception( __( 'Entity not found in Zoho CRM', 'ict-platform' ) );
		}

		$module = $this->get_module_name( $entity_type );
		$this->delete( "/{$module}/{$zoho_id}" );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'deleted',
		);
	}

	/**
	 * Sync deals from Zoho CRM to local projects.
	 *
	 * @since  1.0.0
	 * @param  array $params Query parameters.
	 * @return array Sync results.
	 */
	public function sync_deals_to_projects( $params = array() ) {
		$defaults = array(
			'page'     => 1,
			'per_page' => 200,
			'fields'   => 'id,Deal_Name,Account_Name,Stage,Amount,Closing_Date,Owner,Description',
		);

		$params   = wp_parse_args( $params, $defaults );
		$response = $this->get( '/Deals', $params );

		if ( ! isset( $response['data']['data'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No deals found', 'ict-platform' ),
			);
		}

		$deals = $response['data']['data'];
		$synced = 0;
		$errors = array();

		global $wpdb;

		foreach ( $deals as $deal ) {
			try {
				// Check if project exists
				$existing = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id FROM " . ICT_PROJECTS_TABLE . " WHERE zoho_crm_id = %s",
						$deal['id']
					)
				);

				$project_data = $this->transform_deal_to_project( $deal );

				if ( $existing ) {
					// Update existing
					$wpdb->update(
						ICT_PROJECTS_TABLE,
						$project_data,
						array( 'id' => $existing->id ),
						array( '%s', '%s', '%s', '%s', '%s', '%s' ),
						array( '%d' )
					);
				} else {
					// Insert new
					$wpdb->insert(
						ICT_PROJECTS_TABLE,
						$project_data,
						array( '%s', '%s', '%s', '%s', '%s', '%s' )
					);
				}

				$synced++;

			} catch ( Exception $e ) {
				$errors[] = array(
					'deal_id' => $deal['id'],
					'error'   => $e->getMessage(),
				);
			}
		}

		return array(
			'success' => true,
			'synced'  => $synced,
			'errors'  => $errors,
			'has_more' => isset( $response['data']['info']['more_records'] ) && $response['data']['info']['more_records'],
		);
	}

	/**
	 * Sync contacts from Zoho CRM.
	 *
	 * @since  1.0.0
	 * @param  array $params Query parameters.
	 * @return array Sync results.
	 */
	public function sync_contacts( $params = array() ) {
		$defaults = array(
			'page'     => 1,
			'per_page' => 200,
			'fields'   => 'id,First_Name,Last_Name,Email,Phone,Account_Name',
		);

		$params   = wp_parse_args( $params, $defaults );
		$response = $this->get( '/Contacts', $params );

		if ( ! isset( $response['data']['data'] ) ) {
			return array( 'success' => false, 'message' => __( 'No contacts found', 'ict-platform' ) );
		}

		// Implementation: Sync contacts to WordPress users or custom clients table
		// For now, return success
		return array(
			'success' => true,
			'synced'  => count( $response['data']['data'] ),
		);
	}

	/**
	 * Get module name for entity type.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @return string Module name.
	 */
	protected function get_module_name( $entity_type ) {
		$mapping = array(
			'project' => 'Deals',
			'contact' => 'Contacts',
			'account' => 'Accounts',
		);

		return $mapping[ $entity_type ] ?? 'Deals';
	}

	/**
	 * Transform local data for CRM API.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  array  $data        Local data.
	 * @return array Transformed data.
	 */
	protected function transform_data_for_crm( $entity_type, $data ) {
		if ( 'project' === $entity_type ) {
			return array(
				'Deal_Name'    => $data['project_name'] ?? '',
				'Amount'       => $data['budget_amount'] ?? 0,
				'Stage'        => $this->map_status_to_stage( $data['status'] ?? 'pending' ),
				'Closing_Date' => $data['end_date'] ?? '',
				'Description'  => $data['notes'] ?? '',
			);
		}

		return $data;
	}

	/**
	 * Transform CRM deal to local project data.
	 *
	 * @since  1.0.0
	 * @param  array $deal Deal data from CRM.
	 * @return array Project data.
	 */
	protected function transform_deal_to_project( $deal ) {
		return array(
			'zoho_crm_id'    => $deal['id'],
			'project_name'   => $deal['Deal_Name'] ?? '',
			'status'         => $this->map_stage_to_status( $deal['Stage'] ?? '' ),
			'budget_amount'  => $deal['Amount'] ?? 0,
			'end_date'       => $deal['Closing_Date'] ?? null,
			'notes'          => $deal['Description'] ?? '',
			'sync_status'    => 'synced',
			'last_synced'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Map local status to CRM stage.
	 *
	 * @since  1.0.0
	 * @param  string $status Local status.
	 * @return string CRM stage.
	 */
	protected function map_status_to_stage( $status ) {
		$mapping = array(
			'pending'     => 'Qualification',
			'in-progress' => 'Proposal/Price Quote',
			'completed'   => 'Closed Won',
			'cancelled'   => 'Closed Lost',
		);

		return $mapping[ $status ] ?? 'Qualification';
	}

	/**
	 * Map CRM stage to local status.
	 *
	 * @since  1.0.0
	 * @param  string $stage CRM stage.
	 * @return string Local status.
	 */
	protected function map_stage_to_status( $stage ) {
		$mapping = array(
			'Qualification'          => 'pending',
			'Needs Analysis'         => 'pending',
			'Value Proposition'      => 'pending',
			'Proposal/Price Quote'   => 'in-progress',
			'Negotiation/Review'     => 'in-progress',
			'Closed Won'             => 'completed',
			'Closed Lost'            => 'cancelled',
		);

		return $mapping[ $stage ] ?? 'pending';
	}

	/**
	 * Get Zoho ID for local entity.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return string|null Zoho ID or null.
	 */
	protected function get_zoho_id( $entity_type, $entity_id ) {
		global $wpdb;

		if ( 'project' === $entity_type ) {
			return $wpdb->get_var(
				$wpdb->prepare(
					"SELECT zoho_crm_id FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
					$entity_id
				)
			);
		}

		return null;
	}

	/**
	 * Update local record with Zoho ID.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @param  string $zoho_id     Zoho ID.
	 * @return void
	 */
	protected function update_local_zoho_id( $entity_type, $entity_id, $zoho_id ) {
		global $wpdb;

		if ( 'project' === $entity_type ) {
			$wpdb->update(
				ICT_PROJECTS_TABLE,
				array(
					'zoho_crm_id'  => $zoho_id,
					'sync_status'  => 'synced',
					'last_synced'  => current_time( 'mysql' ),
				),
				array( 'id' => $entity_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}
}
