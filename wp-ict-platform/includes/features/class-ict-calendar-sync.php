<?php
/**
 * Calendar Sync Integration
 *
 * Sync with external calendars (Google, Outlook, Apple).
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Calendar_Sync {

    private static $instance = null;
    private $connections_table;
    private $events_table;
    private $sync_log_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->connections_table = $wpdb->prefix . 'ict_calendar_connections';
        $this->events_table = $wpdb->prefix . 'ict_calendar_events';
        $this->sync_log_table = $wpdb->prefix . 'ict_calendar_sync_log';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
        add_action( 'ict_sync_calendars', array( $this, 'sync_all_calendars' ) );
        add_action( 'ict_project_updated', array( $this, 'sync_project_to_calendar' ), 10, 2 );
        add_action( 'ict_task_updated', array( $this, 'sync_task_to_calendar' ), 10, 2 );

        if ( ! wp_next_scheduled( 'ict_sync_calendars' ) ) {
            wp_schedule_event( time(), 'hourly', 'ict_sync_calendars' );
        }
    }

    public function register_routes() {
        register_rest_route( 'ict/v1', '/calendar/connections', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_connections' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_connection' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/calendar/connections/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_connection' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_connection' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_connection' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/calendar/connections/(?P<id>\d+)/sync', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'sync_connection' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/calendar/events', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_events' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/calendar/events/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_event' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/calendar/ical/(?P<token>[a-zA-Z0-9]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_ical_feed' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'ict/v1', '/calendar/feed-token', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'generate_feed_token' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/calendar/oauth/(?P<provider>[a-z]+)/callback', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'oauth_callback' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function check_permission() {
        return is_user_logged_in();
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->connections_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            provider enum('google','outlook','apple','caldav') NOT NULL,
            calendar_id varchar(255),
            calendar_name varchar(255),
            access_token text,
            refresh_token text,
            token_expires datetime,
            caldav_url varchar(500),
            caldav_username varchar(255),
            sync_direction enum('both','to_calendar','from_calendar') DEFAULT 'both',
            sync_projects tinyint(1) DEFAULT 1,
            sync_tasks tinyint(1) DEFAULT 1,
            sync_time_entries tinyint(1) DEFAULT 0,
            color varchar(7) DEFAULT '#3788d8',
            is_active tinyint(1) DEFAULT 1,
            last_sync datetime,
            sync_token varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY provider (provider),
            KEY is_active (is_active)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->events_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            connection_id bigint(20) unsigned NOT NULL,
            external_id varchar(255),
            internal_type enum('project','task','time_entry','recurring') NOT NULL,
            internal_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            start_datetime datetime NOT NULL,
            end_datetime datetime,
            all_day tinyint(1) DEFAULT 0,
            location varchar(255),
            attendees text,
            recurrence_rule varchar(255),
            status enum('confirmed','tentative','cancelled') DEFAULT 'confirmed',
            last_modified datetime,
            etag varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY connection_id (connection_id),
            KEY external_id (external_id),
            KEY internal_type_id (internal_type, internal_id),
            KEY start_datetime (start_datetime)
        ) {$charset_collate};";

        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->sync_log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            connection_id bigint(20) unsigned NOT NULL,
            direction enum('push','pull') NOT NULL,
            action enum('create','update','delete') NOT NULL,
            event_id bigint(20) unsigned,
            status enum('success','error') NOT NULL,
            message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY connection_id (connection_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
    }

    public function get_connections( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();

        $connections = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, user_id, provider, calendar_id, calendar_name, sync_direction,
                    sync_projects, sync_tasks, sync_time_entries, color, is_active, last_sync
             FROM {$this->connections_table}
             WHERE user_id = %d ORDER BY created_at",
            $user_id
        ) );

        return rest_ensure_response( $connections );
    }

    public function get_connection( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();

        $connection = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, user_id, provider, calendar_id, calendar_name, sync_direction,
                    sync_projects, sync_tasks, sync_time_entries, color, is_active, last_sync
             FROM {$this->connections_table}
             WHERE id = %d AND user_id = %d",
            $id, $user_id
        ) );

        if ( ! $connection ) {
            return new WP_Error( 'not_found', 'Connection not found', array( 'status' => 404 ) );
        }

        $connection->event_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->events_table} WHERE connection_id = %d", $id
        ) );

        return rest_ensure_response( $connection );
    }

    public function create_connection( $request ) {
        global $wpdb;

        $provider = sanitize_text_field( $request->get_param( 'provider' ) );
        $user_id = get_current_user_id();

        $data = array(
            'user_id'           => $user_id,
            'provider'          => $provider,
            'calendar_name'     => sanitize_text_field( $request->get_param( 'calendar_name' ) ),
            'sync_direction'    => sanitize_text_field( $request->get_param( 'sync_direction' ) ?: 'both' ),
            'sync_projects'     => (int) $request->get_param( 'sync_projects' ),
            'sync_tasks'        => (int) $request->get_param( 'sync_tasks' ),
            'sync_time_entries' => (int) $request->get_param( 'sync_time_entries' ),
            'color'             => sanitize_hex_color( $request->get_param( 'color' ) ) ?: '#3788d8',
        );

        if ( $provider === 'caldav' ) {
            $data['caldav_url'] = esc_url_raw( $request->get_param( 'caldav_url' ) );
            $data['caldav_username'] = sanitize_text_field( $request->get_param( 'caldav_username' ) );
            $password = $request->get_param( 'caldav_password' );
            if ( $password ) {
                $data['access_token'] = $this->encrypt( $password );
            }
        }

        $wpdb->insert( $this->connections_table, $data );
        $connection_id = $wpdb->insert_id;

        // For OAuth providers, return auth URL
        if ( in_array( $provider, array( 'google', 'outlook' ), true ) ) {
            $auth_url = $this->get_oauth_url( $provider, $connection_id );
            return rest_ensure_response( array(
                'success' => true,
                'id' => $connection_id,
                'auth_url' => $auth_url,
            ) );
        }

        return rest_ensure_response( array( 'success' => true, 'id' => $connection_id ) );
    }

    public function update_connection( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->connections_table} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ) );

        if ( ! $existing ) {
            return new WP_Error( 'not_found', 'Connection not found', array( 'status' => 404 ) );
        }

        $fields = array( 'calendar_name', 'sync_direction', 'color' );
        $data = array();

        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $bool_fields = array( 'sync_projects', 'sync_tasks', 'sync_time_entries', 'is_active' );
        foreach ( $bool_fields as $field ) {
            $value = $request->get_param( $field );
            if ( null !== $value ) {
                $data[ $field ] = (int) $value;
            }
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->connections_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_connection( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();

        $wpdb->delete( $this->events_table, array( 'connection_id' => $id ) );
        $wpdb->delete( $this->sync_log_table, array( 'connection_id' => $id ) );
        $wpdb->delete( $this->connections_table, array( 'id' => $id, 'user_id' => $user_id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function sync_connection( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();

        $connection = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->connections_table} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ) );

        if ( ! $connection ) {
            return new WP_Error( 'not_found', 'Connection not found', array( 'status' => 404 ) );
        }

        $result = $this->sync_calendar( $connection );

        return rest_ensure_response( $result );
    }

    public function get_events( $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $start = sanitize_text_field( $request->get_param( 'start' ) );
        $end = sanitize_text_field( $request->get_param( 'end' ) );
        $type = $request->get_param( 'type' );

        $where = 'c.user_id = %d';
        $values = array( $user_id );

        if ( $start ) {
            $where .= ' AND e.start_datetime >= %s';
            $values[] = $start;
        }
        if ( $end ) {
            $where .= ' AND e.start_datetime <= %s';
            $values[] = $end;
        }
        if ( $type ) {
            $where .= ' AND e.internal_type = %s';
            $values[] = $type;
        }

        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, c.provider, c.color, c.calendar_name
             FROM {$this->events_table} e
             JOIN {$this->connections_table} c ON e.connection_id = c.id
             WHERE {$where}
             ORDER BY e.start_datetime",
            $values
        ) );

        foreach ( $events as $event ) {
            $event->attendees = json_decode( $event->attendees, true );
        }

        return rest_ensure_response( $events );
    }

    public function get_event( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );
        $user_id = get_current_user_id();

        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, c.provider, c.color
             FROM {$this->events_table} e
             JOIN {$this->connections_table} c ON e.connection_id = c.id
             WHERE e.id = %d AND c.user_id = %d",
            $id, $user_id
        ) );

        if ( ! $event ) {
            return new WP_Error( 'not_found', 'Event not found', array( 'status' => 404 ) );
        }

        $event->attendees = json_decode( $event->attendees, true );

        return rest_ensure_response( $event );
    }

    public function get_ical_feed( $request ) {
        global $wpdb;
        $token = sanitize_text_field( $request->get_param( 'token' ) );

        $user_id = get_user_meta_by_key_value( 'ict_calendar_feed_token', $token );
        if ( ! $user_id ) {
            return new WP_Error( 'invalid_token', 'Invalid feed token', array( 'status' => 403 ) );
        }

        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*
             FROM {$this->events_table} e
             JOIN {$this->connections_table} c ON e.connection_id = c.id
             WHERE c.user_id = %d AND e.start_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY e.start_datetime",
            $user_id
        ) );

        $ical = $this->generate_ical( $events );

        return new WP_REST_Response( $ical, 200, array(
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ict-calendar.ics"',
        ) );
    }

    public function generate_feed_token( $request ) {
        $user_id = get_current_user_id();
        $token = wp_generate_password( 32, false );

        update_user_meta( $user_id, 'ict_calendar_feed_token', $token );

        $feed_url = rest_url( "ict/v1/calendar/ical/{$token}" );

        return rest_ensure_response( array(
            'token' => $token,
            'feed_url' => $feed_url,
        ) );
    }

    public function oauth_callback( $request ) {
        $provider = sanitize_text_field( $request->get_param( 'provider' ) );
        $code = sanitize_text_field( $request->get_param( 'code' ) );
        $state = sanitize_text_field( $request->get_param( 'state' ) );

        if ( ! $code || ! $state ) {
            return new WP_Error( 'invalid_callback', 'Invalid OAuth callback', array( 'status' => 400 ) );
        }

        $tokens = $this->exchange_oauth_code( $provider, $code );

        if ( is_wp_error( $tokens ) ) {
            return $tokens;
        }

        global $wpdb;
        $wpdb->update( $this->connections_table, array(
            'access_token'  => $this->encrypt( $tokens['access_token'] ),
            'refresh_token' => $this->encrypt( $tokens['refresh_token'] ),
            'token_expires' => date( 'Y-m-d H:i:s', time() + $tokens['expires_in'] ),
            'calendar_id'   => $tokens['calendar_id'] ?? null,
        ), array( 'id' => (int) $state ) );

        // Redirect back to settings
        wp_redirect( admin_url( 'admin.php?page=ict-settings&tab=calendar&connected=1' ) );
        exit;
    }

    public function sync_all_calendars() {
        global $wpdb;

        $connections = $wpdb->get_results(
            "SELECT * FROM {$this->connections_table} WHERE is_active = 1"
        );

        foreach ( $connections as $connection ) {
            $this->sync_calendar( $connection );
        }
    }

    public function sync_project_to_calendar( $project_id, $project_data ) {
        global $wpdb;

        $connections = $wpdb->get_results(
            "SELECT * FROM {$this->connections_table}
             WHERE is_active = 1 AND sync_projects = 1
             AND sync_direction IN ('both', 'to_calendar')"
        );

        foreach ( $connections as $connection ) {
            $this->push_event( $connection, 'project', $project_id, $project_data );
        }
    }

    public function sync_task_to_calendar( $task_id, $task_data ) {
        global $wpdb;

        $connections = $wpdb->get_results(
            "SELECT * FROM {$this->connections_table}
             WHERE is_active = 1 AND sync_tasks = 1
             AND sync_direction IN ('both', 'to_calendar')"
        );

        foreach ( $connections as $connection ) {
            $this->push_event( $connection, 'task', $task_id, $task_data );
        }
    }

    private function sync_calendar( $connection ) {
        global $wpdb;

        $synced = 0;
        $errors = 0;

        try {
            // Pull events from external calendar
            if ( in_array( $connection->sync_direction, array( 'both', 'from_calendar' ), true ) ) {
                $external_events = $this->fetch_external_events( $connection );

                foreach ( $external_events as $event ) {
                    $existing = $wpdb->get_row( $wpdb->prepare(
                        "SELECT id FROM {$this->events_table}
                         WHERE connection_id = %d AND external_id = %s",
                        $connection->id, $event['id']
                    ) );

                    if ( $existing ) {
                        $wpdb->update( $this->events_table, array(
                            'title'          => $event['title'],
                            'description'    => $event['description'],
                            'start_datetime' => $event['start'],
                            'end_datetime'   => $event['end'],
                            'location'       => $event['location'],
                            'last_modified'  => current_time( 'mysql' ),
                        ), array( 'id' => $existing->id ) );
                    } else {
                        $wpdb->insert( $this->events_table, array(
                            'connection_id'    => $connection->id,
                            'external_id'      => $event['id'],
                            'internal_type'    => 'task',
                            'internal_id'      => 0,
                            'title'            => $event['title'],
                            'description'      => $event['description'],
                            'start_datetime'   => $event['start'],
                            'end_datetime'     => $event['end'],
                            'all_day'          => $event['all_day'] ? 1 : 0,
                            'location'         => $event['location'],
                        ) );
                    }
                    $synced++;
                }
            }

            // Push local events to external calendar
            if ( in_array( $connection->sync_direction, array( 'both', 'to_calendar' ), true ) ) {
                $local_events = $this->get_local_events_to_sync( $connection );

                foreach ( $local_events as $event ) {
                    $result = $this->push_to_external( $connection, $event );
                    if ( $result ) {
                        $synced++;
                    } else {
                        $errors++;
                    }
                }
            }

            $wpdb->update( $this->connections_table,
                array( 'last_sync' => current_time( 'mysql' ) ),
                array( 'id' => $connection->id )
            );

            $this->log_sync( $connection->id, 'pull', 'update', null, 'success',
                "Synced {$synced} events" );

        } catch ( Exception $e ) {
            $this->log_sync( $connection->id, 'pull', 'update', null, 'error', $e->getMessage() );
            $errors++;
        }

        return array(
            'synced' => $synced,
            'errors' => $errors,
        );
    }

    private function push_event( $connection, $type, $id, $data ) {
        global $wpdb;

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->events_table}
             WHERE connection_id = %d AND internal_type = %s AND internal_id = %d",
            $connection->id, $type, $id
        ) );

        $event_data = array(
            'connection_id'    => $connection->id,
            'internal_type'    => $type,
            'internal_id'      => $id,
            'title'            => $data['name'] ?? $data['title'],
            'description'      => $data['description'] ?? '',
            'start_datetime'   => $data['start_date'] ?? $data['due_date'],
            'end_datetime'     => $data['end_date'] ?? $data['due_date'],
            'last_modified'    => current_time( 'mysql' ),
        );

        if ( $existing ) {
            $wpdb->update( $this->events_table, $event_data, array( 'id' => $existing->id ) );
        } else {
            $wpdb->insert( $this->events_table, $event_data );
        }
    }

    private function fetch_external_events( $connection ) {
        // Provider-specific implementations
        switch ( $connection->provider ) {
            case 'google':
                return $this->fetch_google_events( $connection );
            case 'outlook':
                return $this->fetch_outlook_events( $connection );
            case 'caldav':
                return $this->fetch_caldav_events( $connection );
            default:
                return array();
        }
    }

    private function fetch_google_events( $connection ) {
        $access_token = $this->decrypt( $connection->access_token );
        if ( $this->is_token_expired( $connection ) ) {
            $access_token = $this->refresh_token( $connection );
        }

        $calendar_id = $connection->calendar_id ?: 'primary';
        $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events";
        $url .= '?timeMin=' . urlencode( date( 'c', strtotime( '-30 days' ) ) );
        $url .= '&timeMax=' . urlencode( date( 'c', strtotime( '+90 days' ) ) );

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => "Bearer {$access_token}" ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $events = array();

        foreach ( $body['items'] ?? array() as $item ) {
            $events[] = array(
                'id'          => $item['id'],
                'title'       => $item['summary'] ?? '',
                'description' => $item['description'] ?? '',
                'start'       => $item['start']['dateTime'] ?? $item['start']['date'],
                'end'         => $item['end']['dateTime'] ?? $item['end']['date'],
                'all_day'     => isset( $item['start']['date'] ),
                'location'    => $item['location'] ?? '',
            );
        }

        return $events;
    }

    private function fetch_outlook_events( $connection ) {
        $access_token = $this->decrypt( $connection->access_token );
        if ( $this->is_token_expired( $connection ) ) {
            $access_token = $this->refresh_token( $connection );
        }

        $url = 'https://graph.microsoft.com/v1.0/me/calendar/events';
        $url .= '?$filter=start/dateTime ge \'' . date( 'Y-m-d', strtotime( '-30 days' ) ) . '\'';

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => "Bearer {$access_token}" ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $events = array();

        foreach ( $body['value'] ?? array() as $item ) {
            $events[] = array(
                'id'          => $item['id'],
                'title'       => $item['subject'] ?? '',
                'description' => $item['bodyPreview'] ?? '',
                'start'       => $item['start']['dateTime'],
                'end'         => $item['end']['dateTime'],
                'all_day'     => $item['isAllDay'] ?? false,
                'location'    => $item['location']['displayName'] ?? '',
            );
        }

        return $events;
    }

    private function fetch_caldav_events( $connection ) {
        // CalDAV implementation would require a CalDAV library
        return array();
    }

    private function get_local_events_to_sync( $connection ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->events_table}
             WHERE connection_id = %d
             AND (external_id IS NULL OR last_modified > %s)
             AND start_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $connection->id,
            $connection->last_sync ?: '1970-01-01'
        ) );
    }

    private function push_to_external( $connection, $event ) {
        // Implementation depends on provider
        return true;
    }

    private function generate_ical( $events ) {
        $lines = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ICT Platform//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        );

        foreach ( $events as $event ) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event->id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
            $lines[] = 'DTSTART:' . date( 'Ymd\THis\Z', strtotime( $event->start_datetime ) );
            if ( $event->end_datetime ) {
                $lines[] = 'DTEND:' . date( 'Ymd\THis\Z', strtotime( $event->end_datetime ) );
            }
            $lines[] = 'SUMMARY:' . $this->escape_ical( $event->title );
            if ( $event->description ) {
                $lines[] = 'DESCRIPTION:' . $this->escape_ical( $event->description );
            }
            if ( $event->location ) {
                $lines[] = 'LOCATION:' . $this->escape_ical( $event->location );
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode( "\r\n", $lines );
    }

    private function escape_ical( $text ) {
        return preg_replace( '/([,;\\\\])/', '\\\\$1', str_replace( "\n", '\\n', $text ) );
    }

    private function get_oauth_url( $provider, $connection_id ) {
        $redirect_uri = rest_url( "ict/v1/calendar/oauth/{$provider}/callback" );

        if ( $provider === 'google' ) {
            $client_id = get_option( 'ict_google_client_id' );
            return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( array(
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'response_type' => 'code',
                'scope'         => 'https://www.googleapis.com/auth/calendar',
                'access_type'   => 'offline',
                'state'         => $connection_id,
            ) );
        }

        if ( $provider === 'outlook' ) {
            $client_id = get_option( 'ict_outlook_client_id' );
            return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query( array(
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'response_type' => 'code',
                'scope'         => 'Calendars.ReadWrite offline_access',
                'state'         => $connection_id,
            ) );
        }

        return '';
    }

    private function exchange_oauth_code( $provider, $code ) {
        // Implementation depends on provider
        return array(
            'access_token'  => '',
            'refresh_token' => '',
            'expires_in'    => 3600,
        );
    }

    private function is_token_expired( $connection ) {
        return $connection->token_expires && strtotime( $connection->token_expires ) < time();
    }

    private function refresh_token( $connection ) {
        // Implementation depends on provider
        return $this->decrypt( $connection->access_token );
    }

    private function encrypt( $data ) {
        $key = wp_salt( 'auth' );
        $iv = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
        return base64_encode( $iv . $encrypted );
    }

    private function decrypt( $data ) {
        $key = wp_salt( 'auth' );
        $data = base64_decode( $data );
        $iv = substr( $data, 0, 16 );
        $encrypted = substr( $data, 16 );
        return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
    }

    private function log_sync( $connection_id, $direction, $action, $event_id, $status, $message ) {
        global $wpdb;
        $wpdb->insert( $this->sync_log_table, array(
            'connection_id' => $connection_id,
            'direction'     => $direction,
            'action'        => $action,
            'event_id'      => $event_id,
            'status'        => $status,
            'message'       => $message,
        ) );
    }
}

function get_user_meta_by_key_value( $key, $value ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
        $key, $value
    ) );
}
