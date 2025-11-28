<?php
/**
 * GPS Location Tracking
 *
 * Track employee locations for clock in/out and job site verification.
 *
 * @package    suspended_ict_platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_GPS_Tracking
 *
 * Handles GPS location tracking for field workers.
 */
class ICT_GPS_Tracking {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_GPS_Tracking|null
	 */
	private static $instance = null;

	/**
	 * Locations table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Geofences table name.
	 *
	 * @var string
	 */
	private $geofences_table;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_GPS_Tracking
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
		$this->table_name      = $wpdb->prefix . 'ict_gps_locations';
		$this->geofences_table = $wpdb->prefix . 'ict_geofences';
	}

	/**
	 * Initialize the feature.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Location tracking
		register_rest_route(
			'ict/v1',
			'/gps/location',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'record_location' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/gps/locations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_locations' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/gps/users/(?P<user_id>\d+)/locations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_locations' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/gps/users/(?P<user_id>\d+)/current',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_current_location' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/gps/team/current',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_team_locations' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		// Geofencing
		register_rest_route(
			'ict/v1',
			'/geofences',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_geofences' ),
					'permission_callback' => array( $this, 'check_manager_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_geofence' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/geofences/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_geofence' ),
					'permission_callback' => array( $this, 'check_manager_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_geofence' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_geofence' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/geofences/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_geofence' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Project job sites
		register_rest_route(
			'ict/v1',
			'/projects/(?P<project_id>\d+)/jobsite',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project_jobsite' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_project_jobsite' ),
					'permission_callback' => array( $this, 'check_manager_permission' ),
				),
			)
		);

		// Location history/trail
		register_rest_route(
			'ict/v1',
			'/gps/trail',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_location_trail' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);

		// Mileage tracking
		register_rest_route(
			'ict/v1',
			'/gps/mileage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mileage_report' ),
				'permission_callback' => array( $this, 'check_manager_permission' ),
			)
		);
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return is_user_logged_in();
	}

	/**
	 * Check manager permission.
	 *
	 * @return bool
	 */
	public function check_manager_permission() {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create database tables if needed.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// GPS locations table
		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            accuracy decimal(10,2),
            altitude decimal(10,2),
            speed decimal(10,2),
            heading decimal(5,2),
            location_type enum('clock_in','clock_out','checkpoint','manual','auto') DEFAULT 'auto',
            time_entry_id bigint(20) unsigned,
            project_id bigint(20) unsigned,
            address text,
            geofence_id bigint(20) unsigned,
            is_within_geofence tinyint(1),
            battery_level tinyint(3) unsigned,
            network_type varchar(20),
            device_info varchar(255),
            recorded_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY recorded_at (recorded_at),
            KEY location_type (location_type),
            KEY project_id (project_id),
            KEY geofence_id (geofence_id),
            KEY lat_lng (latitude, longitude)
        ) {$charset_collate};";

		// Geofences table
		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->geofences_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            fence_type enum('circle','polygon') DEFAULT 'circle',
            center_latitude decimal(10,8),
            center_longitude decimal(11,8),
            radius_meters int unsigned,
            polygon_coords longtext,
            project_id bigint(20) unsigned,
            address text,
            is_active tinyint(1) DEFAULT 1,
            require_on_clock_in tinyint(1) DEFAULT 0,
            require_on_clock_out tinyint(1) DEFAULT 0,
            notify_on_enter tinyint(1) DEFAULT 0,
            notify_on_exit tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY is_active (is_active)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	/**
	 * Record a location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function record_location( $request ) {
		global $wpdb;

		$user_id   = get_current_user_id();
		$latitude  = (float) $request->get_param( 'latitude' );
		$longitude = (float) $request->get_param( 'longitude' );

		if ( ! $this->validate_coordinates( $latitude, $longitude ) ) {
			return new WP_Error( 'invalid_coords', 'Invalid coordinates', array( 'status' => 400 ) );
		}

		// Check geofence
		$geofence_check = $this->check_point_in_geofences( $latitude, $longitude );

		$data = array(
			'user_id'            => $user_id,
			'latitude'           => $latitude,
			'longitude'          => $longitude,
			'accuracy'           => (float) $request->get_param( 'accuracy' ),
			'altitude'           => (float) $request->get_param( 'altitude' ),
			'speed'              => (float) $request->get_param( 'speed' ),
			'heading'            => (float) $request->get_param( 'heading' ),
			'location_type'      => sanitize_text_field( $request->get_param( 'location_type' ) ?: 'auto' ),
			'time_entry_id'      => (int) $request->get_param( 'time_entry_id' ) ?: null,
			'project_id'         => (int) $request->get_param( 'project_id' ) ?: null,
			'address'            => sanitize_text_field( $request->get_param( 'address' ) ),
			'geofence_id'        => $geofence_check['geofence_id'],
			'is_within_geofence' => $geofence_check['is_within'] ? 1 : 0,
			'battery_level'      => (int) $request->get_param( 'battery_level' ) ?: null,
			'network_type'       => sanitize_text_field( $request->get_param( 'network_type' ) ),
			'device_info'        => sanitize_text_field( $request->get_param( 'device_info' ) ),
			'recorded_at'        => sanitize_text_field( $request->get_param( 'recorded_at' ) ) ?: current_time( 'mysql' ),
		);

		$wpdb->insert( $this->table_name, $data );
		$location_id = $wpdb->insert_id;

		// Trigger geofence events
		if ( $geofence_check['geofence_id'] ) {
			$this->handle_geofence_event( $user_id, $geofence_check['geofence_id'], $geofence_check['is_within'] );
		}

		return rest_ensure_response(
			array(
				'success'            => true,
				'id'                 => $location_id,
				'is_within_geofence' => $geofence_check['is_within'],
				'geofence_name'      => $geofence_check['geofence_name'],
			)
		);
	}

	/**
	 * Get locations with filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_locations( $request ) {
		global $wpdb;

		$page          = (int) $request->get_param( 'page' ) ?: 1;
		$per_page      = (int) $request->get_param( 'per_page' ) ?: 50;
		$user_id       = (int) $request->get_param( 'user_id' );
		$project_id    = (int) $request->get_param( 'project_id' );
		$from_date     = $request->get_param( 'from_date' );
		$to_date       = $request->get_param( 'to_date' );
		$location_type = $request->get_param( 'location_type' );

		$offset = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		if ( $user_id ) {
			$where[]  = 'l.user_id = %d';
			$values[] = $user_id;
		}

		if ( $project_id ) {
			$where[]  = 'l.project_id = %d';
			$values[] = $project_id;
		}

		if ( $from_date ) {
			$where[]  = 'l.recorded_at >= %s';
			$values[] = $from_date . ' 00:00:00';
		}

		if ( $to_date ) {
			$where[]  = 'l.recorded_at <= %s';
			$values[] = $to_date . ' 23:59:59';
		}

		if ( $location_type ) {
			$where[]  = 'l.location_type = %s';
			$values[] = $location_type;
		}

		$where_clause = implode( ' AND ', $where );

		$count_query = "SELECT COUNT(*) FROM {$this->table_name} l WHERE {$where_clause}";
		$total       = ! empty( $values )
			? $wpdb->get_var( $wpdb->prepare( $count_query, $values ) )
			: $wpdb->get_var( $count_query );

		$values[] = $per_page;
		$values[] = $offset;

		$query = "SELECT l.*, u.display_name as user_name, g.name as geofence_name
                  FROM {$this->table_name} l
                  LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                  LEFT JOIN {$this->geofences_table} g ON l.geofence_id = g.id
                  WHERE {$where_clause}
                  ORDER BY l.recorded_at DESC
                  LIMIT %d OFFSET %d";

		$locations = $wpdb->get_results( $wpdb->prepare( $query, $values ) );

		return rest_ensure_response(
			array(
				'locations' => $locations,
				'total'     => (int) $total,
				'pages'     => ceil( $total / $per_page ),
				'page'      => $page,
			)
		);
	}

	/**
	 * Get user locations.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_user_locations( $request ) {
		global $wpdb;

		$user_id = (int) $request->get_param( 'user_id' );
		$date    = $request->get_param( 'date' ) ?: date( 'Y-m-d' );

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
                 WHERE user_id = %d AND DATE(recorded_at) = %s
                 ORDER BY recorded_at",
				$user_id,
				$date
			)
		);

		return rest_ensure_response( $locations );
	}

	/**
	 * Get user's current/last location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_user_current_location( $request ) {
		global $wpdb;

		$user_id = (int) $request->get_param( 'user_id' );

		$location = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT l.*, g.name as geofence_name
                 FROM {$this->table_name} l
                 LEFT JOIN {$this->geofences_table} g ON l.geofence_id = g.id
                 WHERE l.user_id = %d
                 ORDER BY l.recorded_at DESC
                 LIMIT 1",
				$user_id
			)
		);

		if ( ! $location ) {
			return rest_ensure_response( array( 'found' => false ) );
		}

		$age_minutes = ( current_time( 'timestamp' ) - strtotime( $location->recorded_at ) ) / 60;

		return rest_ensure_response(
			array(
				'found'       => true,
				'location'    => $location,
				'age_minutes' => round( $age_minutes ),
				'is_recent'   => $age_minutes < 15,
			)
		);
	}

	/**
	 * Get current locations for all team members.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_team_locations( $request ) {
		global $wpdb;

		$project_id      = (int) $request->get_param( 'project_id' );
		$max_age_minutes = (int) $request->get_param( 'max_age' ) ?: 60;

		$cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$max_age_minutes} minutes" ) );

		$where  = 'l.recorded_at >= %s';
		$values = array( $cutoff );

		if ( $project_id ) {
			$where   .= ' AND l.project_id = %d';
			$values[] = $project_id;
		}

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name as user_name, u.user_email,
                        g.name as geofence_name
                 FROM {$this->table_name} l
                 INNER JOIN (
                     SELECT user_id, MAX(recorded_at) as max_time
                     FROM {$this->table_name}
                     WHERE recorded_at >= %s
                     GROUP BY user_id
                 ) latest ON l.user_id = latest.user_id AND l.recorded_at = latest.max_time
                 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                 LEFT JOIN {$this->geofences_table} g ON l.geofence_id = g.id
                 WHERE {$where}",
				array_merge( array( $cutoff ), $values )
			)
		);

		foreach ( $locations as &$loc ) {
			$loc->age_minutes = round( ( current_time( 'timestamp' ) - strtotime( $loc->recorded_at ) ) / 60 );
		}

		return rest_ensure_response( $locations );
	}

	// Geofencing Methods

	/**
	 * Get all geofences.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_geofences( $request ) {
		global $wpdb;

		$project_id = (int) $request->get_param( 'project_id' );
		$is_active  = $request->get_param( 'is_active' );

		$where  = array( '1=1' );
		$values = array();

		if ( $project_id ) {
			$where[]  = 'project_id = %d';
			$values[] = $project_id;
		}

		if ( null !== $is_active ) {
			$where[]  = 'is_active = %d';
			$values[] = (int) $is_active;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$this->geofences_table} WHERE {$where_clause} ORDER BY name";

		$geofences = ! empty( $values )
			? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
			: $wpdb->get_results( $query );

		foreach ( $geofences as &$fence ) {
			if ( $fence->polygon_coords ) {
				$fence->polygon_coords = json_decode( $fence->polygon_coords, true );
			}
		}

		return rest_ensure_response( $geofences );
	}

	/**
	 * Get single geofence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_geofence( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$geofence = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->geofences_table} WHERE id = %d", $id )
		);

		if ( ! $geofence ) {
			return new WP_Error( 'not_found', 'Geofence not found', array( 'status' => 404 ) );
		}

		if ( $geofence->polygon_coords ) {
			$geofence->polygon_coords = json_decode( $geofence->polygon_coords, true );
		}

		return rest_ensure_response( $geofence );
	}

	/**
	 * Create a geofence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_geofence( $request ) {
		global $wpdb;

		$fence_type = sanitize_text_field( $request->get_param( 'fence_type' ) ?: 'circle' );

		$data = array(
			'name'                 => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'          => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'fence_type'           => $fence_type,
			'project_id'           => (int) $request->get_param( 'project_id' ) ?: null,
			'address'              => sanitize_text_field( $request->get_param( 'address' ) ),
			'require_on_clock_in'  => (int) $request->get_param( 'require_on_clock_in' ),
			'require_on_clock_out' => (int) $request->get_param( 'require_on_clock_out' ),
			'notify_on_enter'      => (int) $request->get_param( 'notify_on_enter' ),
			'notify_on_exit'       => (int) $request->get_param( 'notify_on_exit' ),
			'created_by'           => get_current_user_id(),
		);

		if ( $fence_type === 'circle' ) {
			$data['center_latitude']  = (float) $request->get_param( 'center_latitude' );
			$data['center_longitude'] = (float) $request->get_param( 'center_longitude' );
			$data['radius_meters']    = (int) $request->get_param( 'radius_meters' );

			if ( ! $this->validate_coordinates( $data['center_latitude'], $data['center_longitude'] ) ) {
				return new WP_Error( 'invalid_coords', 'Invalid center coordinates', array( 'status' => 400 ) );
			}
		} else {
			$polygon = $request->get_param( 'polygon_coords' );
			if ( ! is_array( $polygon ) || count( $polygon ) < 3 ) {
				return new WP_Error( 'invalid_polygon', 'Polygon must have at least 3 points', array( 'status' => 400 ) );
			}
			$data['polygon_coords'] = wp_json_encode( $polygon );
		}

		$wpdb->insert( $this->geofences_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
				'message' => 'Geofence created successfully',
			)
		);
	}

	/**
	 * Update a geofence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_geofence( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$data        = array();
		$text_fields = array( 'name', 'description', 'address' );

		foreach ( $text_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( $value );
			}
		}

		$bool_fields = array( 'is_active', 'require_on_clock_in', 'require_on_clock_out', 'notify_on_enter', 'notify_on_exit' );
		foreach ( $bool_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = (int) $value;
			}
		}

		$lat = $request->get_param( 'center_latitude' );
		$lng = $request->get_param( 'center_longitude' );
		if ( null !== $lat && null !== $lng ) {
			$data['center_latitude']  = (float) $lat;
			$data['center_longitude'] = (float) $lng;
		}

		$radius = $request->get_param( 'radius_meters' );
		if ( null !== $radius ) {
			$data['radius_meters'] = (int) $radius;
		}

		$polygon = $request->get_param( 'polygon_coords' );
		if ( null !== $polygon ) {
			$data['polygon_coords'] = wp_json_encode( $polygon );
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->geofences_table, $data, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Geofence updated successfully',
			)
		);
	}

	/**
	 * Delete a geofence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_geofence( $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );
		$wpdb->delete( $this->geofences_table, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Geofence deleted successfully',
			)
		);
	}

	/**
	 * Check if a point is within any geofence.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_geofence( $request ) {
		$latitude   = (float) $request->get_param( 'latitude' );
		$longitude  = (float) $request->get_param( 'longitude' );
		$project_id = (int) $request->get_param( 'project_id' );

		$result = $this->check_point_in_geofences( $latitude, $longitude, $project_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Get or set project job site location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_project_jobsite( $request ) {
		global $wpdb;

		$project_id = (int) $request->get_param( 'project_id' );

		$geofence = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->geofences_table} WHERE project_id = %d LIMIT 1",
				$project_id
			)
		);

		if ( ! $geofence ) {
			return rest_ensure_response( array( 'has_jobsite' => false ) );
		}

		if ( $geofence->polygon_coords ) {
			$geofence->polygon_coords = json_decode( $geofence->polygon_coords, true );
		}

		return rest_ensure_response(
			array(
				'has_jobsite' => true,
				'geofence'    => $geofence,
			)
		);
	}

	/**
	 * Set project job site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function set_project_jobsite( $request ) {
		global $wpdb;

		$project_id = (int) $request->get_param( 'project_id' );

		// Get project name
		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}ict_projects WHERE id = %d",
				$project_id
			)
		);

		if ( ! $project ) {
			return new WP_Error( 'not_found', 'Project not found', array( 'status' => 404 ) );
		}

		$data = array(
			'name'                => $project->name . ' - Job Site',
			'description'         => 'Auto-created job site geofence',
			'fence_type'          => 'circle',
			'center_latitude'     => (float) $request->get_param( 'latitude' ),
			'center_longitude'    => (float) $request->get_param( 'longitude' ),
			'radius_meters'       => (int) $request->get_param( 'radius' ) ?: 100,
			'project_id'          => $project_id,
			'address'             => sanitize_text_field( $request->get_param( 'address' ) ),
			'require_on_clock_in' => 1,
			'created_by'          => get_current_user_id(),
		);

		// Check for existing
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->geofences_table} WHERE project_id = %d",
				$project_id
			)
		);

		if ( $existing ) {
			$wpdb->update( $this->geofences_table, $data, array( 'id' => $existing ) );
			$geofence_id = $existing;
		} else {
			$wpdb->insert( $this->geofences_table, $data );
			$geofence_id = $wpdb->insert_id;
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'geofence_id' => $geofence_id,
				'message'     => 'Job site location saved',
			)
		);
	}

	/**
	 * Get location trail/history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_location_trail( $request ) {
		global $wpdb;

		$user_id = (int) $request->get_param( 'user_id' );
		$date    = $request->get_param( 'date' ) ?: date( 'Y-m-d' );

		if ( ! $user_id ) {
			return new WP_Error( 'missing_user', 'User ID required', array( 'status' => 400 ) );
		}

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT latitude, longitude, recorded_at, location_type, accuracy
                 FROM {$this->table_name}
                 WHERE user_id = %d AND DATE(recorded_at) = %s
                 ORDER BY recorded_at",
				$user_id,
				$date
			)
		);

		// Calculate total distance
		$total_distance = 0;
		$prev           = null;

		foreach ( $locations as $loc ) {
			if ( $prev ) {
				$total_distance += $this->calculate_distance(
					$prev->latitude,
					$prev->longitude,
					$loc->latitude,
					$loc->longitude
				);
			}
			$prev = $loc;
		}

		return rest_ensure_response(
			array(
				'user_id'        => $user_id,
				'date'           => $date,
				'locations'      => $locations,
				'total_points'   => count( $locations ),
				'total_distance' => round( $total_distance, 2 ),
				'distance_unit'  => 'km',
			)
		);
	}

	/**
	 * Get mileage report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_mileage_report( $request ) {
		global $wpdb;

		$user_id    = (int) $request->get_param( 'user_id' );
		$start_date = $request->get_param( 'start_date' ) ?: date( 'Y-m-01' );
		$end_date   = $request->get_param( 'end_date' ) ?: date( 'Y-m-d' );

		$where  = 'DATE(recorded_at) BETWEEN %s AND %s';
		$values = array( $start_date, $end_date );

		if ( $user_id ) {
			$where   .= ' AND user_id = %d';
			$values[] = $user_id;
		}

		// Get daily totals
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, DATE(recorded_at) as date,
                        MIN(recorded_at) as first_location,
                        MAX(recorded_at) as last_location,
                        COUNT(*) as points
                 FROM {$this->table_name}
                 WHERE {$where}
                 GROUP BY user_id, DATE(recorded_at)
                 ORDER BY date",
				$values
			)
		);

		$report = array();

		foreach ( $daily as $day ) {
			// Get locations for this day
			$locations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT latitude, longitude
                     FROM {$this->table_name}
                     WHERE user_id = %d AND DATE(recorded_at) = %s
                     ORDER BY recorded_at",
					$day->user_id,
					$day->date
				)
			);

			// Calculate distance
			$distance = 0;
			$prev     = null;

			foreach ( $locations as $loc ) {
				if ( $prev ) {
					$distance += $this->calculate_distance(
						$prev->latitude,
						$prev->longitude,
						$loc->latitude,
						$loc->longitude
					);
				}
				$prev = $loc;
			}

			$report[] = array(
				'user_id'  => $day->user_id,
				'date'     => $day->date,
				'distance' => round( $distance, 2 ),
				'points'   => $day->points,
			);
		}

		// Aggregate by user
		$by_user = array();
		foreach ( $report as $entry ) {
			$uid = $entry['user_id'];
			if ( ! isset( $by_user[ $uid ] ) ) {
				$by_user[ $uid ] = array(
					'total_distance' => 0,
					'days'           => 0,
				);
			}
			$by_user[ $uid ]['total_distance'] += $entry['distance'];
			++$by_user[ $uid ]['days'];
		}

		return rest_ensure_response(
			array(
				'period'       => array(
					'start' => $start_date,
					'end'   => $end_date,
				),
				'daily_report' => $report,
				'by_user'      => $by_user,
				'unit'         => 'km',
			)
		);
	}

	// Helper Methods

	/**
	 * Validate coordinates.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return bool
	 */
	private function validate_coordinates( $lat, $lng ) {
		return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
	}

	/**
	 * Check if point is within any active geofences.
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @param int   $project_id Optional project ID.
	 * @return array
	 */
	private function check_point_in_geofences( $latitude, $longitude, $project_id = null ) {
		global $wpdb;

		$where  = 'is_active = 1';
		$values = array();

		if ( $project_id ) {
			$where   .= ' AND (project_id = %d OR project_id IS NULL)';
			$values[] = $project_id;
		}

		$query = "SELECT * FROM {$this->geofences_table} WHERE {$where}";

		$geofences = ! empty( $values )
			? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
			: $wpdb->get_results( $query );

		foreach ( $geofences as $fence ) {
			$is_within = false;

			if ( $fence->fence_type === 'circle' ) {
				$distance = $this->calculate_distance(
					$latitude,
					$longitude,
					$fence->center_latitude,
					$fence->center_longitude
				) * 1000; // Convert to meters

				$is_within = $distance <= $fence->radius_meters;
			} else {
				$polygon   = json_decode( $fence->polygon_coords, true );
				$is_within = $this->point_in_polygon( $latitude, $longitude, $polygon );
			}

			if ( $is_within ) {
				return array(
					'is_within'     => true,
					'geofence_id'   => $fence->id,
					'geofence_name' => $fence->name,
				);
			}
		}

		return array(
			'is_within'     => false,
			'geofence_id'   => null,
			'geofence_name' => null,
		);
	}

	/**
	 * Calculate distance between two points using Haversine formula.
	 *
	 * @param float $lat1 Latitude 1.
	 * @param float $lon1 Longitude 1.
	 * @param float $lat2 Latitude 2.
	 * @param float $lon2 Longitude 2.
	 * @return float Distance in kilometers.
	 */
	private function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius = 6371; // km

		$lat1 = deg2rad( $lat1 );
		$lon1 = deg2rad( $lon1 );
		$lat2 = deg2rad( $lat2 );
		$lon2 = deg2rad( $lon2 );

		$dlat = $lat2 - $lat1;
		$dlon = $lon2 - $lon1;

		$a = sin( $dlat / 2 ) * sin( $dlat / 2 ) +
			cos( $lat1 ) * cos( $lat2 ) *
			sin( $dlon / 2 ) * sin( $dlon / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}

	/**
	 * Check if point is inside polygon.
	 *
	 * @param float $lat     Latitude.
	 * @param float $lng     Longitude.
	 * @param array $polygon Array of polygon coordinates.
	 * @return bool
	 */
	private function point_in_polygon( $lat, $lng, $polygon ) {
		$n      = count( $polygon );
		$inside = false;

		for ( $i = 0, $j = $n - 1; $i < $n; $j = $i++ ) {
			$xi = $polygon[ $i ]['lat'];
			$yi = $polygon[ $i ]['lng'];
			$xj = $polygon[ $j ]['lat'];
			$yj = $polygon[ $j ]['lng'];

			if ( ( ( $yi > $lng ) !== ( $yj > $lng ) ) &&
				( $lat < ( $xj - $xi ) * ( $lng - $yi ) / ( $yj - $yi ) + $xi ) ) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	/**
	 * Handle geofence enter/exit events.
	 *
	 * @param int  $user_id     User ID.
	 * @param int  $geofence_id Geofence ID.
	 * @param bool $is_entering Whether entering or exiting.
	 */
	private function handle_geofence_event( $user_id, $geofence_id, $is_entering ) {
		global $wpdb;

		$geofence = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->geofences_table} WHERE id = %d", $geofence_id )
		);

		if ( ! $geofence ) {
			return;
		}

		if ( $is_entering && $geofence->notify_on_enter ) {
			do_action( 'ict_geofence_entered', $user_id, $geofence_id, $geofence->name );
		} elseif ( ! $is_entering && $geofence->notify_on_exit ) {
			do_action( 'ict_geofence_exited', $user_id, $geofence_id, $geofence->name );
		}
	}
}
