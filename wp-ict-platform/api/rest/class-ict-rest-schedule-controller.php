<?php
/**
 * REST API: Schedule Controller
 *
 * Handles schedule and events endpoints for mobile app.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_Schedule_Controller
 *
 * Handles schedule and events via REST API.
 */
class ICT_REST_Schedule_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'schedule';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /schedule/events
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/events',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_events' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /schedule/events/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/events/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_event' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// POST /schedule/events
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/events',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_event' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);

		// GET /schedule/my-schedule
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/my-schedule',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_my_schedule' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * Get events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events( $request ) {
		$user_id    = get_current_user_id();
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		// Get events from various sources
		$events = array();

		// 1. Get project deadlines
		$events = array_merge( $events, $this->get_project_events( $user_id, $start_date, $end_date ) );

		// 2. Get task due dates
		$events = array_merge( $events, $this->get_task_events( $user_id, $start_date, $end_date ) );

		// 3. Get scheduled time entries
		$events = array_merge( $events, $this->get_scheduled_time_entries( $user_id, $start_date, $end_date ) );

		// Sort by start time
		usort(
			$events,
			function ( $a, $b ) {
				return strtotime( $a['start'] ) - strtotime( $b['start'] );
			}
		);

		return new WP_REST_Response( $events, 200 );
	}

	/**
	 * Get single event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_event( $request ) {
		$id = absint( $request->get_param( 'id' ) );

		// For now, return a simple event
		// In production, fetch from database
		return new WP_REST_Response(
			array(
				'id'    => $id,
				'title' => 'Sample Event',
				'start' => current_time( 'mysql' ),
				'end'   => current_time( 'mysql' ),
			),
			200
		);
	}

	/**
	 * Create event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function create_event( $request ) {
		// For now, return success
		// In production, save to database
		return new WP_REST_Response(
			array(
				'id'      => 1,
				'message' => 'Event created successfully',
			),
			201
		);
	}

	/**
	 * Get current user's schedule.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_my_schedule( $request ) {
		$user_id = get_current_user_id();
		$date    = $request->get_param( 'date' ) ?: current_time( 'Y-m-d' );

		$events = $this->get_events_for_date( $user_id, $date );

		return new WP_REST_Response(
			array(
				'date'   => $date,
				'events' => $events,
			),
			200
		);
	}

	/**
	 * Get project events.
	 *
	 * @param int    $user_id User ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array
	 */
	private function get_project_events( $user_id, $start_date = null, $end_date = null ) {
		global $wpdb;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $start_date && $end_date ) {
			$where .= ' AND (start_date BETWEEN %s AND %s OR end_date BETWEEN %s AND %s)';
			$params = array( $start_date, $end_date, $start_date, $end_date );
		}

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, start_date, end_date, client_name
				FROM ' . ICT_PROJECTS_TABLE . "
				$where
				AND status != 'cancelled'
				ORDER BY start_date ASC",
				...$params
			),
			ARRAY_A
		);

		$events = array();
		foreach ( $projects as $project ) {
			if ( $project['start_date'] ) {
				$events[] = array(
					'id'          => 'project_' . $project['id'],
					'title'       => $project['name'] . ' - Start',
					'description' => $project['client_name'],
					'start'       => $project['start_date'] . ' 09:00:00',
					'end'         => $project['start_date'] . ' 17:00:00',
					'type'        => 'project',
					'project_id'  => $project['id'],
				);
			}

			if ( $project['end_date'] ) {
				$events[] = array(
					'id'          => 'project_deadline_' . $project['id'],
					'title'       => $project['name'] . ' - Deadline',
					'description' => $project['client_name'],
					'start'       => $project['end_date'] . ' 09:00:00',
					'end'         => $project['end_date'] . ' 17:00:00',
					'type'        => 'project',
					'project_id'  => $project['id'],
					'color'       => '#dc2626',
				);
			}
		}

		return $events;
	}

	/**
	 * Get task events.
	 *
	 * @param int    $user_id User ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array
	 */
	private function get_task_events( $user_id, $start_date = null, $end_date = null ) {
		global $wpdb;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $start_date && $end_date ) {
			$where .= ' AND due_date BETWEEN %s AND %s';
			$params = array( $start_date, $end_date );
		}

		// Get tasks (from WordPress posts or custom table if available)
		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, project_id, title, due_date, priority, status
				FROM {$wpdb->prefix}ict_tasks
				$where
				AND status != 'cancelled'
				ORDER BY due_date ASC",
				...$params
			),
			ARRAY_A
		);

		$events = array();
		foreach ( $tasks as $task ) {
			if ( $task['due_date'] ) {
				$color = '#3b82f6';
				if ( $task['priority'] === 'urgent' ) {
					$color = '#dc2626';
				} elseif ( $task['priority'] === 'high' ) {
					$color = '#f59e0b';
				}

				$events[] = array(
					'id'         => 'task_' . $task['id'],
					'title'      => $task['title'],
					'start'      => $task['due_date'] . ' 09:00:00',
					'end'        => $task['due_date'] . ' 17:00:00',
					'type'       => 'task',
					'task_id'    => $task['id'],
					'project_id' => $task['project_id'],
					'color'      => $color,
				);
			}
		}

		return $events;
	}

	/**
	 * Get scheduled time entries.
	 *
	 * @param int    $user_id User ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array
	 */
	private function get_scheduled_time_entries( $user_id, $start_date = null, $end_date = null ) {
		global $wpdb;

		$where  = 'WHERE user_id = %d';
		$params = array( $user_id );

		if ( $start_date && $end_date ) {
			$where   .= ' AND clock_in BETWEEN %s AND %s';
			$params[] = $start_date;
			$params[] = $end_date;
		} else {
			// Get upcoming week by default
			$where   .= ' AND clock_in >= %s';
			$params[] = current_time( 'mysql' );
		}

		$time_entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT te.*, p.name AS project_name
				FROM ' . ICT_TIME_ENTRIES_TABLE . ' te
				LEFT JOIN ' . ICT_PROJECTS_TABLE . " p ON te.project_id = p.id
				$where
				AND te.clock_out IS NULL
				ORDER BY te.clock_in ASC
				LIMIT 50",
				...$params
			),
			ARRAY_A
		);

		$events = array();
		foreach ( $time_entries as $entry ) {
			$events[] = array(
				'id'         => 'time_entry_' . $entry['id'],
				'title'      => 'Clocked In: ' . $entry['project_name'],
				'start'      => $entry['clock_in'],
				'end'        => $entry['clock_out'] ?: date( 'Y-m-d H:i:s', strtotime( $entry['clock_in'] ) + ( 8 * HOUR_IN_SECONDS ) ),
				'type'       => 'time_entry',
				'project_id' => $entry['project_id'],
				'color'      => '#10b981',
			);
		}

		return $events;
	}

	/**
	 * Get events for specific date.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date Date.
	 * @return array
	 */
	private function get_events_for_date( $user_id, $date ) {
		$start_date = $date . ' 00:00:00';
		$end_date   = $date . ' 23:59:59';

		return $this->get_events(
			new WP_REST_Request(
				'GET',
				array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			)
		)->get_data();
	}
}
