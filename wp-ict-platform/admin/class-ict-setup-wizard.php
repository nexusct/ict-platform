<?php
/**
 * Setup Wizard for ICT Platform
 *
 * Provides a guided setup experience for initially configuring the plugin
 * with step-by-step instructions and AI-powered assistance.
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Setup_Wizard
 *
 * Handles the multi-step setup wizard for initial plugin configuration.
 */
class ICT_Setup_Wizard {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Setup_Wizard
	 */
	private static $instance = null;

	/**
	 * Current step in the wizard.
	 *
	 * @var string
	 */
	private $step = '';

	/**
	 * All wizard steps.
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * AI Assistant instance.
	 *
	 * @var ICT_AI_Setup_Assistant
	 */
	private $ai_assistant = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Setup_Wizard
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
		$this->define_steps();
	}

	/**
	 * Initialize the wizard.
	 *
	 * @return void
	 */
	public function init() {
		// Check if wizard should be shown
		if ( $this->should_show_wizard() ) {
			add_action( 'admin_notices', array( $this, 'wizard_notice' ) );
		}

		// Register AJAX handlers
		add_action( 'wp_ajax_ict_wizard_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_ict_wizard_skip_step', array( $this, 'ajax_skip_step' ) );
		add_action( 'wp_ajax_ict_wizard_ai_assist', array( $this, 'ajax_ai_assist' ) );
		add_action( 'wp_ajax_ict_wizard_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ict_wizard_complete', array( $this, 'ajax_complete_wizard' ) );
		add_action( 'wp_ajax_ict_wizard_get_ai_recommendation', array( $this, 'ajax_get_ai_recommendation' ) );
	}

	/**
	 * Define wizard steps.
	 *
	 * @return void
	 */
	private function define_steps() {
		$this->steps = array(
			'welcome'       => array(
				'name'        => __( 'Welcome', 'ict-platform' ),
				'description' => __( 'Introduction to the ICT Platform setup wizard', 'ict-platform' ),
				'icon'        => 'dashicons-admin-home',
				'handler'     => 'render_welcome_step',
			),
			'company'       => array(
				'name'        => __( 'Company Info', 'ict-platform' ),
				'description' => __( 'Basic company and business settings', 'ict-platform' ),
				'icon'        => 'dashicons-building',
				'handler'     => 'render_company_step',
			),
			'zoho'          => array(
				'name'        => __( 'Zoho Integration', 'ict-platform' ),
				'description' => __( 'Connect to Zoho CRM, FSM, Books, People, and Desk', 'ict-platform' ),
				'icon'        => 'dashicons-cloud',
				'handler'     => 'render_zoho_step',
			),
			'teams'         => array(
				'name'        => __( 'Microsoft Teams', 'ict-platform' ),
				'description' => __( 'Set up Microsoft Teams integration for notifications', 'ict-platform' ),
				'icon'        => 'dashicons-groups',
				'handler'     => 'render_teams_step',
			),
			'notifications' => array(
				'name'        => __( 'Notifications', 'ict-platform' ),
				'description' => __( 'Configure email, SMS, and push notifications', 'ict-platform' ),
				'icon'        => 'dashicons-bell',
				'handler'     => 'render_notifications_step',
			),
			'security'      => array(
				'name'        => __( 'Security', 'ict-platform' ),
				'description' => __( 'Set up biometric authentication and role management', 'ict-platform' ),
				'icon'        => 'dashicons-shield',
				'handler'     => 'render_security_step',
			),
			'features'      => array(
				'name'        => __( 'Features', 'ict-platform' ),
				'description' => __( 'Enable offline mode, reporting, and custom fields', 'ict-platform' ),
				'icon'        => 'dashicons-admin-generic',
				'handler'     => 'render_features_step',
			),
			'complete'      => array(
				'name'        => __( 'Complete', 'ict-platform' ),
				'description' => __( 'Review and finish setup', 'ict-platform' ),
				'icon'        => 'dashicons-yes-alt',
				'handler'     => 'render_complete_step',
			),
		);
	}

	/**
	 * Check if wizard should be displayed.
	 *
	 * @return bool
	 */
	public function should_show_wizard() {
		// Don't show if wizard was completed or dismissed
		if ( get_option( 'ict_wizard_completed', false ) ) {
			return false;
		}

		// Don't show if wizard was dismissed
		if ( get_option( 'ict_wizard_dismissed', false ) ) {
			return false;
		}

		// Only show to administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice to start wizard.
	 *
	 * @return void
	 */
	public function wizard_notice() {
		$wizard_url  = admin_url( 'admin.php?page=ict-setup-wizard' );
		$dismiss_url = wp_nonce_url( admin_url( 'admin.php?page=ict-platform&dismiss-wizard=1' ), 'ict_dismiss_wizard' );
		?>
		<div class="notice notice-info is-dismissible ict-wizard-notice">
			<p>
				<strong><?php esc_html_e( 'Welcome to ICT Platform!', 'ict-platform' ); ?></strong>
				<?php esc_html_e( 'Get started quickly with our guided setup wizard featuring AI-powered assistance.', 'ict-platform' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $wizard_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Start Setup Wizard', 'ict-platform' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Skip Setup', 'ict-platform' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Register wizard admin page.
	 *
	 * @return void
	 */
	public function register_wizard_page() {
		add_submenu_page(
			null, // Hidden from menu
			__( 'ICT Platform Setup', 'ict-platform' ),
			__( 'Setup Wizard', 'ict-platform' ),
			'manage_options',
			'ict-setup-wizard',
			array( $this, 'render_wizard_page' )
		);
	}

	/**
	 * Enqueue wizard assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_wizard_assets( $hook ) {
		if ( 'admin_page_ict-setup-wizard' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'ict-wizard-styles',
			ICT_PLATFORM_PLUGIN_URL . 'admin/css/wizard.css',
			array(),
			ICT_PLATFORM_VERSION
		);

		wp_enqueue_script(
			'ict-wizard-script',
			ICT_PLATFORM_PLUGIN_URL . 'admin/js/wizard.js',
			array( 'jquery', 'wp-util' ),
			ICT_PLATFORM_VERSION,
			true
		);

		wp_localize_script(
			'ict-wizard-script',
			'ictWizard',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ict_wizard_nonce' ),
				'steps'       => array_keys( $this->steps ),
				'currentStep' => $this->get_current_step(),
				'strings'     => array(
					'saving'           => __( 'Saving...', 'ict-platform' ),
					'saved'            => __( 'Saved!', 'ict-platform' ),
					'error'            => __( 'An error occurred. Please try again.', 'ict-platform' ),
					'testing'          => __( 'Testing connection...', 'ict-platform' ),
					'testSuccess'      => __( 'Connection successful!', 'ict-platform' ),
					'testFailed'       => __( 'Connection failed. Please check your credentials.', 'ict-platform' ),
					'aiThinking'       => __( 'AI is analyzing your setup...', 'ict-platform' ),
					'aiRecommendation' => __( 'AI Recommendation:', 'ict-platform' ),
					'confirmSkip'      => __( 'Are you sure you want to skip this step? You can configure these settings later.', 'ict-platform' ),
				),
			)
		);
	}

	/**
	 * Get current wizard step.
	 *
	 * @return string
	 */
	private function get_current_step() {
		$saved_step = get_option( 'ict_wizard_current_step', 'welcome' );
		$step       = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : $saved_step;

		if ( ! isset( $this->steps[ $step ] ) ) {
			$step = 'welcome';
		}

		return $step;
	}

	/**
	 * Render the wizard page.
	 *
	 * @return void
	 */
	public function render_wizard_page() {
		$this->step    = $this->get_current_step();
		$step_keys     = array_keys( $this->steps );
		$current_index = array_search( $this->step, $step_keys, true );
		$progress      = ( $current_index / ( count( $step_keys ) - 1 ) ) * 100;
		?>
		<div class="ict-wizard-wrap">
			<div class="ict-wizard-header">
				<div class="ict-wizard-logo">
					<span class="dashicons dashicons-building"></span>
					<h1><?php esc_html_e( 'ICT Platform Setup', 'ict-platform' ); ?></h1>
				</div>
				<div class="ict-wizard-ai-badge">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'AI-Powered Setup', 'ict-platform' ); ?>
				</div>
			</div>

			<div class="ict-wizard-progress">
				<div class="ict-wizard-progress-bar" style="width: <?php echo esc_attr( $progress ); ?>%"></div>
			</div>

			<div class="ict-wizard-steps">
				<?php foreach ( $this->steps as $key => $step ) : ?>
					<?php
					$step_index  = array_search( $key, $step_keys, true );
					$is_complete = $step_index < $current_index;
					$is_current  = $key === $this->step;
					$classes     = array( 'ict-wizard-step-indicator' );
					if ( $is_complete ) {
						$classes[] = 'complete';
					}
					if ( $is_current ) {
						$classes[] = 'current';
					}
					?>
					<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
						<span class="dashicons <?php echo esc_attr( $step['icon'] ); ?>"></span>
						<span class="step-name"><?php echo esc_html( $step['name'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="ict-wizard-content">
				<div class="ict-wizard-main">
					<?php $this->render_step_content(); ?>
				</div>

				<div class="ict-wizard-sidebar">
					<?php $this->render_ai_assistant_panel(); ?>
					<?php $this->render_help_panel(); ?>
				</div>
			</div>

			<div class="ict-wizard-footer">
				<?php $this->render_navigation(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the current step content.
	 *
	 * @return void
	 */
	private function render_step_content() {
		$handler = $this->steps[ $this->step ]['handler'];
		if ( method_exists( $this, $handler ) ) {
			$this->$handler();
		}
	}

	/**
	 * Render Welcome step.
	 *
	 * @return void
	 */
	private function render_welcome_step() {
		?>
		<div class="ict-wizard-step-content welcome-step">
			<h2><?php esc_html_e( 'Welcome to ICT Platform!', 'ict-platform' ); ?></h2>
			<p class="lead">
				<?php esc_html_e( 'This wizard will guide you through setting up your ICT/Electrical Contracting operations management system. Our AI assistant will help you configure everything optimally for your business.', 'ict-platform' ); ?>
			</p>

			<div class="ict-wizard-features">
				<div class="feature-card">
					<span class="dashicons dashicons-cloud"></span>
					<h3><?php esc_html_e( 'Zoho Integration', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Connect with Zoho CRM, FSM, Books, People, and Desk for complete business management.', 'ict-platform' ); ?></p>
				</div>

				<div class="feature-card">
					<span class="dashicons dashicons-groups"></span>
					<h3><?php esc_html_e( 'Microsoft Teams', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Get real-time notifications and updates directly in your Teams channels.', 'ict-platform' ); ?></p>
				</div>

				<div class="feature-card">
					<span class="dashicons dashicons-bell"></span>
					<h3><?php esc_html_e( 'Multi-Channel Notifications', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Email, SMS via Twilio, and push notifications to keep your team informed.', 'ict-platform' ); ?></p>
				</div>

				<div class="feature-card">
					<span class="dashicons dashicons-shield"></span>
					<h3><?php esc_html_e( 'Advanced Security', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Biometric authentication and granular role-based access control.', 'ict-platform' ); ?></p>
				</div>

				<div class="feature-card">
					<span class="dashicons dashicons-download"></span>
					<h3><?php esc_html_e( 'Offline Mode', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Work offline and sync automatically when connection is restored.', 'ict-platform' ); ?></p>
				</div>

				<div class="feature-card">
					<span class="dashicons dashicons-chart-area"></span>
					<h3><?php esc_html_e( 'Advanced Reporting', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Generate comprehensive reports in PDF, Excel, CSV, and JSON formats.', 'ict-platform' ); ?></p>
				</div>
			</div>

			<div class="ict-wizard-ai-intro">
				<div class="ai-intro-icon">
					<span class="dashicons dashicons-superhero"></span>
				</div>
				<div class="ai-intro-content">
					<h3><?php esc_html_e( 'AI-Powered Setup Assistant', 'ict-platform' ); ?></h3>
					<p><?php esc_html_e( 'Throughout this wizard, our AI assistant will:', 'ict-platform' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Analyze your business needs and recommend optimal configurations', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Provide step-by-step instructions for each integration', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Help troubleshoot connection issues', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Suggest best practices for your industry', 'ict-platform' ); ?></li>
					</ul>
				</div>
			</div>

			<div class="ict-wizard-time-estimate">
				<span class="dashicons dashicons-clock"></span>
				<span><?php esc_html_e( 'Estimated setup time: 10-15 minutes', 'ict-platform' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Company Info step.
	 *
	 * @return void
	 */
	private function render_company_step() {
		$company_name = get_option( 'ict_company_name', get_bloginfo( 'name' ) );
		$industry     = get_option( 'ict_industry', 'electrical' );
		$company_size = get_option( 'ict_company_size', 'small' );
		$currency     = get_option( 'ict_currency', 'USD' );
		$timezone     = get_option( 'timezone_string', 'UTC' );
		?>
		<div class="ict-wizard-step-content company-step">
			<h2><?php esc_html_e( 'Company Information', 'ict-platform' ); ?></h2>
			<p><?php esc_html_e( 'Tell us about your business so we can configure the platform optimally.', 'ict-platform' ); ?></p>

			<form id="ict-wizard-company-form" class="ict-wizard-form">
				<div class="form-row">
					<label for="company_name"><?php esc_html_e( 'Company Name', 'ict-platform' ); ?> <span class="required">*</span></label>
					<input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $company_name ); ?>" required>
					<p class="description"><?php esc_html_e( 'This will appear on reports and notifications.', 'ict-platform' ); ?></p>
				</div>

				<div class="form-row">
					<label for="industry"><?php esc_html_e( 'Industry', 'ict-platform' ); ?> <span class="required">*</span></label>
					<select id="industry" name="industry" required>
						<option value="electrical" <?php selected( $industry, 'electrical' ); ?>><?php esc_html_e( 'Electrical Contracting', 'ict-platform' ); ?></option>
						<option value="ict" <?php selected( $industry, 'ict' ); ?>><?php esc_html_e( 'ICT / Telecommunications', 'ict-platform' ); ?></option>
						<option value="hvac" <?php selected( $industry, 'hvac' ); ?>><?php esc_html_e( 'HVAC', 'ict-platform' ); ?></option>
						<option value="plumbing" <?php selected( $industry, 'plumbing' ); ?>><?php esc_html_e( 'Plumbing', 'ict-platform' ); ?></option>
						<option value="general" <?php selected( $industry, 'general' ); ?>><?php esc_html_e( 'General Contracting', 'ict-platform' ); ?></option>
						<option value="other" <?php selected( $industry, 'other' ); ?>><?php esc_html_e( 'Other Field Service', 'ict-platform' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Helps us customize terminology and workflows.', 'ict-platform' ); ?></p>
				</div>

				<div class="form-row">
					<label for="company_size"><?php esc_html_e( 'Company Size', 'ict-platform' ); ?></label>
					<select id="company_size" name="company_size">
						<option value="solo" <?php selected( $company_size, 'solo' ); ?>><?php esc_html_e( 'Solo / 1 person', 'ict-platform' ); ?></option>
						<option value="small" <?php selected( $company_size, 'small' ); ?>><?php esc_html_e( 'Small (2-10 employees)', 'ict-platform' ); ?></option>
						<option value="medium" <?php selected( $company_size, 'medium' ); ?>><?php esc_html_e( 'Medium (11-50 employees)', 'ict-platform' ); ?></option>
						<option value="large" <?php selected( $company_size, 'large' ); ?>><?php esc_html_e( 'Large (51-200 employees)', 'ict-platform' ); ?></option>
						<option value="enterprise" <?php selected( $company_size, 'enterprise' ); ?>><?php esc_html_e( 'Enterprise (200+ employees)', 'ict-platform' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Helps us recommend appropriate features and limits.', 'ict-platform' ); ?></p>
				</div>

				<div class="form-row">
					<label for="currency"><?php esc_html_e( 'Currency', 'ict-platform' ); ?></label>
					<select id="currency" name="currency">
						<option value="USD" <?php selected( $currency, 'USD' ); ?>>USD - US Dollar</option>
						<option value="EUR" <?php selected( $currency, 'EUR' ); ?>>EUR - Euro</option>
						<option value="GBP" <?php selected( $currency, 'GBP' ); ?>>GBP - British Pound</option>
						<option value="CAD" <?php selected( $currency, 'CAD' ); ?>>CAD - Canadian Dollar</option>
						<option value="AUD" <?php selected( $currency, 'AUD' ); ?>>AUD - Australian Dollar</option>
						<option value="NZD" <?php selected( $currency, 'NZD' ); ?>>NZD - New Zealand Dollar</option>
					</select>
				</div>

				<div class="form-row">
					<label for="timezone"><?php esc_html_e( 'Timezone', 'ict-platform' ); ?></label>
					<select id="timezone" name="timezone">
						<?php echo wp_timezone_choice( $timezone ); ?>
					</select>
				</div>

				<div class="form-row">
					<label for="primary_use"><?php esc_html_e( 'Primary Use Case', 'ict-platform' ); ?></label>
					<div class="checkbox-group">
						<label><input type="checkbox" name="use_cases[]" value="project_management" checked> <?php esc_html_e( 'Project Management', 'ict-platform' ); ?></label>
						<label><input type="checkbox" name="use_cases[]" value="time_tracking" checked> <?php esc_html_e( 'Time Tracking', 'ict-platform' ); ?></label>
						<label><input type="checkbox" name="use_cases[]" value="inventory"> <?php esc_html_e( 'Inventory Management', 'ict-platform' ); ?></label>
						<label><input type="checkbox" name="use_cases[]" value="field_service"> <?php esc_html_e( 'Field Service Dispatch', 'ict-platform' ); ?></label>
						<label><input type="checkbox" name="use_cases[]" value="invoicing"> <?php esc_html_e( 'Invoicing & Billing', 'ict-platform' ); ?></label>
					</div>
				</div>
			</form>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="company">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'Get AI Recommendations', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Zoho Integration step.
	 *
	 * @return void
	 */
	private function render_zoho_step() {
		$crm_enabled    = get_option( 'ict_zoho_crm_enabled', false );
		$fsm_enabled    = get_option( 'ict_zoho_fsm_enabled', false );
		$books_enabled  = get_option( 'ict_zoho_books_enabled', false );
		$people_enabled = get_option( 'ict_zoho_people_enabled', false );
		$desk_enabled   = get_option( 'ict_zoho_desk_enabled', false );
		?>
		<div class="ict-wizard-step-content zoho-step">
			<h2><?php esc_html_e( 'Zoho Integration', 'ict-platform' ); ?></h2>
			<p><?php esc_html_e( 'Connect your Zoho services to sync projects, customers, time entries, and more.', 'ict-platform' ); ?></p>

			<div class="ict-wizard-instructions">
				<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Before You Begin', 'ict-platform' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Log in to your Zoho Developer Console at', 'ict-platform' ); ?> <a href="https://api-console.zoho.com/" target="_blank">api-console.zoho.com</a></li>
					<li><?php esc_html_e( 'Create a new "Server-based Application"', 'ict-platform' ); ?></li>
					<li><?php printf( esc_html__( 'Add this URL as an Authorized Redirect URI: %s', 'ict-platform' ), '<code>' . esc_html( admin_url( 'admin.php?page=ict-platform&zoho-callback=1' ) ) . '</code>' ); ?></li>
					<li><?php esc_html_e( 'Copy the Client ID and Client Secret below', 'ict-platform' ); ?></li>
				</ol>
			</div>

			<form id="ict-wizard-zoho-form" class="ict-wizard-form">
				<div class="ict-wizard-integration-cards">
					<!-- Zoho CRM -->
					<div class="integration-card <?php echo $crm_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<img src="<?php echo esc_url( ICT_PLATFORM_PLUGIN_URL . 'admin/images/zoho-crm.svg' ); ?>" alt="Zoho CRM" class="integration-logo">
							<div class="integration-info">
								<h4><?php esc_html_e( 'Zoho CRM', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Customer and deal management', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="zoho_crm_enabled" value="1" <?php checked( $crm_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $crm_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'Client ID', 'ict-platform' ); ?></label>
								<input type="text" name="zoho_crm_client_id" value="<?php echo esc_attr( get_option( 'ict_zoho_crm_client_id' ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Client Secret', 'ict-platform' ); ?></label>
								<input type="password" name="zoho_crm_client_secret" value="">
								<p class="description"><?php esc_html_e( 'Leave blank to keep existing secret', 'ict-platform' ); ?></p>
							</div>
							<button type="button" class="button test-connection-btn" data-service="zoho_crm">
								<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>

					<!-- Zoho FSM -->
					<div class="integration-card <?php echo $fsm_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<img src="<?php echo esc_url( ICT_PLATFORM_PLUGIN_URL . 'admin/images/zoho-fsm.svg' ); ?>" alt="Zoho FSM" class="integration-logo">
							<div class="integration-info">
								<h4><?php esc_html_e( 'Zoho FSM', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Field service management', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="zoho_fsm_enabled" value="1" <?php checked( $fsm_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $fsm_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'Client ID', 'ict-platform' ); ?></label>
								<input type="text" name="zoho_fsm_client_id" value="<?php echo esc_attr( get_option( 'ict_zoho_fsm_client_id' ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Client Secret', 'ict-platform' ); ?></label>
								<input type="password" name="zoho_fsm_client_secret" value="">
							</div>
							<button type="button" class="button test-connection-btn" data-service="zoho_fsm">
								<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>

					<!-- Zoho Books -->
					<div class="integration-card <?php echo $books_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<img src="<?php echo esc_url( ICT_PLATFORM_PLUGIN_URL . 'admin/images/zoho-books.svg' ); ?>" alt="Zoho Books" class="integration-logo">
							<div class="integration-info">
								<h4><?php esc_html_e( 'Zoho Books', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Invoicing and accounting', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="zoho_books_enabled" value="1" <?php checked( $books_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $books_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'Client ID', 'ict-platform' ); ?></label>
								<input type="text" name="zoho_books_client_id" value="<?php echo esc_attr( get_option( 'ict_zoho_books_client_id' ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Client Secret', 'ict-platform' ); ?></label>
								<input type="password" name="zoho_books_client_secret" value="">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Organization ID', 'ict-platform' ); ?></label>
								<input type="text" name="zoho_books_org_id" value="<?php echo esc_attr( get_option( 'ict_zoho_books_org_id' ) ); ?>">
							</div>
							<button type="button" class="button test-connection-btn" data-service="zoho_books">
								<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>

					<!-- Zoho People -->
					<div class="integration-card <?php echo $people_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<img src="<?php echo esc_url( ICT_PLATFORM_PLUGIN_URL . 'admin/images/zoho-people.svg' ); ?>" alt="Zoho People" class="integration-logo">
							<div class="integration-info">
								<h4><?php esc_html_e( 'Zoho People', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'HR and time tracking', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="zoho_people_enabled" value="1" <?php checked( $people_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $people_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'Client ID', 'ict-platform' ); ?></label>
								<input type="text" name="zoho_people_client_id" value="<?php echo esc_attr( get_option( 'ict_zoho_people_client_id' ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Client Secret', 'ict-platform' ); ?></label>
								<input type="password" name="zoho_people_client_secret" value="">
							</div>
							<button type="button" class="button test-connection-btn" data-service="zoho_people">
								<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>

					<!-- Zoho Desk -->
					<div class="integration-card <?php echo $desk_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<img src="<?php echo esc_url( ICT_PLATFORM_PLUGIN_URL . 'admin/images/zoho-desk.svg' ); ?>" alt="Zoho Desk" class="integration-logo">
							<div class="integration-info">
								<h4><?php esc_html_e( 'Zoho Desk', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Support ticket management', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="zoho_desk_enabled" value="1" <?php checked( $desk_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $desk_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'Client ID', 'ict-platform' ); ?></label>
								<input type="text" name="zoho_desk_client_id" value="<?php echo esc_attr( get_option( 'ict_zoho_desk_client_id' ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Client Secret', 'ict-platform' ); ?></label>
								<input type="password" name="zoho_desk_client_secret" value="">
							</div>
							<button type="button" class="button test-connection-btn" data-service="zoho_desk">
								<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>
				</div>
			</form>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="zoho">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'AI: Which Zoho services do I need?', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Microsoft Teams step.
	 *
	 * @return void
	 */
	private function render_teams_step() {
		$teams_enabled = get_option( 'ict_teams_enabled', false );
		?>
		<div class="ict-wizard-step-content teams-step">
			<h2><?php esc_html_e( 'Microsoft Teams Integration', 'ict-platform' ); ?></h2>
			<p><?php esc_html_e( 'Connect Microsoft Teams to receive project updates, alerts, and notifications directly in your channels.', 'ict-platform' ); ?></p>

			<div class="ict-wizard-instructions">
				<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Setup Instructions', 'ict-platform' ); ?></h3>

				<div class="instruction-tabs">
					<button class="tab-btn active" data-tab="webhook"><?php esc_html_e( 'Simple (Webhook)', 'ict-platform' ); ?></button>
					<button class="tab-btn" data-tab="oauth"><?php esc_html_e( 'Advanced (OAuth)', 'ict-platform' ); ?></button>
				</div>

				<div class="tab-content active" id="tab-webhook">
					<h4><?php esc_html_e( 'Option 1: Incoming Webhook (Recommended for Quick Setup)', 'ict-platform' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Open Microsoft Teams and go to the channel where you want notifications', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Click the "..." menu next to the channel name and select "Connectors"', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Search for "Incoming Webhook" and click "Configure"', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Give it a name (e.g., "ICT Platform") and optionally upload an icon', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Click "Create" and copy the webhook URL', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Paste the URL in the field below', 'ict-platform' ); ?></li>
					</ol>
				</div>

				<div class="tab-content" id="tab-oauth">
					<h4><?php esc_html_e( 'Option 2: Full OAuth Integration (For Advanced Features)', 'ict-platform' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Go to Azure Portal:', 'ict-platform' ); ?> <a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Azure App Registrations</a></li>
						<li><?php esc_html_e( 'Click "New registration"', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Enter a name and select "Accounts in this organizational directory only"', 'ict-platform' ); ?></li>
						<li><?php printf( esc_html__( 'Add Redirect URI: %s', 'ict-platform' ), '<code>' . esc_html( admin_url( 'admin.php?page=ict-platform&teams-callback=1' ) ) . '</code>' ); ?></li>
						<li><?php esc_html_e( 'Under "API Permissions", add: ChannelMessage.Send, Team.ReadBasic.All', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Under "Certificates & secrets", create a new client secret', 'ict-platform' ); ?></li>
						<li><?php esc_html_e( 'Copy the Application (client) ID, Directory (tenant) ID, and secret value', 'ict-platform' ); ?></li>
					</ol>
				</div>
			</div>

			<form id="ict-wizard-teams-form" class="ict-wizard-form">
				<div class="form-row">
					<label class="toggle-label">
						<input type="checkbox" name="teams_enabled" value="1" <?php checked( $teams_enabled ); ?>>
						<?php esc_html_e( 'Enable Microsoft Teams Integration', 'ict-platform' ); ?>
					</label>
				</div>

				<div class="teams-fields" <?php echo ! $teams_enabled ? 'style="display:none;"' : ''; ?>>
					<div class="form-section webhook-section">
						<h4><?php esc_html_e( 'Webhook Configuration', 'ict-platform' ); ?></h4>
						<div class="form-row">
							<label for="teams_webhook_url"><?php esc_html_e( 'Webhook URL', 'ict-platform' ); ?></label>
							<input type="url" id="teams_webhook_url" name="teams_webhook_url" value="<?php echo esc_attr( get_option( 'ict_teams_webhook_url' ) ); ?>" placeholder="https://outlook.office.com/webhook/...">
						</div>
					</div>

					<div class="form-section oauth-section">
						<h4><?php esc_html_e( 'OAuth Configuration (Optional)', 'ict-platform' ); ?></h4>
						<div class="form-row">
							<label for="teams_tenant_id"><?php esc_html_e( 'Tenant ID', 'ict-platform' ); ?></label>
							<input type="text" id="teams_tenant_id" name="teams_tenant_id" value="<?php echo esc_attr( get_option( 'ict_teams_tenant_id' ) ); ?>">
						</div>
						<div class="form-row">
							<label for="teams_client_id"><?php esc_html_e( 'Client ID', 'ict-platform' ); ?></label>
							<input type="text" id="teams_client_id" name="teams_client_id" value="<?php echo esc_attr( get_option( 'ict_teams_client_id' ) ); ?>">
						</div>
						<div class="form-row">
							<label for="teams_client_secret"><?php esc_html_e( 'Client Secret', 'ict-platform' ); ?></label>
							<input type="password" id="teams_client_secret" name="teams_client_secret" value="">
							<p class="description"><?php esc_html_e( 'Leave blank to keep existing secret', 'ict-platform' ); ?></p>
						</div>
					</div>

					<div class="form-section">
						<h4><?php esc_html_e( 'Notification Settings', 'ict-platform' ); ?></h4>
						<div class="checkbox-group">
							<label>
								<input type="checkbox" name="teams_notify_project_updates" value="1" <?php checked( get_option( 'ict_teams_notify_project_updates', true ) ); ?>>
								<?php esc_html_e( 'Project status updates', 'ict-platform' ); ?>
							</label>
							<label>
								<input type="checkbox" name="teams_notify_time_entries" value="1" <?php checked( get_option( 'ict_teams_notify_time_entries', false ) ); ?>>
								<?php esc_html_e( 'Time entry submissions', 'ict-platform' ); ?>
							</label>
							<label>
								<input type="checkbox" name="teams_notify_low_stock" value="1" <?php checked( get_option( 'ict_teams_notify_low_stock', true ) ); ?>>
								<?php esc_html_e( 'Low stock alerts', 'ict-platform' ); ?>
							</label>
							<label>
								<input type="checkbox" name="teams_notify_po_approvals" value="1" <?php checked( get_option( 'ict_teams_notify_po_approvals', true ) ); ?>>
								<?php esc_html_e( 'Purchase order approvals', 'ict-platform' ); ?>
							</label>
						</div>
					</div>

					<button type="button" class="button test-connection-btn" data-service="teams">
						<?php esc_html_e( 'Send Test Message', 'ict-platform' ); ?>
					</button>
					<span class="connection-status"></span>
				</div>
			</form>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="teams">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'AI: Help me set up Teams', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Notifications step.
	 *
	 * @return void
	 */
	private function render_notifications_step() {
		$email_enabled  = get_option( 'ict_email_enabled', true );
		$twilio_enabled = get_option( 'ict_twilio_enabled', false );
		$push_enabled   = get_option( 'ict_push_enabled', false );
		?>
		<div class="ict-wizard-step-content notifications-step">
			<h2><?php esc_html_e( 'Notification Channels', 'ict-platform' ); ?></h2>
			<p><?php esc_html_e( 'Configure how your team receives notifications about projects, time entries, and inventory.', 'ict-platform' ); ?></p>

			<form id="ict-wizard-notifications-form" class="ict-wizard-form">
				<!-- Email Notifications -->
				<div class="ict-wizard-integration-cards">
					<div class="integration-card <?php echo $email_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-email-alt integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Email Notifications', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Send notifications via WordPress email', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="email_enabled" value="1" <?php checked( $email_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $email_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'From Name', 'ict-platform' ); ?></label>
								<input type="text" name="email_from_name" value="<?php echo esc_attr( get_option( 'ict_email_from_name', get_bloginfo( 'name' ) ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'From Address', 'ict-platform' ); ?></label>
								<input type="email" name="email_from_address" value="<?php echo esc_attr( get_option( 'ict_email_from_address', get_option( 'admin_email' ) ) ); ?>">
							</div>
							<div class="form-row">
								<label class="toggle-label">
									<input type="checkbox" name="email_digest_enabled" value="1" <?php checked( get_option( 'ict_email_digest_enabled', false ) ); ?>>
									<?php esc_html_e( 'Enable daily digest (combine notifications)', 'ict-platform' ); ?>
								</label>
							</div>
							<button type="button" class="button test-connection-btn" data-service="email">
								<?php esc_html_e( 'Send Test Email', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>

					<!-- Twilio SMS -->
					<div class="integration-card <?php echo $twilio_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-smartphone integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'SMS via Twilio', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Send text message notifications', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="twilio_enabled" value="1" <?php checked( $twilio_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $twilio_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="ict-wizard-instructions compact">
								<h5><?php esc_html_e( 'Get Twilio Credentials:', 'ict-platform' ); ?></h5>
								<ol>
									<li><?php esc_html_e( 'Sign up at', 'ict-platform' ); ?> <a href="https://www.twilio.com/try-twilio" target="_blank">twilio.com</a></li>
									<li><?php esc_html_e( 'Go to Console Dashboard to find Account SID and Auth Token', 'ict-platform' ); ?></li>
									<li><?php esc_html_e( 'Purchase a phone number or use the trial number', 'ict-platform' ); ?></li>
								</ol>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Account SID', 'ict-platform' ); ?></label>
								<input type="text" name="twilio_account_sid" value="<?php echo esc_attr( get_option( 'ict_twilio_account_sid' ) ); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Auth Token', 'ict-platform' ); ?></label>
								<input type="password" name="twilio_auth_token" value="">
								<p class="description"><?php esc_html_e( 'Leave blank to keep existing token', 'ict-platform' ); ?></p>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'From Phone Number', 'ict-platform' ); ?></label>
								<input type="tel" name="twilio_from_number" value="<?php echo esc_attr( get_option( 'ict_twilio_from_number' ) ); ?>" placeholder="+15551234567">
							</div>
							<button type="button" class="button test-connection-btn" data-service="twilio">
								<?php esc_html_e( 'Send Test SMS', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>

					<!-- Push Notifications -->
					<div class="integration-card <?php echo $push_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-bell integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Push Notifications', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Browser and mobile push notifications', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="push_enabled" value="1" <?php checked( $push_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $push_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="ict-wizard-instructions compact">
								<p><?php esc_html_e( 'Push notifications use VAPID (Voluntary Application Server Identification) keys. You can generate new keys or enter existing ones.', 'ict-platform' ); ?></p>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'VAPID Public Key', 'ict-platform' ); ?></label>
								<input type="text" name="push_vapid_public_key" value="<?php echo esc_attr( get_option( 'ict_push_vapid_public_key' ) ); ?>">
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'VAPID Private Key', 'ict-platform' ); ?></label>
								<input type="password" name="push_vapid_private_key" value="">
							</div>
							<button type="button" class="button" id="generate-vapid-keys">
								<?php esc_html_e( 'Generate New Keys', 'ict-platform' ); ?>
							</button>
							<button type="button" class="button test-connection-btn" data-service="push">
								<?php esc_html_e( 'Test Push', 'ict-platform' ); ?>
							</button>
							<span class="connection-status"></span>
						</div>
					</div>
				</div>
			</form>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="notifications">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'AI: Recommend notification strategy', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Security step.
	 *
	 * @return void
	 */
	private function render_security_step() {
		$biometric_enabled       = get_option( 'ict_biometric_enabled', false );
		$role_management_enabled = get_option( 'ict_role_management_enabled', true );
		?>
		<div class="ict-wizard-step-content security-step">
			<h2><?php esc_html_e( 'Security Settings', 'ict-platform' ); ?></h2>
			<p><?php esc_html_e( 'Configure authentication and access control for your team.', 'ict-platform' ); ?></p>

			<form id="ict-wizard-security-form" class="ict-wizard-form">
				<!-- Biometric Authentication -->
				<div class="ict-wizard-integration-cards">
					<div class="integration-card <?php echo $biometric_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-id integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Biometric Authentication', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Fingerprint, Face ID, Windows Hello', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="biometric_enabled" value="1" <?php checked( $biometric_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $biometric_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="ict-wizard-instructions compact">
								<p><?php esc_html_e( 'Biometric authentication uses WebAuthn/FIDO2 standards. Users can register their devices for passwordless login.', 'ict-platform' ); ?></p>
								<p><strong><?php esc_html_e( 'Requirements:', 'ict-platform' ); ?></strong></p>
								<ul>
									<li><?php esc_html_e( 'HTTPS connection (required for WebAuthn)', 'ict-platform' ); ?></li>
									<li><?php esc_html_e( 'Compatible browser (Chrome, Firefox, Safari, Edge)', 'ict-platform' ); ?></li>
									<li><?php esc_html_e( 'Device with biometric sensor or security key', 'ict-platform' ); ?></li>
								</ul>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Relying Party Name', 'ict-platform' ); ?></label>
								<input type="text" name="biometric_rp_name" value="<?php echo esc_attr( get_option( 'ict_biometric_relying_party_name', get_bloginfo( 'name' ) ) ); ?>">
								<p class="description"><?php esc_html_e( 'Displayed to users during authentication', 'ict-platform' ); ?></p>
							</div>
							<div class="form-row">
								<label class="toggle-label">
									<input type="checkbox" name="biometric_require_for_mobile" value="1" <?php checked( get_option( 'ict_biometric_require_for_mobile', false ) ); ?>>
									<?php esc_html_e( 'Require biometric auth for mobile app', 'ict-platform' ); ?>
								</label>
							</div>
						</div>
					</div>

					<!-- Role Management -->
					<div class="integration-card <?php echo $role_management_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-admin-users integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Advanced Role Management', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Custom roles and permissions', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="role_management_enabled" value="1" <?php checked( $role_management_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $role_management_enabled ? 'style="display:none;"' : ''; ?>>
							<p><?php esc_html_e( 'The following default roles will be created:', 'ict-platform' ); ?></p>
							<table class="roles-preview">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Role', 'ict-platform' ); ?></th>
										<th><?php esc_html_e( 'Description', 'ict-platform' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><strong><?php esc_html_e( 'ICT Administrator', 'ict-platform' ); ?></strong></td>
										<td><?php esc_html_e( 'Full access to all features and settings', 'ict-platform' ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Project Manager', 'ict-platform' ); ?></strong></td>
										<td><?php esc_html_e( 'Manage projects, assign resources, view reports', 'ict-platform' ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Technician', 'ict-platform' ); ?></strong></td>
										<td><?php esc_html_e( 'View assigned projects, log time, update status', 'ict-platform' ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Inventory Manager', 'ict-platform' ); ?></strong></td>
										<td><?php esc_html_e( 'Manage inventory, create purchase orders', 'ict-platform' ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Accountant', 'ict-platform' ); ?></strong></td>
										<td><?php esc_html_e( 'View financial reports, manage invoicing', 'ict-platform' ); ?></td>
									</tr>
									<tr>
										<td><strong><?php esc_html_e( 'Viewer', 'ict-platform' ); ?></strong></td>
										<td><?php esc_html_e( 'Read-only access to projects and reports', 'ict-platform' ); ?></td>
									</tr>
								</tbody>
							</table>
							<div class="form-row">
								<label class="toggle-label">
									<input type="checkbox" name="role_dynamic_permissions" value="1" <?php checked( get_option( 'ict_role_dynamic_permissions', false ) ); ?>>
									<?php esc_html_e( 'Enable dynamic permissions (project-level access)', 'ict-platform' ); ?>
								</label>
							</div>
						</div>
					</div>
				</div>
			</form>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="security">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'AI: Security best practices', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Features step.
	 *
	 * @return void
	 */
	private function render_features_step() {
		$offline_enabled       = get_option( 'ict_offline_enabled', true );
		$reporting_enabled     = get_option( 'ict_reporting_auto_schedule_enabled', false );
		$custom_fields_enabled = get_option( 'ict_custom_fields_enabled', true );
		?>
		<div class="ict-wizard-step-content features-step">
			<h2><?php esc_html_e( 'Additional Features', 'ict-platform' ); ?></h2>
			<p><?php esc_html_e( 'Configure offline mode, reporting, and custom fields to match your workflow.', 'ict-platform' ); ?></p>

			<form id="ict-wizard-features-form" class="ict-wizard-form">
				<div class="ict-wizard-integration-cards">
					<!-- Offline Mode -->
					<div class="integration-card <?php echo $offline_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-cloud-saved integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Offline Mode', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Work without internet, sync when connected', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="offline_enabled" value="1" <?php checked( $offline_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $offline_enabled ? 'style="display:none;"' : ''; ?>>
							<div class="form-row">
								<label><?php esc_html_e( 'Max Offline Queue Size', 'ict-platform' ); ?></label>
								<input type="number" name="offline_max_queue_size" value="<?php echo esc_attr( get_option( 'ict_offline_max_queue_size', 100 ) ); ?>" min="10" max="1000">
								<p class="description"><?php esc_html_e( 'Maximum number of offline changes to store', 'ict-platform' ); ?></p>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Conflict Resolution', 'ict-platform' ); ?></label>
								<select name="offline_conflict_resolution">
									<option value="server_wins" <?php selected( get_option( 'ict_offline_conflict_resolution', 'server_wins' ), 'server_wins' ); ?>><?php esc_html_e( 'Server wins (safest)', 'ict-platform' ); ?></option>
									<option value="client_wins" <?php selected( get_option( 'ict_offline_conflict_resolution' ), 'client_wins' ); ?>><?php esc_html_e( 'Client wins', 'ict-platform' ); ?></option>
									<option value="manual" <?php selected( get_option( 'ict_offline_conflict_resolution' ), 'manual' ); ?>><?php esc_html_e( 'Manual resolution', 'ict-platform' ); ?></option>
								</select>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Sync Interval (seconds)', 'ict-platform' ); ?></label>
								<input type="number" name="offline_sync_interval" value="<?php echo esc_attr( get_option( 'ict_offline_sync_interval', 30 ) ); ?>" min="10" max="300">
							</div>
						</div>
					</div>

					<!-- Advanced Reporting -->
					<div class="integration-card">
						<div class="integration-header">
							<span class="dashicons dashicons-chart-bar integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Advanced Reporting', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Generate and schedule reports', 'ict-platform' ); ?></p>
							</div>
							<span class="feature-badge"><?php esc_html_e( 'Always On', 'ict-platform' ); ?></span>
						</div>
						<div class="integration-fields">
							<p><?php esc_html_e( 'Available report types:', 'ict-platform' ); ?></p>
							<ul class="feature-list">
								<li><?php esc_html_e( 'Project Summary & Status', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Time Entry Analysis', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Resource Utilization', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Inventory Status', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Financial Summary', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Technician Performance', 'ict-platform' ); ?></li>
							</ul>
							<div class="form-row">
								<label><?php esc_html_e( 'Default Export Format', 'ict-platform' ); ?></label>
								<select name="reporting_default_format">
									<option value="pdf" <?php selected( get_option( 'ict_reporting_default_format', 'pdf' ), 'pdf' ); ?>>PDF</option>
									<option value="xlsx" <?php selected( get_option( 'ict_reporting_default_format' ), 'xlsx' ); ?>>Excel (XLSX)</option>
									<option value="csv" <?php selected( get_option( 'ict_reporting_default_format' ), 'csv' ); ?>>CSV</option>
									<option value="json" <?php selected( get_option( 'ict_reporting_default_format' ), 'json' ); ?>>JSON</option>
								</select>
							</div>
							<div class="form-row">
								<label class="toggle-label">
									<input type="checkbox" name="reporting_auto_schedule_enabled" value="1" <?php checked( $reporting_enabled ); ?>>
									<?php esc_html_e( 'Enable scheduled reports', 'ict-platform' ); ?>
								</label>
							</div>
						</div>
					</div>

					<!-- Custom Fields -->
					<div class="integration-card <?php echo $custom_fields_enabled ? 'connected' : ''; ?>">
						<div class="integration-header">
							<span class="dashicons dashicons-forms integration-icon"></span>
							<div class="integration-info">
								<h4><?php esc_html_e( 'Custom Field Builder', 'ict-platform' ); ?></h4>
								<p><?php esc_html_e( 'Add custom data fields to entities', 'ict-platform' ); ?></p>
							</div>
							<label class="toggle-switch">
								<input type="checkbox" name="custom_fields_enabled" value="1" <?php checked( $custom_fields_enabled ); ?>>
								<span class="toggle-slider"></span>
							</label>
						</div>
						<div class="integration-fields" <?php echo ! $custom_fields_enabled ? 'style="display:none;"' : ''; ?>>
							<p><?php esc_html_e( 'Add custom fields to:', 'ict-platform' ); ?></p>
							<ul class="feature-list">
								<li><?php esc_html_e( 'Projects', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Time Entries', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Inventory Items', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Purchase Orders', 'ict-platform' ); ?></li>
								<li><?php esc_html_e( 'Users', 'ict-platform' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'Supported field types: Text, Number, Date, Select, Checkbox, File Upload, GPS, Formula, and more.', 'ict-platform' ); ?></p>
							<div class="form-row">
								<label><?php esc_html_e( 'Max Fields Per Entity', 'ict-platform' ); ?></label>
								<input type="number" name="custom_fields_max_per_entity" value="<?php echo esc_attr( get_option( 'ict_custom_fields_max_per_entity', 50 ) ); ?>" min="10" max="200">
							</div>
						</div>
					</div>
				</div>
			</form>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="features">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'AI: Recommend features for my business', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Complete step.
	 *
	 * @return void
	 */
	private function render_complete_step() {
		?>
		<div class="ict-wizard-step-content complete-step">
			<div class="completion-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<h2><?php esc_html_e( 'Setup Complete!', 'ict-platform' ); ?></h2>
				<p><?php esc_html_e( 'Your ICT Platform is configured and ready to use.', 'ict-platform' ); ?></p>
			</div>

			<div class="setup-summary">
				<h3><?php esc_html_e( 'Configuration Summary', 'ict-platform' ); ?></h3>
				<div class="summary-grid" id="wizard-summary">
					<?php $this->render_setup_summary(); ?>
				</div>
			</div>

			<div class="next-steps">
				<h3><?php esc_html_e( 'Recommended Next Steps', 'ict-platform' ); ?></h3>
				<div class="next-steps-grid">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ict-projects&action=new' ) ); ?>" class="next-step-card">
						<span class="dashicons dashicons-portfolio"></span>
						<h4><?php esc_html_e( 'Create Your First Project', 'ict-platform' ); ?></h4>
						<p><?php esc_html_e( 'Start tracking a project with time entries and resources', 'ict-platform' ); ?></p>
					</a>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ict-users' ) ); ?>" class="next-step-card">
						<span class="dashicons dashicons-admin-users"></span>
						<h4><?php esc_html_e( 'Invite Team Members', 'ict-platform' ); ?></h4>
						<p><?php esc_html_e( 'Add technicians, project managers, and other staff', 'ict-platform' ); ?></p>
					</a>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ict-inventory' ) ); ?>" class="next-step-card">
						<span class="dashicons dashicons-archive"></span>
						<h4><?php esc_html_e( 'Set Up Inventory', 'ict-platform' ); ?></h4>
						<p><?php esc_html_e( 'Import or add your equipment and materials', 'ict-platform' ); ?></p>
					</a>

					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ict-settings' ) ); ?>" class="next-step-card">
						<span class="dashicons dashicons-admin-settings"></span>
						<h4><?php esc_html_e( 'Review All Settings', 'ict-platform' ); ?></h4>
						<p><?php esc_html_e( 'Fine-tune your configuration in the settings panel', 'ict-platform' ); ?></p>
					</a>
				</div>
			</div>

			<div class="ict-wizard-ai-suggest">
				<button type="button" class="button ai-suggest-btn" data-context="getting_started">
					<span class="dashicons dashicons-superhero"></span>
					<?php esc_html_e( 'AI: What should I do first?', 'ict-platform' ); ?>
				</button>
				<div class="ai-suggestion-result"></div>
			</div>

			<div class="completion-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ict-platform' ) ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Go to Dashboard', 'ict-platform' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render setup summary.
	 *
	 * @return void
	 */
	private function render_setup_summary() {
		$summary_items = array(
			'company'       => array(
				'label' => __( 'Company', 'ict-platform' ),
				'value' => get_option( 'ict_company_name', get_bloginfo( 'name' ) ),
				'icon'  => 'dashicons-building',
			),
			'zoho'          => array(
				'label' => __( 'Zoho Services', 'ict-platform' ),
				'value' => $this->get_enabled_zoho_services(),
				'icon'  => 'dashicons-cloud',
			),
			'teams'         => array(
				'label' => __( 'Microsoft Teams', 'ict-platform' ),
				'value' => get_option( 'ict_teams_enabled' ) ? __( 'Connected', 'ict-platform' ) : __( 'Not configured', 'ict-platform' ),
				'icon'  => 'dashicons-groups',
			),
			'notifications' => array(
				'label' => __( 'Notifications', 'ict-platform' ),
				'value' => $this->get_enabled_notification_channels(),
				'icon'  => 'dashicons-bell',
			),
			'security'      => array(
				'label' => __( 'Security', 'ict-platform' ),
				'value' => $this->get_security_features(),
				'icon'  => 'dashicons-shield',
			),
			'features'      => array(
				'label' => __( 'Features', 'ict-platform' ),
				'value' => $this->get_enabled_features(),
				'icon'  => 'dashicons-admin-generic',
			),
		);

		foreach ( $summary_items as $key => $item ) {
			?>
			<div class="summary-item">
				<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
				<div class="summary-content">
					<strong><?php echo esc_html( $item['label'] ); ?></strong>
					<span><?php echo esc_html( $item['value'] ); ?></span>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Get enabled Zoho services.
	 *
	 * @return string
	 */
	private function get_enabled_zoho_services() {
		$services = array();
		if ( get_option( 'ict_zoho_crm_enabled' ) ) {
			$services[] = 'CRM';
		}
		if ( get_option( 'ict_zoho_fsm_enabled' ) ) {
			$services[] = 'FSM';
		}
		if ( get_option( 'ict_zoho_books_enabled' ) ) {
			$services[] = 'Books';
		}
		if ( get_option( 'ict_zoho_people_enabled' ) ) {
			$services[] = 'People';
		}
		if ( get_option( 'ict_zoho_desk_enabled' ) ) {
			$services[] = 'Desk';
		}

		return empty( $services ) ? __( 'None configured', 'ict-platform' ) : implode( ', ', $services );
	}

	/**
	 * Get enabled notification channels.
	 *
	 * @return string
	 */
	private function get_enabled_notification_channels() {
		$channels = array();
		if ( get_option( 'ict_email_enabled', true ) ) {
			$channels[] = __( 'Email', 'ict-platform' );
		}
		if ( get_option( 'ict_twilio_enabled' ) ) {
			$channels[] = __( 'SMS', 'ict-platform' );
		}
		if ( get_option( 'ict_push_enabled' ) ) {
			$channels[] = __( 'Push', 'ict-platform' );
		}

		return empty( $channels ) ? __( 'None', 'ict-platform' ) : implode( ', ', $channels );
	}

	/**
	 * Get security features status.
	 *
	 * @return string
	 */
	private function get_security_features() {
		$features = array();
		if ( get_option( 'ict_biometric_enabled' ) ) {
			$features[] = __( 'Biometric', 'ict-platform' );
		}
		if ( get_option( 'ict_role_management_enabled', true ) ) {
			$features[] = __( 'Role Management', 'ict-platform' );
		}

		return empty( $features ) ? __( 'Standard', 'ict-platform' ) : implode( ', ', $features );
	}

	/**
	 * Get enabled features.
	 *
	 * @return string
	 */
	private function get_enabled_features() {
		$features = array();
		if ( get_option( 'ict_offline_enabled', true ) ) {
			$features[] = __( 'Offline', 'ict-platform' );
		}
		if ( get_option( 'ict_custom_fields_enabled', true ) ) {
			$features[] = __( 'Custom Fields', 'ict-platform' );
		}
		$features[] = __( 'Reporting', 'ict-platform' ); // Always enabled

		return implode( ', ', $features );
	}

	/**
	 * Render AI assistant panel.
	 *
	 * @return void
	 */
	private function render_ai_assistant_panel() {
		?>
		<div class="ict-wizard-ai-panel">
			<div class="ai-panel-header">
				<span class="dashicons dashicons-superhero"></span>
				<h3><?php esc_html_e( 'AI Assistant', 'ict-platform' ); ?></h3>
			</div>
			<div class="ai-panel-content">
				<p><?php esc_html_e( 'I can help you with:', 'ict-platform' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Explaining each configuration option', 'ict-platform' ); ?></li>
					<li><?php esc_html_e( 'Recommending settings for your business', 'ict-platform' ); ?></li>
					<li><?php esc_html_e( 'Troubleshooting connection issues', 'ict-platform' ); ?></li>
					<li><?php esc_html_e( 'Best practices for your industry', 'ict-platform' ); ?></li>
				</ul>
				<div class="ai-chat-input">
					<input type="text" id="ai-question-input" placeholder="<?php esc_attr_e( 'Ask me anything...', 'ict-platform' ); ?>">
					<button type="button" id="ai-ask-btn" class="button">
						<span class="dashicons dashicons-arrow-right-alt"></span>
					</button>
				</div>
				<div class="ai-response" id="ai-response"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render help panel.
	 *
	 * @return void
	 */
	private function render_help_panel() {
		?>
		<div class="ict-wizard-help-panel">
			<div class="help-panel-header">
				<span class="dashicons dashicons-editor-help"></span>
				<h3><?php esc_html_e( 'Need Help?', 'ict-platform' ); ?></h3>
			</div>
			<div class="help-panel-content">
				<a href="https://docs.ictplatform.com" target="_blank" class="help-link">
					<span class="dashicons dashicons-book"></span>
					<?php esc_html_e( 'Documentation', 'ict-platform' ); ?>
				</a>
				<a href="https://ictplatform.com/support" target="_blank" class="help-link">
					<span class="dashicons dashicons-sos"></span>
					<?php esc_html_e( 'Support Center', 'ict-platform' ); ?>
				</a>
				<a href="https://www.youtube.com/ictplatform" target="_blank" class="help-link">
					<span class="dashicons dashicons-video-alt3"></span>
					<?php esc_html_e( 'Video Tutorials', 'ict-platform' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render navigation buttons.
	 *
	 * @return void
	 */
	private function render_navigation() {
		$step_keys     = array_keys( $this->steps );
		$current_index = array_search( $this->step, $step_keys, true );
		$prev_step     = $current_index > 0 ? $step_keys[ $current_index - 1 ] : null;
		$next_step     = $current_index < count( $step_keys ) - 1 ? $step_keys[ $current_index + 1 ] : null;
		$is_last_step  = $this->step === 'complete';
		?>
		<div class="wizard-nav-buttons">
			<?php if ( $prev_step && $this->step !== 'welcome' ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'step', $prev_step ) ); ?>" class="button wizard-prev">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<?php esc_html_e( 'Previous', 'ict-platform' ); ?>
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>

			<div class="wizard-nav-right">
				<?php if ( ! $is_last_step && $this->step !== 'welcome' ) : ?>
					<button type="button" class="button wizard-skip" data-next-step="<?php echo esc_attr( $next_step ); ?>">
						<?php esc_html_e( 'Skip this step', 'ict-platform' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( $this->step === 'welcome' ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'step', $next_step ) ); ?>" class="button button-primary wizard-next">
						<?php esc_html_e( 'Get Started', 'ict-platform' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
				<?php elseif ( ! $is_last_step ) : ?>
					<button type="button" class="button button-primary wizard-save-next" data-next-step="<?php echo esc_attr( $next_step ); ?>">
						<?php esc_html_e( 'Save & Continue', 'ict-platform' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Save step data.
	 *
	 * @return void
	 */
	public function ajax_save_step() {
		check_ajax_referer( 'ict_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ict-platform' ) ) );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		$data = isset( $_POST['data'] ) ? $_POST['data'] : array();

		if ( empty( $step ) || ! isset( $this->steps[ $step ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid step', 'ict-platform' ) ) );
		}

		// Process step data based on step type
		$result = $this->process_step_data( $step, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Update current step
		$step_keys     = array_keys( $this->steps );
		$current_index = array_search( $step, $step_keys, true );
		$next_step     = isset( $step_keys[ $current_index + 1 ] ) ? $step_keys[ $current_index + 1 ] : 'complete';
		update_option( 'ict_wizard_current_step', $next_step );

		wp_send_json_success(
			array(
				'message'   => __( 'Settings saved successfully', 'ict-platform' ),
				'next_step' => $next_step,
			)
		);
	}

	/**
	 * Process step data.
	 *
	 * @param string $step Step key.
	 * @param array  $data Form data.
	 * @return bool|WP_Error
	 */
	private function process_step_data( $step, $data ) {
		switch ( $step ) {
			case 'company':
				return $this->save_company_settings( $data );
			case 'zoho':
				return $this->save_zoho_settings( $data );
			case 'teams':
				return $this->save_teams_settings( $data );
			case 'notifications':
				return $this->save_notifications_settings( $data );
			case 'security':
				return $this->save_security_settings( $data );
			case 'features':
				return $this->save_features_settings( $data );
			default:
				return true;
		}
	}

	/**
	 * Save company settings.
	 *
	 * @param array $data Form data.
	 * @return bool
	 */
	private function save_company_settings( $data ) {
		if ( isset( $data['company_name'] ) ) {
			update_option( 'ict_company_name', sanitize_text_field( $data['company_name'] ) );
		}
		if ( isset( $data['industry'] ) ) {
			update_option( 'ict_industry', sanitize_key( $data['industry'] ) );
		}
		if ( isset( $data['company_size'] ) ) {
			update_option( 'ict_company_size', sanitize_key( $data['company_size'] ) );
		}
		if ( isset( $data['currency'] ) ) {
			update_option( 'ict_currency', sanitize_text_field( $data['currency'] ) );
		}
		if ( isset( $data['timezone'] ) ) {
			update_option( 'timezone_string', sanitize_text_field( $data['timezone'] ) );
		}
		if ( isset( $data['use_cases'] ) && is_array( $data['use_cases'] ) ) {
			update_option( 'ict_use_cases', array_map( 'sanitize_key', $data['use_cases'] ) );
		}

		return true;
	}

	/**
	 * Save Zoho settings.
	 *
	 * @param array $data Form data.
	 * @return bool
	 */
	private function save_zoho_settings( $data ) {
		$services = array( 'crm', 'fsm', 'books', 'people', 'desk' );
		$settings = new ICT_Admin_Settings();

		foreach ( $services as $service ) {
			$enabled_key       = "zoho_{$service}_enabled";
			$client_id_key     = "zoho_{$service}_client_id";
			$client_secret_key = "zoho_{$service}_client_secret";

			update_option( "ict_{$enabled_key}", ! empty( $data[ $enabled_key ] ) );

			if ( isset( $data[ $client_id_key ] ) ) {
				update_option( "ict_{$client_id_key}", sanitize_text_field( $data[ $client_id_key ] ) );
			}

			if ( ! empty( $data[ $client_secret_key ] ) ) {
				$encrypted = $settings->sanitize_encrypted( $data[ $client_secret_key ] );
				update_option( "ict_{$client_secret_key}", $encrypted );
			}
		}

		// Save Books organization ID
		if ( isset( $data['zoho_books_org_id'] ) ) {
			update_option( 'ict_zoho_books_org_id', sanitize_text_field( $data['zoho_books_org_id'] ) );
		}

		return true;
	}

	/**
	 * Save Teams settings.
	 *
	 * @param array $data Form data.
	 * @return bool
	 */
	private function save_teams_settings( $data ) {
		$settings = new ICT_Admin_Settings();

		update_option( 'ict_teams_enabled', ! empty( $data['teams_enabled'] ) );

		if ( isset( $data['teams_webhook_url'] ) ) {
			update_option( 'ict_teams_webhook_url', esc_url_raw( $data['teams_webhook_url'] ) );
		}
		if ( isset( $data['teams_tenant_id'] ) ) {
			update_option( 'ict_teams_tenant_id', sanitize_text_field( $data['teams_tenant_id'] ) );
		}
		if ( isset( $data['teams_client_id'] ) ) {
			update_option( 'ict_teams_client_id', sanitize_text_field( $data['teams_client_id'] ) );
		}
		if ( ! empty( $data['teams_client_secret'] ) ) {
			$encrypted = $settings->sanitize_encrypted( $data['teams_client_secret'] );
			update_option( 'ict_teams_client_secret', $encrypted );
		}

		// Notification settings
		update_option( 'ict_teams_notify_project_updates', ! empty( $data['teams_notify_project_updates'] ) );
		update_option( 'ict_teams_notify_time_entries', ! empty( $data['teams_notify_time_entries'] ) );
		update_option( 'ict_teams_notify_low_stock', ! empty( $data['teams_notify_low_stock'] ) );
		update_option( 'ict_teams_notify_po_approvals', ! empty( $data['teams_notify_po_approvals'] ) );

		return true;
	}

	/**
	 * Save notification settings.
	 *
	 * @param array $data Form data.
	 * @return bool
	 */
	private function save_notifications_settings( $data ) {
		$settings = new ICT_Admin_Settings();

		// Email
		update_option( 'ict_email_enabled', ! empty( $data['email_enabled'] ) );
		if ( isset( $data['email_from_name'] ) ) {
			update_option( 'ict_email_from_name', sanitize_text_field( $data['email_from_name'] ) );
		}
		if ( isset( $data['email_from_address'] ) ) {
			update_option( 'ict_email_from_address', sanitize_email( $data['email_from_address'] ) );
		}
		update_option( 'ict_email_digest_enabled', ! empty( $data['email_digest_enabled'] ) );

		// Twilio
		update_option( 'ict_twilio_enabled', ! empty( $data['twilio_enabled'] ) );
		if ( isset( $data['twilio_account_sid'] ) ) {
			update_option( 'ict_twilio_account_sid', sanitize_text_field( $data['twilio_account_sid'] ) );
		}
		if ( ! empty( $data['twilio_auth_token'] ) ) {
			$encrypted = $settings->sanitize_encrypted( $data['twilio_auth_token'] );
			update_option( 'ict_twilio_auth_token', $encrypted );
		}
		if ( isset( $data['twilio_from_number'] ) ) {
			update_option( 'ict_twilio_from_number', sanitize_text_field( $data['twilio_from_number'] ) );
		}

		// Push
		update_option( 'ict_push_enabled', ! empty( $data['push_enabled'] ) );
		if ( isset( $data['push_vapid_public_key'] ) ) {
			update_option( 'ict_push_vapid_public_key', sanitize_text_field( $data['push_vapid_public_key'] ) );
		}
		if ( ! empty( $data['push_vapid_private_key'] ) ) {
			$encrypted = $settings->sanitize_encrypted( $data['push_vapid_private_key'] );
			update_option( 'ict_push_vapid_private_key', $encrypted );
		}

		return true;
	}

	/**
	 * Save security settings.
	 *
	 * @param array $data Form data.
	 * @return bool
	 */
	private function save_security_settings( $data ) {
		update_option( 'ict_biometric_enabled', ! empty( $data['biometric_enabled'] ) );
		if ( isset( $data['biometric_rp_name'] ) ) {
			update_option( 'ict_biometric_relying_party_name', sanitize_text_field( $data['biometric_rp_name'] ) );
		}
		update_option( 'ict_biometric_require_for_mobile', ! empty( $data['biometric_require_for_mobile'] ) );

		update_option( 'ict_role_management_enabled', ! empty( $data['role_management_enabled'] ) );
		update_option( 'ict_role_dynamic_permissions', ! empty( $data['role_dynamic_permissions'] ) );

		// Initialize roles if role management is enabled
		if ( ! empty( $data['role_management_enabled'] ) && class_exists( 'ICT_Advanced_Role_Manager' ) ) {
			$role_manager = ICT_Advanced_Role_Manager::get_instance();
			$role_manager->create_default_roles();
		}

		return true;
	}

	/**
	 * Save features settings.
	 *
	 * @param array $data Form data.
	 * @return bool
	 */
	private function save_features_settings( $data ) {
		// Offline
		update_option( 'ict_offline_enabled', ! empty( $data['offline_enabled'] ) );
		if ( isset( $data['offline_max_queue_size'] ) ) {
			update_option( 'ict_offline_max_queue_size', absint( $data['offline_max_queue_size'] ) );
		}
		if ( isset( $data['offline_conflict_resolution'] ) ) {
			update_option( 'ict_offline_conflict_resolution', sanitize_key( $data['offline_conflict_resolution'] ) );
		}
		if ( isset( $data['offline_sync_interval'] ) ) {
			update_option( 'ict_offline_sync_interval', absint( $data['offline_sync_interval'] ) );
		}

		// Reporting
		if ( isset( $data['reporting_default_format'] ) ) {
			update_option( 'ict_reporting_default_format', sanitize_key( $data['reporting_default_format'] ) );
		}
		update_option( 'ict_reporting_auto_schedule_enabled', ! empty( $data['reporting_auto_schedule_enabled'] ) );

		// Custom Fields
		update_option( 'ict_custom_fields_enabled', ! empty( $data['custom_fields_enabled'] ) );
		if ( isset( $data['custom_fields_max_per_entity'] ) ) {
			update_option( 'ict_custom_fields_max_per_entity', absint( $data['custom_fields_max_per_entity'] ) );
		}

		return true;
	}

	/**
	 * AJAX: Skip step.
	 *
	 * @return void
	 */
	public function ajax_skip_step() {
		check_ajax_referer( 'ict_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ict-platform' ) ) );
		}

		$next_step = isset( $_POST['next_step'] ) ? sanitize_key( $_POST['next_step'] ) : '';

		if ( ! empty( $next_step ) && isset( $this->steps[ $next_step ] ) ) {
			update_option( 'ict_wizard_current_step', $next_step );
			wp_send_json_success( array( 'next_step' => $next_step ) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid step', 'ict-platform' ) ) );
	}

	/**
	 * AJAX: Complete wizard.
	 *
	 * @return void
	 */
	public function ajax_complete_wizard() {
		check_ajax_referer( 'ict_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ict-platform' ) ) );
		}

		update_option( 'ict_wizard_completed', true );
		update_option( 'ict_wizard_completed_at', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'message'      => __( 'Setup completed successfully!', 'ict-platform' ),
				'redirect_url' => admin_url( 'admin.php?page=ict-platform' ),
			)
		);
	}

	/**
	 * AJAX: Test connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ict_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ict-platform' ) ) );
		}

		$service = isset( $_POST['service'] ) ? sanitize_key( $_POST['service'] ) : '';

		switch ( $service ) {
			case 'zoho_crm':
			case 'zoho_fsm':
			case 'zoho_books':
			case 'zoho_people':
			case 'zoho_desk':
				$result = $this->test_zoho_connection( str_replace( 'zoho_', '', $service ) );
				break;
			case 'teams':
				$result = $this->test_teams_connection();
				break;
			case 'email':
				$result = $this->test_email_connection();
				break;
			case 'twilio':
				$result = $this->test_twilio_connection();
				break;
			case 'push':
				$result = $this->test_push_connection();
				break;
			default:
				$result = new WP_Error( 'invalid_service', __( 'Invalid service', 'ict-platform' ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Test Zoho connection.
	 *
	 * @param string $service Zoho service name.
	 * @return array|WP_Error
	 */
	private function test_zoho_connection( $service ) {
		// Check if integration manager exists
		if ( ! class_exists( 'ICT_Integration_Manager' ) ) {
			return new WP_Error( 'not_available', __( 'Integration manager not available', 'ict-platform' ) );
		}

		$client_id = get_option( "ict_zoho_{$service}_client_id" );
		if ( empty( $client_id ) ) {
			return new WP_Error( 'missing_credentials', __( 'Client ID is required', 'ict-platform' ) );
		}

		// In a real implementation, this would test the actual OAuth connection
		return array(
			'success' => true,
			'message' => __( 'Connection configured. Click "Authorize" to complete OAuth setup.', 'ict-platform' ),
		);
	}

	/**
	 * Test Teams connection.
	 *
	 * @return array|WP_Error
	 */
	private function test_teams_connection() {
		$webhook_url = get_option( 'ict_teams_webhook_url' );

		if ( empty( $webhook_url ) ) {
			return new WP_Error( 'missing_webhook', __( 'Webhook URL is required', 'ict-platform' ) );
		}

		// Send test message
		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'@type'      => 'MessageCard',
						'@context'   => 'http://schema.org/extensions',
						'themeColor' => '0076D7',
						'summary'    => 'ICT Platform Test',
						'sections'   => array(
							array(
								'activityTitle'    => __( 'ICT Platform Setup Wizard', 'ict-platform' ),
								'activitySubtitle' => __( 'Test message successful!', 'ict-platform' ),
								'facts'            => array(
									array(
										'name'  => __( 'Status', 'ict-platform' ),
										'value' => __( 'Connected', 'ict-platform' ),
									),
								),
								'markdown'         => true,
							),
						),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'teams_error', sprintf( __( 'Teams returned error code: %d', 'ict-platform' ), $code ) );
		}

		return array(
			'success' => true,
			'message' => __( 'Test message sent successfully! Check your Teams channel.', 'ict-platform' ),
		);
	}

	/**
	 * Test email connection.
	 *
	 * @return array|WP_Error
	 */
	private function test_email_connection() {
		$to      = get_option( 'admin_email' );
		$subject = __( 'ICT Platform - Test Email', 'ict-platform' );
		$message = __( 'This is a test email from the ICT Platform setup wizard. If you received this, email notifications are working correctly!', 'ict-platform' );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( ! $sent ) {
			return new WP_Error( 'email_failed', __( 'Failed to send test email. Please check your WordPress email configuration.', 'ict-platform' ) );
		}

		return array(
			'success' => true,
			'message' => sprintf( __( 'Test email sent to %s', 'ict-platform' ), $to ),
		);
	}

	/**
	 * Test Twilio connection.
	 *
	 * @return array|WP_Error
	 */
	private function test_twilio_connection() {
		$account_sid = get_option( 'ict_twilio_account_sid' );
		$auth_token  = ICT_Admin_Settings::decrypt( get_option( 'ict_twilio_auth_token' ) );

		if ( empty( $account_sid ) || empty( $auth_token ) ) {
			return new WP_Error( 'missing_credentials', __( 'Account SID and Auth Token are required', 'ict-platform' ) );
		}

		// Test API connectivity
		$response = wp_remote_get(
			"https://api.twilio.com/2010-04-01/Accounts/{$account_sid}.json",
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$account_sid}:{$auth_token}" ),
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'twilio_error', __( 'Invalid Twilio credentials', 'ict-platform' ) );
		}

		return array(
			'success' => true,
			'message' => __( 'Twilio credentials verified successfully!', 'ict-platform' ),
		);
	}

	/**
	 * Test push notification connection.
	 *
	 * @return array|WP_Error
	 */
	private function test_push_connection() {
		$public_key  = get_option( 'ict_push_vapid_public_key' );
		$private_key = get_option( 'ict_push_vapid_private_key' );

		if ( empty( $public_key ) || empty( $private_key ) ) {
			return new WP_Error( 'missing_keys', __( 'VAPID keys are required. Click "Generate New Keys" to create them.', 'ict-platform' ) );
		}

		return array(
			'success' => true,
			'message' => __( 'VAPID keys configured. Users can now subscribe to push notifications.', 'ict-platform' ),
		);
	}

	/**
	 * AJAX: Get AI recommendation.
	 *
	 * @return void
	 */
	public function ajax_get_ai_recommendation() {
		check_ajax_referer( 'ict_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ict-platform' ) ) );
		}

		$context  = isset( $_POST['context'] ) ? sanitize_key( $_POST['context'] ) : '';
		$question = isset( $_POST['question'] ) ? sanitize_text_field( $_POST['question'] ) : '';

		// Get AI assistant
		if ( ! $this->ai_assistant ) {
			require_once ICT_PLATFORM_PLUGIN_DIR . 'admin/class-ict-ai-setup-assistant.php';
			$this->ai_assistant = new ICT_AI_Setup_Assistant();
		}

		$response = $this->ai_assistant->get_recommendation( $context, $question );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		wp_send_json_success( array( 'recommendation' => $response ) );
	}

	/**
	 * AJAX: AI assist - general questions.
	 *
	 * @return void
	 */
	public function ajax_ai_assist() {
		check_ajax_referer( 'ict_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ict-platform' ) ) );
		}

		$question = isset( $_POST['question'] ) ? sanitize_text_field( $_POST['question'] ) : '';

		if ( empty( $question ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a question', 'ict-platform' ) ) );
		}

		// Get AI assistant
		if ( ! $this->ai_assistant ) {
			require_once ICT_PLATFORM_PLUGIN_DIR . 'admin/class-ict-ai-setup-assistant.php';
			$this->ai_assistant = new ICT_AI_Setup_Assistant();
		}

		$response = $this->ai_assistant->answer_question( $question );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		wp_send_json_success( array( 'answer' => $response ) );
	}
}
