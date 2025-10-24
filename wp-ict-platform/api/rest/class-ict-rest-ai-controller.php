<?php
/**
 * REST API: AI Controller
 *
 * Handles AI-powered REST API endpoints.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_REST_AI_Controller
 *
 * AI features REST API controller.
 */
class ICT_REST_AI_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ict/v1';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'ai';

	/**
	 * Register routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Analyze project
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/analyze/project/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'analyze_project' ),
					'permission_callback' => array( $this, 'check_project_permission' ),
					'args'                => $this->get_project_params(),
				),
			)
		);

		// Analyze quote
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/analyze/quote',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'analyze_quote' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
					'args'                => $this->get_quote_params(),
				),
			)
		);

		// Generate project description
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate/description',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_description' ),
					'permission_callback' => array( $this, 'check_project_permission' ),
					'args'                => $this->get_description_params(),
				),
			)
		);

		// Time entry suggestions
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/suggest/time-entry',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'suggest_time_entry' ),
					'permission_callback' => array( $this, 'check_time_permission' ),
					'args'                => $this->get_time_entry_params(),
				),
			)
		);

		// Inventory analysis
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/analyze/inventory',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'analyze_inventory' ),
					'permission_callback' => array( $this, 'check_inventory_permission' ),
					'args'                => $this->get_inventory_params(),
				),
			)
		);

		// Generate report summary
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate/report-summary',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_report_summary' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
					'args'                => $this->get_report_params(),
				),
			)
		);

		// Chat completion (general purpose)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/chat',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'chat_completion' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
					'args'                => $this->get_chat_params(),
				),
			)
		);

		// Get usage stats
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/usage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_usage_stats' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
					'args'                => array(
						'days' => array(
							'required' => false,
							'default'  => 30,
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 365,
						),
					),
				),
			)
		);
	}

	/**
	 * Analyze project.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function analyze_project( $request ) {
		if ( ! get_option( 'ict_ai_project_analysis', true ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'Project analysis feature is disabled.', 'ict-platform' ),
				array( 'status' => 403 )
			);
		}

		$project_id = $request->get_param( 'id' );

		if ( ! class_exists( 'ICT_OpenAI_Adapter' ) ) {
			return new WP_Error(
				'adapter_not_loaded',
				__( 'OpenAI adapter not loaded.', 'ict-platform' ),
				array( 'status' => 500 )
			);
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->analyze_project( $project_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Analyze quote.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function analyze_quote( $request ) {
		if ( ! get_option( 'ict_ai_quote_analysis', true ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'Quote analysis feature is disabled.', 'ict-platform' ),
				array( 'status' => 403 )
			);
		}

		$quote_data = $request->get_param( 'quote_data' );

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->analyze_quote( $quote_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Generate description.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function generate_description( $request ) {
		$details = $request->get_param( 'details' );

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->generate_project_description( $details );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'description' => $result ), 200 );
	}

	/**
	 * Suggest time entry.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function suggest_time_entry( $request ) {
		if ( ! get_option( 'ict_ai_time_suggestions', true ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'Time entry suggestions feature is disabled.', 'ict-platform' ),
				array( 'status' => 403 )
			);
		}

		$project_id = $request->get_param( 'project_id' );
		$task_type = $request->get_param( 'task_type' );

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->suggest_time_entry_description( $project_id, $task_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'suggestions' => $result ), 200 );
	}

	/**
	 * Analyze inventory.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function analyze_inventory( $request ) {
		if ( ! get_option( 'ict_ai_inventory_analysis', true ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'Inventory analysis feature is disabled.', 'ict-platform' ),
				array( 'status' => 403 )
			);
		}

		$project_ids = $request->get_param( 'project_ids' );

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->analyze_inventory_needs( $project_ids );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Generate report summary.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function generate_report_summary( $request ) {
		if ( ! get_option( 'ict_ai_report_summaries', true ) ) {
			return new WP_Error(
				'feature_disabled',
				__( 'Report summaries feature is disabled.', 'ict-platform' ),
				array( 'status' => 403 )
			);
		}

		$report_data = $request->get_param( 'report_data' );
		$report_type = $request->get_param( 'report_type' );

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->generate_report_summary( $report_data, $report_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'summary' => $result ), 200 );
	}

	/**
	 * Chat completion.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function chat_completion( $request ) {
		$messages = $request->get_param( 'messages' );
		$options = $request->get_param( 'options' ) ?: array();

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->chat_completion( $messages, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get usage stats.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_usage_stats( $request ) {
		$days = $request->get_param( 'days' );

		$adapter = new ICT_OpenAI_Adapter();
		$stats = $adapter->get_usage_stats( $days );

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Check project permission.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function check_project_permission( $request ) {
		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'edit_ict_projects' );
	}

	/**
	 * Check time permission.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function check_time_permission( $request ) {
		return current_user_can( 'edit_ict_time_entries' );
	}

	/**
	 * Check inventory permission.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function check_inventory_permission( $request ) {
		return current_user_can( 'manage_ict_inventory' );
	}

	/**
	 * Check manage permission.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request Request object.
	 * @return bool True if permitted.
	 */
	public function check_manage_permission( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get project parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_project_params() {
		return array(
			'id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get quote parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_quote_params() {
		return array(
			'quote_data' => array(
				'required' => true,
				'type'     => 'object',
			),
		);
	}

	/**
	 * Get description parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_description_params() {
		return array(
			'details' => array(
				'required'          => true,
				'type'              => 'object',
				'sanitize_callback' => function( $value ) {
					return $value; // Handled by adapter
				},
			),
		);
	}

	/**
	 * Get time entry parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_time_entry_params() {
		return array(
			'project_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'task_type' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get inventory parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_inventory_params() {
		return array(
			'project_ids' => array(
				'required' => true,
				'type'     => 'array',
				'items'    => array(
					'type' => 'integer',
				),
			),
		);
	}

	/**
	 * Get report parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_report_params() {
		return array(
			'report_data' => array(
				'required' => true,
				'type'     => 'object',
			),
			'report_type' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'general',
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array( 'project', 'financial', 'time', 'inventory', 'general' ),
			),
		);
	}

	/**
	 * Get chat parameters.
	 *
	 * @since  1.0.0
	 * @return array Parameters.
	 */
	private function get_chat_params() {
		return array(
			'messages' => array(
				'required' => true,
				'type'     => 'array',
			),
			'options' => array(
				'required' => false,
				'type'     => 'object',
			),
		);
	}
}
