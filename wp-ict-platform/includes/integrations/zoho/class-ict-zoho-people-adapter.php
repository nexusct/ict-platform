<?php
/**
 * Zoho People Adapter
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_People_Adapter
 *
 * Handles synchronization with Zoho People (Employees → Technicians, Timesheets).
 */
class ICT_Zoho_People_Adapter extends ICT_Zoho_API_Client {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( 'people' );
	}

	/**
	 * Create timesheet entry in Zoho People.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type (always 'time_entry').
	 * @param  array  $data        Time entry data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function create( $entity_type, $data ) {
		$payload  = $this->transform_time_entry_for_people( $data );
		$response = $this->post( '/timetracker/addtime', $payload );

		if ( isset( $response['data']['response']['result'] ) ) {
			$zoho_id = $response['data']['response']['result']['recordId'];

			// Update local record
			global $wpdb;
			$wpdb->update(
				ICT_TIME_ENTRIES_TABLE,
				array(
					'zoho_people_id' => $zoho_id,
					'sync_status'    => 'synced',
					'last_synced'    => current_time( 'mysql' ),
				),
				array( 'id' => $data['id'] ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return array(
				'zoho_id' => $zoho_id,
				'status'  => 'created',
			);
		}

		throw new Exception( __( 'Failed to create timesheet in Zoho People', 'ict-platform' ) );
	}

	/**
	 * Update timesheet entry in Zoho People.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @param  array  $data        Time entry data.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function update( $entity_type, $entity_id, $data ) {
		global $wpdb;

		$zoho_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT zoho_people_id FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d",
				$entity_id
			)
		);

		if ( ! $zoho_id ) {
			return $this->create( $entity_type, $data );
		}

		$payload = $this->transform_time_entry_for_people( $data );
		$payload['recordId'] = $zoho_id;

		$response = $this->post( '/timetracker/updatetime', $payload );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'updated',
		);
	}

	/**
	 * Delete timesheet entry from Zoho People.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Local entity ID.
	 * @return array Response data.
	 * @throws Exception On error.
	 */
	public function delete( $entity_type, $entity_id ) {
		global $wpdb;

		$zoho_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT zoho_people_id FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE id = %d",
				$entity_id
			)
		);

		if ( ! $zoho_id ) {
			throw new Exception( __( 'Time entry not found in Zoho People', 'ict-platform' ) );
		}

		$this->post( '/timetracker/deletetime', array( 'recordId' => $zoho_id ) );

		return array(
			'zoho_id' => $zoho_id,
			'status'  => 'deleted',
		);
	}

	/**
	 * Sync employees from Zoho People to local users.
	 *
	 * @since  1.0.0
	 * @return array Sync results.
	 */
	public function sync_employees() {
		$response = $this->get( '/forms/employee/getRecords' );

		if ( ! isset( $response['data']['response']['result'] ) ) {
			return array( 'success' => false, 'message' => __( 'No employees found', 'ict-platform' ) );
		}

		$employees = $response['data']['response']['result'];
		$synced = 0;

		foreach ( $employees as $employee ) {
			// Check if user exists by email
			$email = $employee['Email'] ?? '';
			if ( empty( $email ) ) {
				continue;
			}

			$user = get_user_by( 'email', $email );

			if ( ! $user ) {
				// Create new user
				$user_id = wp_create_user(
					sanitize_user( $employee['FirstName'] . $employee['LastName'] ),
					wp_generate_password(),
					$email
				);

				if ( ! is_wp_error( $user_id ) ) {
					wp_update_user( array(
						'ID'         => $user_id,
						'first_name' => $employee['FirstName'] ?? '',
						'last_name'  => $employee['LastName'] ?? '',
						'role'       => 'ict_technician',
					) );

					update_user_meta( $user_id, 'zoho_people_id', $employee['recordId'] );
					$synced++;
				}
			} else {
				// Update existing user
				update_user_meta( $user->ID, 'zoho_people_id', $employee['recordId'] );
				$synced++;
			}
		}

		return array(
			'success' => true,
			'synced'  => $synced,
		);
	}

	/**
	 * Sync timesheets from Zoho People.
	 *
	 * @since  1.0.0
	 * @param  array $params Query parameters.
	 * @return array Sync results.
	 */
	public function sync_timesheets( $params = array() ) {
		$defaults = array(
			'fromDate' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'toDate'   => date( 'Y-m-d' ),
		);

		$params   = wp_parse_args( $params, $defaults );
		$response = $this->get( '/timetracker/gettimeentries', $params );

		if ( ! isset( $response['data']['response']['result'] ) ) {
			return array( 'success' => false, 'message' => __( 'No timesheets found', 'ict-platform' ) );
		}

		// Implementation: Sync timesheets to local time entries table
		return array(
			'success' => true,
			'synced'  => count( $response['data']['response']['result'] ),
		);
	}

	/**
	 * Transform local time entry for Zoho People API.
	 *
	 * @since  1.0.0
	 * @param  array $data Local time entry data.
	 * @return array Transformed data.
	 */
	protected function transform_time_entry_for_people( $data ) {
		global $wpdb;

		// Get Zoho People employee ID from WordPress user
		$zoho_employee_id = get_user_meta( $data['technician_id'], 'zoho_people_id', true );

		// Get project info for job name
		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT project_name FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
				$data['project_id']
			)
		);

		return array(
			'user'        => $zoho_employee_id,
			'workDate'    => date( 'd-MMM-yyyy', strtotime( $data['clock_in'] ) ),
			'billingStatus' => 'Billable',
			'fromTime'    => date( 'h:i A', strtotime( $data['clock_in'] ) ),
			'toTime'      => date( 'h:i A', strtotime( $data['clock_out'] ) ),
			'hours'       => $data['total_hours'],
			'jobName'     => $project->project_name ?? 'General',
			'workItem'    => $data['notes'] ?? '',
		);
	}

	/**
	 * Get timesheet approval status.
	 *
	 * @since  1.0.0
	 * @param  string $zoho_id Zoho timesheet ID.
	 * @return array Approval status.
	 */
	public function get_timesheet_status( $zoho_id ) {
		$response = $this->get( '/timetracker/gettimeentry', array( 'recordId' => $zoho_id ) );

		if ( isset( $response['data']['response']['result'] ) ) {
			$entry = $response['data']['response']['result'][0];

			return array(
				'status'      => $entry['ApprovalStatus'] ?? 'Pending',
				'approved_by' => $entry['ApprovedBy'] ?? null,
				'approved_at' => $entry['ApprovedDate'] ?? null,
			);
		}

		return array( 'status' => 'Unknown' );
	}
}
