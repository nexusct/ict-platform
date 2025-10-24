<?php
/**
 * OpenAI API Integration Adapter
 *
 * Handles all interactions with OpenAI API for AI-powered features.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_OpenAI_Adapter
 *
 * Provides AI capabilities using OpenAI's GPT models.
 */
class ICT_OpenAI_Adapter {

	/**
	 * OpenAI API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * OpenAI API base URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://api.openai.com/v1';

	/**
	 * Default model to use.
	 *
	 * @var string
	 */
	private $default_model = 'gpt-4o';

	/**
	 * Organization ID (optional).
	 *
	 * @var string
	 */
	private $organization_id;

	/**
	 * Maximum tokens for responses.
	 *
	 * @var int
	 */
	private $max_tokens = 2000;

	/**
	 * Temperature for responses (0-2).
	 *
	 * @var float
	 */
	private $temperature = 0.7;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->api_key = $this->get_api_key();
		$this->organization_id = get_option( 'ict_openai_organization_id', '' );
		$this->default_model = get_option( 'ict_openai_model', 'gpt-4o' );
		$this->max_tokens = (int) get_option( 'ict_openai_max_tokens', 2000 );
		$this->temperature = (float) get_option( 'ict_openai_temperature', 0.7 );
	}

	/**
	 * Get decrypted API key.
	 *
	 * @since  1.0.0
	 * @return string API key.
	 */
	private function get_api_key() {
		$encrypted_key = get_option( 'ict_openai_api_key', '' );

		if ( empty( $encrypted_key ) ) {
			return '';
		}

		// Use the same encryption system as other credentials
		if ( class_exists( 'ICT_Admin_Settings' ) ) {
			$settings = new ICT_Admin_Settings();
			return $settings->decrypt( $encrypted_key );
		}

		return $encrypted_key;
	}

	/**
	 * Test OpenAI API connection.
	 *
	 * @since  1.0.0
	 * @return array|WP_Error Test result.
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key not configured.', 'ict-platform' ) );
		}

		$response = $this->make_request( 'models', 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'success' => true,
			'message' => __( 'Successfully connected to OpenAI API.', 'ict-platform' ),
			'models'  => isset( $response['data'] ) ? count( $response['data'] ) : 0,
		);
	}

	/**
	 * Generate chat completion.
	 *
	 * @since  1.0.0
	 * @param  array $messages Array of messages in OpenAI format.
	 * @param  array $options  Optional. Override default settings.
	 * @return array|WP_Error Response data.
	 */
	public function chat_completion( $messages, $options = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key not configured.', 'ict-platform' ) );
		}

		$defaults = array(
			'model'       => $this->default_model,
			'messages'    => $messages,
			'max_tokens'  => $this->max_tokens,
			'temperature' => $this->temperature,
		);

		$params = wp_parse_args( $options, $defaults );

		$response = $this->make_request( 'chat/completions', 'POST', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Analyze project for insights and recommendations.
	 *
	 * @since  1.0.0
	 * @param  int $project_id Project ID.
	 * @return array|WP_Error Analysis results.
	 */
	public function analyze_project( $project_id ) {
		global $wpdb;

		$project = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
				$project_id
			),
			ARRAY_A
		);

		if ( ! $project ) {
			return new WP_Error( 'project_not_found', __( 'Project not found.', 'ict-platform' ) );
		}

		// Get time entries
		$time_entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_TIME_ENTRIES_TABLE . " WHERE project_id = %d ORDER BY clock_in DESC LIMIT 50",
				$project_id
			),
			ARRAY_A
		);

		// Build context for AI
		$context = $this->build_project_context( $project, $time_entries );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an expert project manager and electrical contractor analyst. Analyze the project data and provide actionable insights, risk assessments, and recommendations.',
			),
			array(
				'role'    => 'user',
				'content' => "Analyze this project and provide:\n1. Overall project health assessment\n2. Budget and timeline risk analysis\n3. Resource utilization insights\n4. Specific recommendations for improvement\n\nProject Data:\n" . $context,
			),
		);

		$response = $this->chat_completion( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'analysis'   => $response['choices'][0]['message']['content'] ?? '',
			'project_id' => $project_id,
			'tokens'     => $response['usage'] ?? array(),
			'timestamp'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Analyze quote for pricing recommendations.
	 *
	 * @since  1.0.0
	 * @param  array $quote_data Quote data from QuoteWerks.
	 * @return array|WP_Error Analysis results.
	 */
	public function analyze_quote( $quote_data ) {
		$context = wp_json_encode( $quote_data, JSON_PRETTY_PRINT );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an expert electrical contractor and pricing analyst. Analyze quotes for competitive pricing, missing items, and optimization opportunities.',
			),
			array(
				'role'    => 'user',
				'content' => "Analyze this quote and provide:\n1. Pricing competitiveness assessment\n2. Potential missing line items or services\n3. Upsell opportunities\n4. Risk factors\n5. Recommendations for quote optimization\n\nQuote Data:\n" . $context,
			),
		);

		$response = $this->chat_completion( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'analysis' => $response['choices'][0]['message']['content'] ?? '',
			'quote'    => $quote_data,
			'tokens'   => $response['usage'] ?? array(),
		);
	}

	/**
	 * Generate project description from basic details.
	 *
	 * @since  1.0.0
	 * @param  array $project_details Basic project info.
	 * @return string|WP_Error Generated description.
	 */
	public function generate_project_description( $project_details ) {
		$context = wp_json_encode( $project_details, JSON_PRETTY_PRINT );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a professional technical writer specializing in electrical contracting projects. Write clear, detailed project descriptions.',
			),
			array(
				'role'    => 'user',
				'content' => "Generate a professional project description (2-3 paragraphs) based on this information:\n" . $context,
			),
		);

		$response = $this->chat_completion( $messages, array( 'max_tokens' => 500 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Suggest time entry descriptions based on project and task.
	 *
	 * @since  1.0.0
	 * @param  int    $project_id Project ID.
	 * @param  string $task_type  Type of task.
	 * @return array|WP_Error Suggested descriptions.
	 */
	public function suggest_time_entry_description( $project_id, $task_type = '' ) {
		global $wpdb;

		// Get recent time entries for this project
		$recent_entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT description FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE project_id = %d AND description != ''
				ORDER BY clock_in DESC LIMIT 10",
				$project_id
			),
			ARRAY_A
		);

		$context = array(
			'project_id' => $project_id,
			'task_type'  => $task_type,
			'recent'     => wp_list_pluck( $recent_entries, 'description' ),
		);

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are helping electrical contractors write clear, professional time entry descriptions. Generate 3-5 concise suggestions.',
			),
			array(
				'role'    => 'user',
				'content' => "Generate 3-5 professional time entry descriptions for:\n" . wp_json_encode( $context, JSON_PRETTY_PRINT ) . "\n\nReturn only the suggestions, one per line, without numbering.",
			),
		);

		$response = $this->chat_completion( $messages, array( 'max_tokens' => 200 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$content = $response['choices'][0]['message']['content'] ?? '';
		$suggestions = array_filter( explode( "\n", $content ) );

		return array_slice( $suggestions, 0, 5 );
	}

	/**
	 * Analyze inventory needs based on upcoming projects.
	 *
	 * @since  1.0.0
	 * @param  array $project_ids Array of project IDs.
	 * @return array|WP_Error Inventory recommendations.
	 */
	public function analyze_inventory_needs( $project_ids ) {
		global $wpdb;

		if ( empty( $project_ids ) ) {
			return new WP_Error( 'no_projects', __( 'No projects provided.', 'ict-platform' ) );
		}

		$placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );

		$projects = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id IN ($placeholders)",
				...$project_ids
			),
			ARRAY_A
		);

		$context = wp_json_encode( $projects, JSON_PRETTY_PRINT );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are an expert electrical contractor inventory manager. Analyze project requirements and recommend inventory needs.',
			),
			array(
				'role'    => 'user',
				'content' => "Based on these upcoming projects, recommend:\n1. Key materials and quantities needed\n2. Specialized tools or equipment\n3. Potential supply chain risks\n4. Optimal procurement timing\n\nProjects:\n" . $context,
			),
		);

		$response = $this->chat_completion( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'recommendations' => $response['choices'][0]['message']['content'] ?? '',
			'project_count'   => count( $projects ),
			'tokens'          => $response['usage'] ?? array(),
		);
	}

	/**
	 * Generate report summary from data.
	 *
	 * @since  1.0.0
	 * @param  array  $report_data Report data.
	 * @param  string $report_type Type of report.
	 * @return string|WP_Error Generated summary.
	 */
	public function generate_report_summary( $report_data, $report_type = 'general' ) {
		$context = wp_json_encode( $report_data, JSON_PRETTY_PRINT );

		$prompts = array(
			'project'   => 'Generate an executive summary for this project performance report. Include key metrics, trends, and actionable insights.',
			'financial' => 'Generate an executive summary for this financial report. Highlight revenue, expenses, profitability, and financial health.',
			'time'      => 'Generate an executive summary for this time tracking report. Analyze productivity, utilization, and efficiency.',
			'inventory' => 'Generate an executive summary for this inventory report. Cover stock levels, turnover, and procurement needs.',
			'general'   => 'Generate a clear, concise executive summary of this report data.',
		);

		$prompt = isset( $prompts[ $report_type ] ) ? $prompts[ $report_type ] : $prompts['general'];

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'You are a business analyst creating executive summaries for electrical contracting company reports.',
			),
			array(
				'role'    => 'user',
				'content' => $prompt . "\n\nReport Data:\n" . $context,
			),
		);

		$response = $this->chat_completion( $messages, array( 'max_tokens' => 600 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Smart search using embeddings (future enhancement).
	 *
	 * @since  1.0.0
	 * @param  string $query Search query.
	 * @return array|WP_Error Search results.
	 */
	public function semantic_search( $query ) {
		// Create embedding for query
		$embedding_response = $this->create_embedding( $query );

		if ( is_wp_error( $embedding_response ) ) {
			return $embedding_response;
		}

		// This would query a vector database or use similarity search
		// Placeholder for future implementation
		return array(
			'query'   => $query,
			'results' => array(),
			'message' => __( 'Semantic search not yet implemented.', 'ict-platform' ),
		);
	}

	/**
	 * Create text embedding.
	 *
	 * @since  1.0.0
	 * @param  string $text Text to embed.
	 * @return array|WP_Error Embedding data.
	 */
	public function create_embedding( $text ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key not configured.', 'ict-platform' ) );
		}

		$params = array(
			'model' => 'text-embedding-3-small',
			'input' => $text,
		);

		return $this->make_request( 'embeddings', 'POST', $params );
	}

	/**
	 * Build project context for AI analysis.
	 *
	 * @since  1.0.0
	 * @param  array $project      Project data.
	 * @param  array $time_entries Time entries data.
	 * @return string Context string.
	 */
	private function build_project_context( $project, $time_entries ) {
		$context = array(
			'Project Information' => array(
				'Name'          => $project['project_name'] ?? 'N/A',
				'Status'        => $project['status'] ?? 'N/A',
				'Budget'        => isset( $project['budget_amount'] ) ? '$' . number_format( $project['budget_amount'], 2 ) : 'N/A',
				'Start Date'    => $project['start_date'] ?? 'N/A',
				'End Date'      => $project['end_date'] ?? 'N/A',
				'Client'        => $project['client_name'] ?? 'N/A',
				'Priority'      => $project['priority'] ?? 'N/A',
			),
			'Time Tracking' => array(
				'Total Entries'  => count( $time_entries ),
				'Total Hours'    => $this->calculate_total_hours( $time_entries ),
				'Recent Entries' => $this->summarize_time_entries( $time_entries, 5 ),
			),
		);

		return wp_json_encode( $context, JSON_PRETTY_PRINT );
	}

	/**
	 * Calculate total hours from time entries.
	 *
	 * @since  1.0.0
	 * @param  array $time_entries Time entries.
	 * @return float Total hours.
	 */
	private function calculate_total_hours( $time_entries ) {
		$total = 0;
		foreach ( $time_entries as $entry ) {
			if ( ! empty( $entry['hours_worked'] ) ) {
				$total += (float) $entry['hours_worked'];
			}
		}
		return round( $total, 2 );
	}

	/**
	 * Summarize time entries.
	 *
	 * @since  1.0.0
	 * @param  array $time_entries Time entries.
	 * @param  int   $limit        Number of entries to include.
	 * @return array Summarized entries.
	 */
	private function summarize_time_entries( $time_entries, $limit = 5 ) {
		$summary = array();
		$entries = array_slice( $time_entries, 0, $limit );

		foreach ( $entries as $entry ) {
			$summary[] = array(
				'date'        => $entry['clock_in'] ?? '',
				'hours'       => $entry['hours_worked'] ?? 0,
				'description' => $entry['description'] ?? '',
			);
		}

		return $summary;
	}

	/**
	 * Make API request to OpenAI.
	 *
	 * @since  1.0.0
	 * @param  string $endpoint API endpoint.
	 * @param  string $method   HTTP method.
	 * @param  array  $data     Request data.
	 * @return array|WP_Error Response data.
	 */
	private function make_request( $endpoint, $method = 'GET', $data = array() ) {
		$url = $this->api_url . '/' . ltrim( $endpoint, '/' );

		$headers = array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		);

		if ( ! empty( $this->organization_id ) ) {
			$headers['OpenAI-Organization'] = $this->organization_id;
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 60,
		);

		if ( 'POST' === $method && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			$error_message = isset( $data['error']['message'] )
				? $data['error']['message']
				: __( 'OpenAI API request failed.', 'ict-platform' );

			return new WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
		}

		// Log usage for tracking
		$this->log_usage( $endpoint, $data );

		return $data;
	}

	/**
	 * Log API usage for tracking costs.
	 *
	 * @since  1.0.0
	 * @param  string $endpoint Endpoint called.
	 * @param  array  $response Response data.
	 * @return void
	 */
	private function log_usage( $endpoint, $response ) {
		if ( ! isset( $response['usage'] ) ) {
			return;
		}

		global $wpdb;

		$usage = $response['usage'];

		$wpdb->insert(
			$wpdb->prefix . 'ict_ai_usage',
			array(
				'endpoint'         => $endpoint,
				'model'            => $response['model'] ?? $this->default_model,
				'prompt_tokens'    => $usage['prompt_tokens'] ?? 0,
				'completion_tokens' => $usage['completion_tokens'] ?? 0,
				'total_tokens'     => $usage['total_tokens'] ?? 0,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Get usage statistics.
	 *
	 * @since  1.0.0
	 * @param  int $days Number of days to look back.
	 * @return array Usage stats.
	 */
	public function get_usage_stats( $days = 30 ) {
		global $wpdb;

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_requests,
					SUM(prompt_tokens) as total_prompt_tokens,
					SUM(completion_tokens) as total_completion_tokens,
					SUM(total_tokens) as total_tokens
				FROM {$wpdb->prefix}ict_ai_usage
				WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			),
			ARRAY_A
		);

		return $stats ?: array();
	}
}
