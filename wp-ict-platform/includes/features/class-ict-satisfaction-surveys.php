<?php
/**
 * Client Satisfaction Surveys
 *
 * Create and send customer satisfaction surveys.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ICT_Satisfaction_Surveys {

	private static $instance = null;
	private $surveys_table;
	private $questions_table;
	private $responses_table;
	private $answers_table;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->surveys_table   = $wpdb->prefix . 'ict_surveys';
		$this->questions_table = $wpdb->prefix . 'ict_survey_questions';
		$this->responses_table = $wpdb->prefix . 'ict_survey_responses';
		$this->answers_table   = $wpdb->prefix . 'ict_survey_answers';
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
		add_action( 'ict_project_completed', array( $this, 'auto_send_survey' ), 10, 2 );
	}

	public function register_routes() {
		register_rest_route(
			'ict/v1',
			'/surveys',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_surveys' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_survey' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/surveys/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_survey' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_survey' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_survey' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/surveys/(?P<id>\d+)/questions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_questions' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_question' ),
					'permission_callback' => array( $this, 'check_edit_permission' ),
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/surveys/(?P<id>\d+)/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_survey' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/surveys/(?P<id>\d+)/responses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_responses' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/surveys/(?P<id>\d+)/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Public endpoint for submitting responses
		register_rest_route(
			'ict/v1',
			'/surveys/respond/(?P<token>[a-zA-Z0-9]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_survey_for_response' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_response' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'ict/v1',
			'/surveys/nps-score',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_nps_score' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'view_ict_reports' ) || current_user_can( 'manage_options' );
	}

	public function check_edit_permission() {
		return current_user_can( 'manage_ict_settings' ) || current_user_can( 'manage_options' );
	}

	public function maybe_create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE IF NOT EXISTS {$this->surveys_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            survey_type enum('nps','csat','custom') DEFAULT 'custom',
            trigger_type enum('manual','project_complete','ticket_close') DEFAULT 'manual',
            is_active tinyint(1) DEFAULT 1,
            send_delay_hours int DEFAULT 24,
            reminder_after_days int DEFAULT 3,
            expires_after_days int DEFAULT 14,
            thank_you_message text,
            email_subject varchar(255),
            email_body text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY survey_type (survey_type),
            KEY is_active (is_active)
        ) {$charset_collate};";

		$sql2 = "CREATE TABLE IF NOT EXISTS {$this->questions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            survey_id bigint(20) unsigned NOT NULL,
            question_text text NOT NULL,
            question_type enum('rating','nps','text','choice','multichoice') NOT NULL,
            options text,
            is_required tinyint(1) DEFAULT 1,
            display_order int DEFAULT 0,
            PRIMARY KEY (id),
            KEY survey_id (survey_id)
        ) {$charset_collate};";

		$sql3 = "CREATE TABLE IF NOT EXISTS {$this->responses_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            survey_id bigint(20) unsigned NOT NULL,
            response_token varchar(64) NOT NULL,
            project_id bigint(20) unsigned,
            client_id bigint(20) unsigned,
            client_email varchar(255),
            client_name varchar(255),
            status enum('pending','started','completed','expired') DEFAULT 'pending',
            overall_score decimal(3,1),
            nps_score int,
            sent_at datetime,
            started_at datetime,
            completed_at datetime,
            ip_address varchar(45),
            user_agent varchar(500),
            PRIMARY KEY (id),
            UNIQUE KEY response_token (response_token),
            KEY survey_id (survey_id),
            KEY project_id (project_id),
            KEY client_id (client_id),
            KEY status (status)
        ) {$charset_collate};";

		$sql4 = "CREATE TABLE IF NOT EXISTS {$this->answers_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            response_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            answer_value text,
            answer_score decimal(3,1),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY question_id (question_id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );
	}

	public function get_surveys( $request ) {
		global $wpdb;

		$active_only = $request->get_param( 'active_only' ) === 'true';

		$where = $active_only ? 'WHERE is_active = 1' : '';

		$surveys = $wpdb->get_results(
			"SELECT s.*,
                    (SELECT COUNT(*) FROM {$this->responses_table} WHERE survey_id = s.id) as response_count,
                    (SELECT COUNT(*) FROM {$this->responses_table} WHERE survey_id = s.id AND status = 'completed') as completed_count,
                    (SELECT AVG(overall_score) FROM {$this->responses_table} WHERE survey_id = s.id AND status = 'completed') as avg_score
             FROM {$this->surveys_table} s
             {$where}
             ORDER BY s.created_at DESC"
		);

		return rest_ensure_response( $surveys );
	}

	public function get_survey( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$survey = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->surveys_table} WHERE id = %d",
				$id
			)
		);

		if ( ! $survey ) {
			return new WP_Error( 'not_found', 'Survey not found', array( 'status' => 404 ) );
		}

		$survey->questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->questions_table} WHERE survey_id = %d ORDER BY display_order",
				$id
			)
		);

		foreach ( $survey->questions as $q ) {
			$q->options = json_decode( $q->options, true );
		}

		return rest_ensure_response( $survey );
	}

	public function create_survey( $request ) {
		global $wpdb;

		$data = array(
			'name'                => sanitize_text_field( $request->get_param( 'name' ) ),
			'description'         => sanitize_textarea_field( $request->get_param( 'description' ) ),
			'survey_type'         => sanitize_text_field( $request->get_param( 'survey_type' ) ?: 'custom' ),
			'trigger_type'        => sanitize_text_field( $request->get_param( 'trigger_type' ) ?: 'manual' ),
			'send_delay_hours'    => (int) $request->get_param( 'send_delay_hours' ) ?: 24,
			'reminder_after_days' => (int) $request->get_param( 'reminder_after_days' ) ?: 3,
			'expires_after_days'  => (int) $request->get_param( 'expires_after_days' ) ?: 14,
			'thank_you_message'   => sanitize_textarea_field( $request->get_param( 'thank_you_message' ) ),
			'email_subject'       => sanitize_text_field( $request->get_param( 'email_subject' ) ),
			'email_body'          => wp_kses_post( $request->get_param( 'email_body' ) ),
			'created_by'          => get_current_user_id(),
		);

		$wpdb->insert( $this->surveys_table, $data );
		$survey_id = $wpdb->insert_id;

		// Add default questions for NPS
		if ( $data['survey_type'] === 'nps' ) {
			$this->add_default_nps_questions( $survey_id );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $survey_id,
			)
		);
	}

	public function update_survey( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$fields = array( 'name', 'description', 'trigger_type', 'thank_you_message', 'email_subject', 'email_body' );
		$data   = array();

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( $value );
			}
		}

		$int_fields = array( 'send_delay_hours', 'reminder_after_days', 'expires_after_days', 'is_active' );
		foreach ( $int_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = (int) $value;
			}
		}

		if ( empty( $data ) ) {
			return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
		}

		$wpdb->update( $this->surveys_table, $data, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_survey( $request ) {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$wpdb->delete(
			$this->answers_table,
			array( 'response_id' ),
			array( '%d' ),
			"WHERE response_id IN (SELECT id FROM {$this->responses_table} WHERE survey_id = %d)",
			array( $id )
		);
		$wpdb->delete( $this->responses_table, array( 'survey_id' => $id ) );
		$wpdb->delete( $this->questions_table, array( 'survey_id' => $id ) );
		$wpdb->delete( $this->surveys_table, array( 'id' => $id ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_questions( $request ) {
		global $wpdb;
		$survey_id = (int) $request->get_param( 'id' );

		$questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->questions_table} WHERE survey_id = %d ORDER BY display_order",
				$survey_id
			)
		);

		foreach ( $questions as $q ) {
			$q->options = json_decode( $q->options, true );
		}

		return rest_ensure_response( $questions );
	}

	public function add_question( $request ) {
		global $wpdb;
		$survey_id = (int) $request->get_param( 'id' );

		$data = array(
			'survey_id'     => $survey_id,
			'question_text' => sanitize_text_field( $request->get_param( 'question_text' ) ),
			'question_type' => sanitize_text_field( $request->get_param( 'question_type' ) ),
			'is_required'   => (int) $request->get_param( 'is_required' ),
			'display_order' => (int) $request->get_param( 'display_order' ),
		);

		$options = $request->get_param( 'options' );
		if ( $options ) {
			$data['options'] = wp_json_encode( $options );
		}

		$wpdb->insert( $this->questions_table, $data );

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $wpdb->insert_id,
			)
		);
	}

	public function send_survey( $request ) {
		global $wpdb;
		$survey_id = (int) $request->get_param( 'id' );

		$recipients = $request->get_param( 'recipients' );
		$project_id = $request->get_param( 'project_id' );

		if ( empty( $recipients ) ) {
			return new WP_Error( 'no_recipients', 'No recipients specified', array( 'status' => 400 ) );
		}

		$survey = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->surveys_table} WHERE id = %d",
				$survey_id
			)
		);

		if ( ! $survey ) {
			return new WP_Error( 'not_found', 'Survey not found', array( 'status' => 404 ) );
		}

		$sent = 0;
		foreach ( $recipients as $recipient ) {
			$token = wp_generate_password( 32, false );

			$wpdb->insert(
				$this->responses_table,
				array(
					'survey_id'      => $survey_id,
					'response_token' => $token,
					'project_id'     => $project_id,
					'client_email'   => sanitize_email( $recipient['email'] ),
					'client_name'    => sanitize_text_field( $recipient['name'] ?? '' ),
					'client_id'      => $recipient['client_id'] ?? null,
					'sent_at'        => current_time( 'mysql' ),
				)
			);

			// Send email
			$survey_url = add_query_arg( 'token', $token, home_url( '/survey/' ) );
			$this->send_survey_email( $survey, $recipient, $survey_url );
			++$sent;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'sent'    => $sent,
			)
		);
	}

	public function get_responses( $request ) {
		global $wpdb;
		$survey_id = (int) $request->get_param( 'id' );
		$status    = $request->get_param( 'status' );

		$where  = 'survey_id = %d';
		$values = array( $survey_id );

		if ( $status ) {
			$where   .= ' AND status = %s';
			$values[] = $status;
		}

		$responses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, p.name as project_name
             FROM {$this->responses_table} r
             LEFT JOIN {$wpdb->prefix}ict_projects p ON r.project_id = p.id
             WHERE {$where}
             ORDER BY r.completed_at DESC",
				$values
			)
		);

		foreach ( $responses as $response ) {
			$response->answers = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.*, q.question_text, q.question_type
                 FROM {$this->answers_table} a
                 JOIN {$this->questions_table} q ON a.question_id = q.id
                 WHERE a.response_id = %d",
					$response->id
				)
			);
		}

		return rest_ensure_response( $responses );
	}

	public function get_analytics( $request ) {
		global $wpdb;
		$survey_id = (int) $request->get_param( 'id' );

		// Response stats
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(overall_score) as avg_score,
                    AVG(nps_score) as avg_nps
             FROM {$this->responses_table}
             WHERE survey_id = %d",
				$survey_id
			)
		);

		$stats->response_rate = $stats->total_sent > 0
			? round( ( $stats->completed / $stats->total_sent ) * 100, 1 )
			: 0;

		// Question breakdown
		$questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.question_text, q.question_type,
                    AVG(a.answer_score) as avg_score,
                    COUNT(a.id) as response_count
             FROM {$this->questions_table} q
             LEFT JOIN {$this->answers_table} a ON q.id = a.question_id
             WHERE q.survey_id = %d
             GROUP BY q.id",
				$survey_id
			)
		);

		// Response timeline
		$timeline = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(completed_at) as date, COUNT(*) as count, AVG(overall_score) as avg_score
             FROM {$this->responses_table}
             WHERE survey_id = %d AND status = 'completed'
             GROUP BY DATE(completed_at)
             ORDER BY date DESC LIMIT 30",
				$survey_id
			)
		);

		return rest_ensure_response(
			array(
				'stats'     => $stats,
				'questions' => $questions,
				'timeline'  => $timeline,
			)
		);
	}

	public function get_survey_for_response( $request ) {
		global $wpdb;
		$token = sanitize_text_field( $request->get_param( 'token' ) );

		$response = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, s.name as survey_name, s.description, s.thank_you_message
             FROM {$this->responses_table} r
             JOIN {$this->surveys_table} s ON r.survey_id = s.id
             WHERE r.response_token = %s",
				$token
			)
		);

		if ( ! $response ) {
			return new WP_Error( 'not_found', 'Survey not found', array( 'status' => 404 ) );
		}

		if ( $response->status === 'completed' ) {
			return rest_ensure_response(
				array(
					'completed'         => true,
					'thank_you_message' => $response->thank_you_message,
				)
			);
		}

		if ( $response->status === 'expired' ) {
			return new WP_Error( 'expired', 'This survey has expired', array( 'status' => 410 ) );
		}

		// Mark as started
		if ( $response->status === 'pending' ) {
			$wpdb->update(
				$this->responses_table,
				array(
					'status'     => 'started',
					'started_at' => current_time( 'mysql' ),
				),
				array( 'id' => $response->id )
			);
		}

		$questions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, question_text, question_type, options, is_required
             FROM {$this->questions_table}
             WHERE survey_id = %d ORDER BY display_order",
				$response->survey_id
			)
		);

		foreach ( $questions as $q ) {
			$q->options = json_decode( $q->options, true );
		}

		return rest_ensure_response(
			array(
				'survey_name' => $response->survey_name,
				'description' => $response->description,
				'questions'   => $questions,
			)
		);
	}

	public function submit_response( $request ) {
		global $wpdb;
		$token   = sanitize_text_field( $request->get_param( 'token' ) );
		$answers = $request->get_param( 'answers' );

		$response = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->responses_table} WHERE response_token = %s",
				$token
			)
		);

		if ( ! $response ) {
			return new WP_Error( 'not_found', 'Survey not found', array( 'status' => 404 ) );
		}

		if ( $response->status === 'completed' ) {
			return new WP_Error( 'already_completed', 'Survey already completed', array( 'status' => 400 ) );
		}

		$total_score = 0;
		$score_count = 0;
		$nps_score   = null;

		foreach ( $answers as $answer ) {
			$question = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->questions_table} WHERE id = %d",
					$answer['question_id']
				)
			);

			$answer_score = null;
			if ( in_array( $question->question_type, array( 'rating', 'nps' ), true ) ) {
				$answer_score = (float) $answer['value'];
				$total_score += $answer_score;
				++$score_count;

				if ( $question->question_type === 'nps' ) {
					$nps_score = (int) $answer['value'];
				}
			}

			$wpdb->insert(
				$this->answers_table,
				array(
					'response_id'  => $response->id,
					'question_id'  => $answer['question_id'],
					'answer_value' => is_array( $answer['value'] ) ? wp_json_encode( $answer['value'] ) : $answer['value'],
					'answer_score' => $answer_score,
				)
			);
		}

		$overall_score = $score_count > 0 ? $total_score / $score_count : null;

		$wpdb->update(
			$this->responses_table,
			array(
				'status'        => 'completed',
				'completed_at'  => current_time( 'mysql' ),
				'overall_score' => $overall_score,
				'nps_score'     => $nps_score,
				'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
				'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
			),
			array( 'id' => $response->id )
		);

		do_action( 'ict_survey_completed', $response->id, $overall_score, $nps_score );

		return rest_ensure_response(
			array(
				'success'       => true,
				'overall_score' => $overall_score,
			)
		);
	}

	public function get_nps_score( $request ) {
		global $wpdb;

		$period = $request->get_param( 'period' ) ?: 'all';

		$where = "status = 'completed' AND nps_score IS NOT NULL";
		if ( $period !== 'all' ) {
			$days      = array(
				'7d'  => 7,
				'30d' => 30,
				'90d' => 90,
				'1y'  => 365,
			);
			$day_count = $days[ $period ] ?? 30;
			$where    .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL {$day_count} DAY)";
		}

		$scores = $wpdb->get_results(
			"SELECT nps_score, COUNT(*) as count
             FROM {$this->responses_table}
             WHERE {$where}
             GROUP BY nps_score"
		);

		$promoters  = 0;
		$passives   = 0;
		$detractors = 0;
		$total      = 0;

		foreach ( $scores as $score ) {
			$total += $score->count;
			if ( $score->nps_score >= 9 ) {
				$promoters += $score->count;
			} elseif ( $score->nps_score >= 7 ) {
				$passives += $score->count;
			} else {
				$detractors += $score->count;
			}
		}

		$nps = $total > 0
			? round( ( ( $promoters - $detractors ) / $total ) * 100 )
			: 0;

		return rest_ensure_response(
			array(
				'nps'        => $nps,
				'promoters'  => $total > 0 ? round( ( $promoters / $total ) * 100, 1 ) : 0,
				'passives'   => $total > 0 ? round( ( $passives / $total ) * 100, 1 ) : 0,
				'detractors' => $total > 0 ? round( ( $detractors / $total ) * 100, 1 ) : 0,
				'total'      => $total,
			)
		);
	}

	public function auto_send_survey( $project_id, $project_data ) {
		global $wpdb;

		$survey = $wpdb->get_row(
			"SELECT * FROM {$this->surveys_table}
             WHERE trigger_type = 'project_complete' AND is_active = 1
             LIMIT 1"
		);

		if ( ! $survey ) {
			return;
		}

		// Get client email from project
		$client_email = $project_data['client_email'] ?? null;
		if ( ! $client_email ) {
			return;
		}

		// Schedule sending based on delay
		if ( $survey->send_delay_hours > 0 ) {
			wp_schedule_single_event(
				time() + ( $survey->send_delay_hours * 3600 ),
				'ict_send_survey',
				array( $survey->id, $project_id, $client_email )
			);
		} else {
			$this->send_survey_to_client( $survey->id, $project_id, $client_email );
		}
	}

	private function send_survey_to_client( $survey_id, $project_id, $email ) {
		global $wpdb;

		$survey = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->surveys_table} WHERE id = %d",
				$survey_id
			)
		);

		$token = wp_generate_password( 32, false );

		$wpdb->insert(
			$this->responses_table,
			array(
				'survey_id'      => $survey_id,
				'response_token' => $token,
				'project_id'     => $project_id,
				'client_email'   => $email,
				'sent_at'        => current_time( 'mysql' ),
			)
		);

		$survey_url = add_query_arg( 'token', $token, home_url( '/survey/' ) );
		$this->send_survey_email( $survey, array( 'email' => $email ), $survey_url );
	}

	private function send_survey_email( $survey, $recipient, $survey_url ) {
		$subject = $survey->email_subject ?: 'We value your feedback';
		$body    = $survey->email_body ?: "Please take a moment to share your experience with us.\n\n{survey_url}";
		$body    = str_replace( '{survey_url}', $survey_url, $body );
		$body    = str_replace( '{name}', $recipient['name'] ?? '', $body );

		wp_mail( $recipient['email'], $subject, $body );
	}

	private function add_default_nps_questions( $survey_id ) {
		global $wpdb;

		$wpdb->insert(
			$this->questions_table,
			array(
				'survey_id'     => $survey_id,
				'question_text' => 'How likely are you to recommend us to a friend or colleague?',
				'question_type' => 'nps',
				'is_required'   => 1,
				'display_order' => 1,
			)
		);

		$wpdb->insert(
			$this->questions_table,
			array(
				'survey_id'     => $survey_id,
				'question_text' => 'What is the primary reason for your score?',
				'question_type' => 'text',
				'is_required'   => 0,
				'display_order' => 2,
			)
		);
	}
}
