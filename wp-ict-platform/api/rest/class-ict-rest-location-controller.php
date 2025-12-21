<?php
/**
 * REST API: Location Controller
 *
 * Handles GPS location tracking endpoints for mobile app.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Location_Controller
 *
 * Handles location tracking via REST API.
 */
class ICT_REST_Location_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ict/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'location';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /location/track
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/track',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_location' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'time_entry_id' => array(
							'required' => true,
							'type'     => 'integer',
						),
						'location'      => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		// GET /location/history/{time_entry_id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/history/(?P<time_entry_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_location_history' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /location/current
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/current',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_current_locations' ),
					'permission_callback' => array( $this, 'check_manager_permission' ),
				),
			)
		);
	}

	/**
	 * Track location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function track_location( $request ) {
		global $wpdb;

		$time_entry_id = absint( $request->get_param( 'time_entry_id' ) );
		$location      = $request->get_param( 'location' );

		// Verify time entry exists and belongs to current user
		$time_entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$time_entry_id
			)
		);

		if ( ! $time_entry ) {
			return new WP_Error(
				'time_entry_not_found',
				'Time entry not found',
				array( 'status' => 404 )
			);
		}

		// Check ownership
		if ( $time_entry->user_id != get_current_user_id() && ! current_user_can( 'manage_ict_time_entries' ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to track location for this time entry',
				array( 'status' => 403 )
			);
		}

		// Validate location data
		if ( ! isset( $location['latitude'] ) || ! isset( $location['longitude'] ) ) {
			return new WP_Error(
				'invalid_location',
				'Location must include latitude and longitude',
				array( 'status' => 400 )
			);
		}

		// Prepare location data
		$location_data = array(
			'time_entry_id' => $time_entry_id,
			'user_id'       => get_current_user_id(),
			'latitude'      => floatval( $location['latitude'] ),
			'longitude'     => floatval( $location['longitude'] ),
			'accuracy'      => isset( $location['accuracy'] ) ? floatval( $location['accuracy'] ) : null,
			'altitude'      => isset( $location['altitude'] ) ? floatval( $location['altitude'] ) : null,
			'heading'       => isset( $location['heading'] ) ? floatval( $location['heading'] ) : null,
			'speed'         => isset( $location['speed'] ) ? floatval( $location['speed'] ) : null,
			'timestamp'     => isset( $location['timestamp'] ) ? $location['timestamp'] : current_time( 'mysql' ),
			'recorded_at'   => current_time( 'mysql' ),
		);

		// Create location tracking table if it doesn't exist
		$this->maybe_create_location_table();

		// Insert location record
		$result = $wpdb->insert(
			$wpdb->prefix . 'ict_location_tracking',
			$location_data,
			array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				'Failed to save location data',
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'      => $wpdb->insert_id,
				'message' => 'Location tracked successfully',
			),
			201
		);
	}

	/**
	 * Get location history for a time entry.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_location_history( $request ) {
		global $wpdb;

		$time_entry_id = absint( $request->get_param( 'time_entry_id' ) );

		// Verify time entry exists
		$time_entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_TIME_ENTRIES_TABLE . ' WHERE id = %d',
				$time_entry_id
			)
		);

		if ( ! $time_entry ) {
			return new WP_Error(
				'time_entry_not_found',
				'Time entry not found',
				array( 'status' => 404 )
			);
		}

		// Check permission
		if ( $time_entry->user_id != get_current_user_id() && ! current_user_can( 'manage_ict_time_entries' ) ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to view this location history',
				array( 'status' => 403 )
			);
		}

		// Get location history
		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ict_location_tracking
				WHERE time_entry_id = %d
				ORDER BY recorded_at ASC",
				$time_entry_id
			),
			ARRAY_A
		);

		// Format locations
		$formatted_locations = array_map( array( $this, 'format_location' ), $locations );

		return new WP_REST_Response(
			array(
				'time_entry_id' => $time_entry_id,
				'locations'     => $formatted_locations,
				'total'         => count( $formatted_locations ),
			),
			200
		);
	}

	/**
	 * Get current locations of all active technicians.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_current_locations( $request ) {
		global $wpdb;

		// Get all active time entries with latest location
		$results = $wpdb->get_results(
			'SELECT
				te.id AS time_entry_id,
				te.user_id,
				te.project_id,
				te.clock_in,
				u.display_name,
				lt.latitude,
				lt.longitude,
				lt.accuracy,
				lt.recorded_at
			FROM ' . ICT_TIME_ENTRIES_TABLE . " te
			INNER JOIN {$wpdb->users} u ON te.user_id = u.ID
			LEFT JOIN (
				SELECT time_entry_id, latitude, longitude, accuracy, recorded_at
				FROM {$wpdb->prefix}ict_location_tracking lt1
				WHERE recorded_at = (
					SELECT MAX(recorded_at)
					FROM {$wpdb->prefix}ict_location_tracking lt2
					WHERE lt2.time_entry_id = lt1.time_entry_id
				)
			) lt ON te.id = lt.time_entry_id
			WHERE te.clock_out IS NULL
			AND te.status = 'active'
			ORDER BY u.display_name ASC",
			ARRAY_A
		);

		return new WP_REST_Response(
			array(
				'technicians' => $results,
				'total'       => count( $results ),
			),
			200
		);
	}

	/**
	 * Check manager permission.
	 *
	 * @return bool
	 */
	public function check_manager_permission() {
		return current_user_can( 'manage_ict_time_entries' ) || current_user_can( 'manage_ict_projects' );
	}

	/**
	 * Format location data.
	 *
	 * @param array $location Location data.
	 * @return array
	 */
	private function format_location( $location ) {
		return array(
			'latitude'    => floatval( $location['latitude'] ),
			'longitude'   => floatval( $location['longitude'] ),
			'accuracy'    => $location['accuracy'] ? floatval( $location['accuracy'] ) : null,
			'altitude'    => $location['altitude'] ? floatval( $location['altitude'] ) : null,
			'heading'     => $location['heading'] ? floatval( $location['heading'] ) : null,
			'speed'       => $location['speed'] ? floatval( $location['speed'] ) : null,
			'timestamp'   => $location['timestamp'],
			'recorded_at' => $location['recorded_at'],
		);
	}

	/**
	 * Create location tracking table if it doesn't exist.
	 */
	private function maybe_create_location_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ict_location_tracking';
		$charset_collate = $wpdb->get_charset_collate();

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $table_exists !== $table_name ) {
			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				time_entry_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				latitude decimal(10,8) NOT NULL,
				longitude decimal(11,8) NOT NULL,
				accuracy decimal(8,2) DEFAULT NULL,
				altitude decimal(8,2) DEFAULT NULL,
				heading decimal(5,2) DEFAULT NULL,
				speed decimal(6,2) DEFAULT NULL,
				timestamp datetime DEFAULT NULL,
				recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY time_entry_id (time_entry_id),
				KEY user_id (user_id),
				KEY recorded_at (recorded_at)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}
}
