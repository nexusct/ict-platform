<?php
/**
 * AI Admin Settings and Interface
 *
 * Manages OpenAI integration settings and AI features admin interface.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Admin_AI
 *
 * Handles AI settings and admin interface.
 */
class ICT_Admin_AI {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_ict_test_openai_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ict_ai_analyze_project', array( $this, 'ajax_analyze_project' ) );
		add_action( 'wp_ajax_ict_ai_analyze_quote', array( $this, 'ajax_analyze_quote' ) );
		add_action( 'wp_ajax_ict_ai_generate_description', array( $this, 'ajax_generate_description' ) );
		add_action( 'wp_ajax_ict_ai_suggest_time_entry', array( $this, 'ajax_suggest_time_entry' ) );
		add_action( 'wp_ajax_ict_ai_inventory_analysis', array( $this, 'ajax_inventory_analysis' ) );
	}

	/**
	 * Add AI menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'ict-platform',
			__( 'AI Assistant', 'ict-platform' ),
			__( 'AI Assistant', 'ict-platform' ),
			'manage_options',
			'ict-ai',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// API Settings
		register_setting( 'ict_ai_settings', 'ict_openai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_api_key' ),
			'default'           => '',
		) );

		register_setting( 'ict_ai_settings', 'ict_openai_organization_id', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'ict_ai_settings', 'ict_openai_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gpt-4o',
		) );

		register_setting( 'ict_ai_settings', 'ict_openai_max_tokens', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 2000,
		) );

		register_setting( 'ict_ai_settings', 'ict_openai_temperature', array(
			'type'              => 'number',
			'sanitize_callback' => array( $this, 'sanitize_temperature' ),
			'default'           => 0.7,
		) );

		// Feature Toggles
		register_setting( 'ict_ai_settings', 'ict_ai_project_analysis', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_ai_settings', 'ict_ai_quote_analysis', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_ai_settings', 'ict_ai_time_suggestions', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_ai_settings', 'ict_ai_inventory_analysis', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_ai_settings', 'ict_ai_report_summaries', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		// Settings sections
		add_settings_section(
			'ict_ai_api',
			__( 'OpenAI API Configuration', 'ict-platform' ),
			array( $this, 'render_api_section' ),
			'ict-ai'
		);

		add_settings_section(
			'ict_ai_features',
			__( 'AI Features', 'ict-platform' ),
			array( $this, 'render_features_section' ),
			'ict-ai'
		);
	}

	/**
	 * Sanitize API key.
	 *
	 * @since  1.0.0
	 * @param  string $value API key.
	 * @return string Encrypted API key.
	 */
	public function sanitize_api_key( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// If already encrypted, return as-is
		if ( strpos( $value, 'encrypted:' ) === 0 ) {
			return $value;
		}

		// Encrypt using same method as other credentials
		if ( class_exists( 'ICT_Admin_Settings' ) ) {
			$settings = new ICT_Admin_Settings();
			return $settings->encrypt( $value );
		}

		return $value;
	}

	/**
	 * Sanitize temperature.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Temperature value.
	 * @return float Sanitized temperature (0-2).
	 */
	public function sanitize_temperature( $value ) {
		$temp = (float) $value;
		return max( 0, min( 2, $temp ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Enqueue on AI settings page
		if ( 'ict-platform_page_ict-ai' === $hook ) {
			wp_enqueue_style(
				'ict-ai-admin',
				plugins_url( 'css/ai-admin.css', __FILE__ ),
				array(),
				ICT_PLATFORM_VERSION
			);

			wp_enqueue_script(
				'ict-ai-admin',
				plugins_url( 'js/ai-admin.js', __FILE__ ),
				array( 'jquery' ),
				ICT_PLATFORM_VERSION,
				true
			);

			wp_localize_script(
				'ict-ai-admin',
				'ictAI',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ict_ai_admin' ),
					'strings' => array(
						'testing'     => __( 'Testing connection...', 'ict-platform' ),
						'analyzing'   => __( 'Analyzing...', 'ict-platform' ),
						'generating'  => __( 'Generating...', 'ict-platform' ),
						'success'     => __( 'Success!', 'ict-platform' ),
						'error'       => __( 'An error occurred.', 'ict-platform' ),
					),
				)
			);
		}

		// Enqueue AI assistant on relevant pages
		if ( $this->is_ai_enabled_page( $hook ) ) {
			wp_enqueue_style(
				'ict-ai-assistant',
				plugins_url( 'css/ai-assistant.css', __FILE__ ),
				array(),
				ICT_PLATFORM_VERSION
			);

			wp_enqueue_script(
				'ict-ai-assistant',
				plugins_url( 'js/ai-assistant.js', __FILE__ ),
				array( 'jquery' ),
				ICT_PLATFORM_VERSION,
				true
			);

			wp_localize_script(
				'ict-ai-assistant',
				'ictAIAssistant',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ict_ai_admin' ),
					'features' => $this->get_enabled_features(),
				)
			);
		}
	}

	/**
	 * Check if AI should be enabled on this page.
	 *
	 * @since  1.0.0
	 * @param  string $hook Page hook.
	 * @return bool
	 */
	private function is_ai_enabled_page( $hook ) {
		$enabled_pages = array(
			'ict-platform_page_ict-projects',
			'ict-platform_page_ict-quotewerks',
			'ict-platform_page_ict-time-tracking',
			'ict-platform_page_ict-inventory',
			'ict-platform_page_ict-reports',
		);

		return in_array( $hook, $enabled_pages, true );
	}

	/**
	 * Get enabled AI features.
	 *
	 * @since  1.0.0
	 * @return array Enabled features.
	 */
	private function get_enabled_features() {
		return array(
			'project_analysis'   => get_option( 'ict_ai_project_analysis', true ),
			'quote_analysis'     => get_option( 'ict_ai_quote_analysis', true ),
			'time_suggestions'   => get_option( 'ict_ai_time_suggestions', true ),
			'inventory_analysis' => get_option( 'ict_ai_inventory_analysis', true ),
			'report_summaries'   => get_option( 'ict_ai_report_summaries', true ),
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connection_status = $this->get_connection_status();
		$usage_stats = $this->get_usage_stats();

		?>
		<div class="wrap ict-ai-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'ict_ai_messages' ); ?>

			<div class="ict-ai-header">
				<div class="connection-status <?php echo esc_attr( $connection_status['class'] ); ?>">
					<span class="status-indicator"></span>
					<strong><?php esc_html_e( 'OpenAI Status:', 'ict-platform' ); ?></strong>
					<span class="status-text"><?php echo esc_html( $connection_status['message'] ); ?></span>
					<button type="button" class="button button-secondary" id="test-ai-connection">
						<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
					</button>
				</div>

				<div class="ai-actions">
					<a href="#usage-stats" class="button button-secondary">
						<span class="dashicons dashicons-chart-bar"></span>
						<?php esc_html_e( 'View Usage Stats', 'ict-platform' );?>
					</a>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'ict_ai_settings' );
				do_settings_sections( 'ict-ai' );
				submit_button();
				?>
			</form>

			<div class="ict-ai-usage" id="usage-stats">
				<h2><?php esc_html_e( 'API Usage Statistics (Last 30 Days)', 'ict-platform' ); ?></h2>
				<?php $this->render_usage_stats( $usage_stats ); ?>
			</div>

			<div class="ict-ai-demo">
				<h2><?php esc_html_e( 'AI Feature Demos', 'ict-platform' ); ?></h2>
				<?php $this->render_demo_section(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get connection status.
	 *
	 * @since  1.0.0
	 * @return array Status info.
	 */
	private function get_connection_status() {
		$api_key = get_option( 'ict_openai_api_key' );

		if ( empty( $api_key ) ) {
			return array(
				'class'   => 'not-configured',
				'message' => __( 'Not Configured', 'ict-platform' ),
			);
		}

		$last_test = get_transient( 'ict_openai_connection_test' );

		if ( false === $last_test ) {
			return array(
				'class'   => 'unknown',
				'message' => __( 'Unknown - Click Test Connection', 'ict-platform' ),
			);
		}

		if ( $last_test['success'] ) {
			return array(
				'class'   => 'connected',
				'message' => __( 'Connected', 'ict-platform' ),
			);
		}

		return array(
			'class'   => 'error',
			'message' => __( 'Connection Failed', 'ict-platform' ),
		);
	}

	/**
	 * Get usage statistics.
	 *
	 * @since  1.0.0
	 * @return array Usage stats.
	 */
	private function get_usage_stats() {
		if ( ! class_exists( 'ICT_OpenAI_Adapter' ) ) {
			return array();
		}

		$adapter = new ICT_OpenAI_Adapter();
		return $adapter->get_usage_stats( 30 );
	}

	/**
	 * Render API section.
	 *
	 * @since 1.0.0
	 */
	public function render_api_section() {
		?>
		<p><?php esc_html_e( 'Configure your OpenAI API credentials to enable AI-powered features.', 'ict-platform' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="ict_openai_api_key"><?php esc_html_e( 'API Key', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="password" id="ict_openai_api_key" name="ict_openai_api_key"
						   value="<?php echo esc_attr( get_option( 'ict_openai_api_key' ) ? '••••••••••••••••••••••••••••' : '' ); ?>"
						   class="large-text" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: OpenAI API keys URL */
							esc_html__( 'Your OpenAI API key. Get one at %s', 'ict-platform' ),
							'<a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_openai_organization_id"><?php esc_html_e( 'Organization ID (Optional)', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="text" id="ict_openai_organization_id" name="ict_openai_organization_id"
						   value="<?php echo esc_attr( get_option( 'ict_openai_organization_id' ) ); ?>"
						   class="regular-text" />
					<p class="description">
						<?php esc_html_e( 'Your OpenAI organization ID (if applicable).', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_openai_model"><?php esc_html_e( 'Model', 'ict-platform' ); ?></label>
				</th>
				<td>
					<select id="ict_openai_model" name="ict_openai_model">
						<?php
						$models = array(
							'gpt-4o'         => 'GPT-4o (Recommended)',
							'gpt-4o-mini'    => 'GPT-4o Mini (Faster, cheaper)',
							'gpt-4-turbo'    => 'GPT-4 Turbo',
							'gpt-3.5-turbo'  => 'GPT-3.5 Turbo (Most economical)',
						);
						$current = get_option( 'ict_openai_model', 'gpt-4o' );
						foreach ( $models as $value => $label ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $value ),
								selected( $current, $value, false ),
								esc_html( $label )
							);
						}
						?>
					</select>
					<p class="description">
						<?php esc_html_e( 'The OpenAI model to use for AI features.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_openai_max_tokens"><?php esc_html_e( 'Max Tokens', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="number" id="ict_openai_max_tokens" name="ict_openai_max_tokens"
						   value="<?php echo esc_attr( get_option( 'ict_openai_max_tokens', 2000 ) ); ?>"
						   min="100" max="4000" step="100" class="small-text" />
					<p class="description">
						<?php esc_html_e( 'Maximum tokens for AI responses (affects cost and response length).', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_openai_temperature"><?php esc_html_e( 'Temperature', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="number" id="ict_openai_temperature" name="ict_openai_temperature"
						   value="<?php echo esc_attr( get_option( 'ict_openai_temperature', 0.7 ) ); ?>"
						   min="0" max="2" step="0.1" class="small-text" />
					<p class="description">
						<?php esc_html_e( 'Controls randomness (0 = focused, 2 = creative). Recommended: 0.7', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render features section.
	 *
	 * @since 1.0.0
	 */
	public function render_features_section() {
		?>
		<p><?php esc_html_e( 'Enable or disable specific AI-powered features.', 'ict-platform' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Project Analysis', 'ict-platform' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ict_ai_project_analysis" value="1"
							   <?php checked( get_option( 'ict_ai_project_analysis', true ), true ); ?> />
						<?php esc_html_e( 'Enable AI-powered project analysis and recommendations', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Quote Analysis', 'ict-platform' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ict_ai_quote_analysis" value="1"
							   <?php checked( get_option( 'ict_ai_quote_analysis', true ), true ); ?> />
						<?php esc_html_e( 'Enable AI analysis of quotes for pricing and optimization', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Time Entry Suggestions', 'ict-platform' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ict_ai_time_suggestions" value="1"
							   <?php checked( get_option( 'ict_ai_time_suggestions', true ), true ); ?> />
						<?php esc_html_e( 'Enable AI-powered time entry description suggestions', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Inventory Analysis', 'ict-platform' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ict_ai_inventory_analysis" value="1"
							   <?php checked( get_option( 'ict_ai_inventory_analysis', true ), true ); ?> />
						<?php esc_html_e( 'Enable AI analysis of inventory needs based on projects', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Report Summaries', 'ict-platform' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ict_ai_report_summaries" value="1"
							   <?php checked( get_option( 'ict_ai_report_summaries', true ), true ); ?> />
						<?php esc_html_e( 'Enable AI-generated executive summaries for reports', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render usage stats.
	 *
	 * @since  1.0.0
	 * @param  array $stats Usage statistics.
	 */
	private function render_usage_stats( $stats ) {
		if ( empty( $stats ) ) {
			echo '<p>' . esc_html__( 'No usage data yet.', 'ict-platform' ) . '</p>';
			return;
		}

		?>
		<div class="usage-stats-grid">
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( number_format( $stats['total_requests'] ?? 0 ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total Requests', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( number_format( $stats['total_tokens'] ?? 0 ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total Tokens', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( number_format( $stats['total_prompt_tokens'] ?? 0 ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Prompt Tokens', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( number_format( $stats['total_completion_tokens'] ?? 0 ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Completion Tokens', 'ict-platform' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render demo section.
	 *
	 * @since 1.0.0
	 */
	private function render_demo_section() {
		?>
		<div class="ai-demo-container">
			<div class="demo-item">
				<h3><?php esc_html_e( 'Generate Project Description', 'ict-platform' ); ?></h3>
				<p><?php esc_html_e( 'Test AI-powered project description generation.', 'ict-platform' ); ?></p>
				<textarea id="demo-project-input" rows="3" placeholder="<?php esc_attr_e( 'Enter basic project details (name, type, location)...', 'ict-platform' ); ?>"></textarea>
				<button type="button" class="button button-primary" id="demo-generate-description">
					<?php esc_html_e( 'Generate Description', 'ict-platform' ); ?>
				</button>
				<div id="demo-description-result" class="demo-result"></div>
			</div>

			<div class="demo-item">
				<h3><?php esc_html_e( 'Time Entry Suggestions', 'ict-platform' ); ?></h3>
				<p><?php esc_html_e( 'Get AI suggestions for time entry descriptions.', 'ict-platform' ); ?></p>
				<select id="demo-project-select">
					<option value=""><?php esc_html_e( 'Select a project...', 'ict-platform' ); ?></option>
					<?php
					global $wpdb;
					$projects = $wpdb->get_results(
						"SELECT id, project_name FROM " . ICT_PROJECTS_TABLE . " ORDER BY created_at DESC LIMIT 20",
						ARRAY_A
					);
					foreach ( $projects as $project ) {
						printf(
							'<option value="%d">%s</option>',
							esc_attr( $project['id'] ),
							esc_html( $project['project_name'] )
						);
					}
					?>
				</select>
				<input type="text" id="demo-task-type" placeholder="<?php esc_attr_e( 'Task type (e.g., installation, maintenance)', 'ict-platform' ); ?>" />
				<button type="button" class="button button-primary" id="demo-suggest-time">
					<?php esc_html_e( 'Get Suggestions', 'ict-platform' ); ?>
				</button>
				<div id="demo-time-suggestions-result" class="demo-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Test connection.
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ict_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		if ( ! class_exists( 'ICT_OpenAI_Adapter' ) ) {
			wp_send_json_error( array( 'message' => __( 'OpenAI adapter not loaded.', 'ict-platform' ) ) );
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->test_connection();

		if ( is_wp_error( $result ) ) {
			set_transient( 'ict_openai_connection_test', array( 'success' => false ), HOUR_IN_SECONDS );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		set_transient( 'ict_openai_connection_test', array( 'success' => true ), HOUR_IN_SECONDS );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Analyze project.
	 *
	 * @since 1.0.0
	 */
	public function ajax_analyze_project() {
		check_ajax_referer( 'ict_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_ict_projects' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;

		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Project ID required.', 'ict-platform' ) ) );
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->analyze_project( $project_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Analyze quote.
	 *
	 * @since 1.0.0
	 */
	public function ajax_analyze_quote() {
		check_ajax_referer( 'ict_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$quote_data = isset( $_POST['quote_data'] ) ? $_POST['quote_data'] : array();

		if ( empty( $quote_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Quote data required.', 'ict-platform' ) ) );
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->analyze_quote( $quote_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Generate description.
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_description() {
		check_ajax_referer( 'ict_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_ict_projects' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( $_POST['details'] ) : '';

		if ( empty( $details ) ) {
			wp_send_json_error( array( 'message' => __( 'Project details required.', 'ict-platform' ) ) );
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->generate_project_description( array( 'details' => $details ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'description' => $result ) );
	}

	/**
	 * AJAX: Suggest time entry.
	 *
	 * @since 1.0.0
	 */
	public function ajax_suggest_time_entry() {
		check_ajax_referer( 'ict_ai_admin', 'nonce' );

		if ( ! current_user_can( 'edit_ict_time_entries' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$task_type = isset( $_POST['task_type'] ) ? sanitize_text_field( $_POST['task_type'] ) : '';

		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Project ID required.', 'ict-platform' ) ) );
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->suggest_time_entry_description( $project_id, $task_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'suggestions' => $result ) );
	}

	/**
	 * AJAX: Inventory analysis.
	 *
	 * @since 1.0.0
	 */
	public function ajax_inventory_analysis() {
		check_ajax_referer( 'ict_ai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_ict_inventory' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$project_ids = isset( $_POST['project_ids'] ) ? array_map( 'absint', $_POST['project_ids'] ) : array();

		if ( empty( $project_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Project IDs required.', 'ict-platform' ) ) );
		}

		$adapter = new ICT_OpenAI_Adapter();
		$result = $adapter->analyze_inventory_needs( $project_ids );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) ) ;
		}

		wp_send_json_success( $result );
	}
}
