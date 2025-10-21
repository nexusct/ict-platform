<?php
/**
 * QuoteWerks Admin Menu and Settings
 *
 * Handles QuoteWerks integration settings, connection testing, and sync management.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Admin_QuoteWerks
 *
 * Manages QuoteWerks settings and admin interface.
 */
class ICT_Admin_QuoteWerks {

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
		add_action( 'wp_ajax_ict_test_quotewerks_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ict_sync_quotes_manual', array( $this, 'ajax_sync_quotes' ) );
		add_action( 'wp_ajax_ict_get_quote_preview', array( $this, 'ajax_get_quote_preview' ) );
		add_action( 'wp_ajax_ict_regenerate_webhook_secret', array( $this, 'ajax_regenerate_webhook_secret' ) );
	}

	/**
	 * Add QuoteWerks menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_menu_page() {
		add_submenu_page(
			'ict-platform',
			__( 'QuoteWerks Integration', 'ict-platform' ),
			__( 'QuoteWerks', 'ict-platform' ),
			'manage_options',
			'ict-quotewerks',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Connection Settings
		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_api_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_api_key' ),
			'default'           => '',
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_username', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_password', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_password' ),
			'default'           => '',
		) );

		// Webhook Settings
		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_webhook_secret', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_webhook_enabled', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );

		// Sync Settings
		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_auto_sync', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_sync_line_items', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_create_tasks', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_default_project_status', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'planning',
		) );

		// Field Mapping Settings
		register_setting( 'ict_quotewerks_settings', 'ict_quotewerks_field_mappings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_field_mappings' ),
			'default'           => array(),
		) );

		// Add settings sections
		add_settings_section(
			'ict_quotewerks_connection',
			__( 'Connection Settings', 'ict-platform' ),
			array( $this, 'render_connection_section' ),
			'ict-quotewerks'
		);

		add_settings_section(
			'ict_quotewerks_webhook',
			__( 'Webhook Configuration', 'ict-platform' ),
			array( $this, 'render_webhook_section' ),
			'ict-quotewerks'
		);

		add_settings_section(
			'ict_quotewerks_sync_options',
			__( 'Sync Options', 'ict-platform' ),
			array( $this, 'render_sync_section' ),
			'ict-quotewerks'
		);

		add_settings_section(
			'ict_quotewerks_field_mapping',
			__( 'Field Mapping', 'ict-platform' ),
			array( $this, 'render_field_mapping_section' ),
			'ict-quotewerks'
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

		// If it's already encrypted (starts with our prefix), return as-is
		if ( strpos( $value, 'encrypted:' ) === 0 ) {
			return $value;
		}

		// Encrypt the API key
		return $this->encrypt_credential( $value );
	}

	/**
	 * Sanitize password.
	 *
	 * @since  1.0.0
	 * @param  string $value Password.
	 * @return string Encrypted password.
	 */
	public function sanitize_password( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// If it's already encrypted, return as-is
		if ( strpos( $value, 'encrypted:' ) === 0 ) {
			return $value;
		}

		// Encrypt the password
		return $this->encrypt_credential( $value );
	}

	/**
	 * Sanitize field mappings.
	 *
	 * @since  1.0.0
	 * @param  array $value Field mappings.
	 * @return array Sanitized mappings.
	 */
	public function sanitize_field_mappings( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $qw_field => $wp_field ) {
			$sanitized[ sanitize_text_field( $qw_field ) ] = sanitize_text_field( $wp_field );
		}

		return $sanitized;
	}

	/**
	 * Encrypt credential.
	 *
	 * @since  1.0.0
	 * @param  string $value Value to encrypt.
	 * @return string Encrypted value.
	 */
	private function encrypt_credential( $value ) {
		if ( ! defined( 'ICT_ENCRYPTION_KEY' ) ) {
			define( 'ICT_ENCRYPTION_KEY', wp_salt( 'auth' ) );
		}

		$iv = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt(
			$value,
			'AES-256-CBC',
			ICT_ENCRYPTION_KEY,
			0,
			$iv
		);

		return 'encrypted:' . base64_encode( $encrypted . '::' . $iv );
	}

	/**
	 * Decrypt credential.
	 *
	 * @since  1.0.0
	 * @param  string $value Encrypted value.
	 * @return string Decrypted value.
	 */
	public function decrypt_credential( $value ) {
		if ( strpos( $value, 'encrypted:' ) !== 0 ) {
			return $value;
		}

		if ( ! defined( 'ICT_ENCRYPTION_KEY' ) ) {
			define( 'ICT_ENCRYPTION_KEY', wp_salt( 'auth' ) );
		}

		$value = substr( $value, 10 ); // Remove 'encrypted:' prefix
		$value = base64_decode( $value );

		list( $encrypted_data, $iv ) = explode( '::', $value, 2 );

		return openssl_decrypt(
			$encrypted_data,
			'AES-256-CBC',
			ICT_ENCRYPTION_KEY,
			0,
			$iv
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since  1.0.0
	 * @param  string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'ict-platform_page_ict-quotewerks' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ict-quotewerks-admin',
			plugins_url( 'css/quotewerks-admin.css', __FILE__ ),
			array(),
			ICT_PLATFORM_VERSION
		);

		wp_enqueue_script(
			'ict-quotewerks-admin',
			plugins_url( 'js/quotewerks-admin.js', __FILE__ ),
			array( 'jquery' ),
			ICT_PLATFORM_VERSION,
			true
		);

		wp_localize_script(
			'ict-quotewerks-admin',
			'ictQuoteWerks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ict_quotewerks_admin' ),
				'strings' => array(
					'testing'         => __( 'Testing connection...', 'ict-platform' ),
					'success'         => __( 'Connection successful!', 'ict-platform' ),
					'error'           => __( 'Connection failed. Please check your settings.', 'ict-platform' ),
					'syncing'         => __( 'Syncing quotes...', 'ict-platform' ),
					'syncSuccess'     => __( 'Sync completed successfully!', 'ict-platform' ),
					'syncError'       => __( 'Sync failed. Please check the logs.', 'ict-platform' ),
					'regenerating'    => __( 'Regenerating webhook secret...', 'ict-platform' ),
					'regenerateSuccess' => __( 'Webhook secret regenerated!', 'ict-platform' ),
					'confirmRegenerate' => __( 'Are you sure? You will need to update the secret in QuoteWerks.', 'ict-platform' ),
				),
			)
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

		// Get connection status
		$connection_status = $this->get_connection_status();

		?>
		<div class="wrap ict-quotewerks-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'ict_quotewerks_messages' ); ?>

			<div class="ict-quotewerks-header">
				<div class="connection-status <?php echo esc_attr( $connection_status['class'] ); ?>">
					<span class="status-indicator"></span>
					<strong><?php esc_html_e( 'Connection Status:', 'ict-platform' ); ?></strong>
					<span class="status-text"><?php echo esc_html( $connection_status['message'] ); ?></span>
					<button type="button" class="button button-secondary" id="test-connection">
						<?php esc_html_e( 'Test Connection', 'ict-platform' ); ?>
					</button>
				</div>

				<div class="quick-actions">
					<button type="button" class="button button-primary" id="sync-quotes-now">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Sync Quotes Now', 'ict-platform' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ict-sync-log&source=quotewerks' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'View Sync Log', 'ict-platform' ); ?>
					</a>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'ict_quotewerks_settings' );
				do_settings_sections( 'ict-quotewerks' );
				submit_button();
				?>
			</form>

			<div class="ict-quotewerks-stats">
				<h2><?php esc_html_e( 'Sync Statistics', 'ict-platform' ); ?></h2>
				<?php $this->render_sync_stats(); ?>
			</div>

			<div class="ict-quotewerks-recent">
				<h2><?php esc_html_e( 'Recent Sync Activity', 'ict-platform' ); ?></h2>
				<?php $this->render_recent_activity(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get connection status.
	 *
	 * @since  1.0.0
	 * @return array Connection status with class and message.
	 */
	private function get_connection_status() {
		$api_url = get_option( 'ict_quotewerks_api_url' );
		$api_key = get_option( 'ict_quotewerks_api_key' );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			return array(
				'class'   => 'not-configured',
				'message' => __( 'Not Configured', 'ict-platform' ),
			);
		}

		// Check last test result
		$last_test = get_transient( 'ict_quotewerks_connection_test' );

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
	 * Render connection section description.
	 *
	 * @since 1.0.0
	 */
	public function render_connection_section() {
		?>
		<p><?php esc_html_e( 'Enter your QuoteWerks API credentials to enable automatic synchronization.', 'ict-platform' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_api_url"><?php esc_html_e( 'API URL', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="url" id="ict_quotewerks_api_url" name="ict_quotewerks_api_url"
						   value="<?php echo esc_attr( get_option( 'ict_quotewerks_api_url' ) ); ?>"
						   class="regular-text" placeholder="https://api.quotewerks.com" />
					<p class="description">
						<?php esc_html_e( 'Your QuoteWerks API endpoint URL.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_api_key"><?php esc_html_e( 'API Key', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="password" id="ict_quotewerks_api_key" name="ict_quotewerks_api_key"
						   value="<?php echo esc_attr( get_option( 'ict_quotewerks_api_key' ) ? '••••••••••••' : '' ); ?>"
						   class="regular-text" placeholder="<?php esc_attr_e( 'Enter API Key', 'ict-platform' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Your QuoteWerks API key (will be encrypted).', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_username"><?php esc_html_e( 'Username (Optional)', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="text" id="ict_quotewerks_username" name="ict_quotewerks_username"
						   value="<?php echo esc_attr( get_option( 'ict_quotewerks_username' ) ); ?>"
						   class="regular-text" />
					<p class="description">
						<?php esc_html_e( 'Username for basic authentication if required.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_password"><?php esc_html_e( 'Password (Optional)', 'ict-platform' ); ?></label>
				</th>
				<td>
					<input type="password" id="ict_quotewerks_password" name="ict_quotewerks_password"
						   value="<?php echo esc_attr( get_option( 'ict_quotewerks_password' ) ? '••••••••••••' : '' ); ?>"
						   class="regular-text" />
					<p class="description">
						<?php esc_html_e( 'Password for basic authentication if required (will be encrypted).', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render webhook section description.
	 *
	 * @since 1.0.0
	 */
	public function render_webhook_section() {
		$webhook_url = rest_url( 'ict/v1/webhooks/quotewerks' );
		$webhook_secret = get_option( 'ict_quotewerks_webhook_secret' );

		if ( empty( $webhook_secret ) ) {
			$webhook_secret = $this->generate_webhook_secret();
			update_option( 'ict_quotewerks_webhook_secret', $webhook_secret );
		}
		?>
		<p><?php esc_html_e( 'Configure QuoteWerks to send webhooks for real-time synchronization.', 'ict-platform' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Webhook URL', 'ict-platform' ); ?>
				</th>
				<td>
					<input type="text" value="<?php echo esc_url( $webhook_url ); ?>" class="large-text" readonly />
					<button type="button" class="button button-secondary copy-webhook-url" data-clipboard="<?php echo esc_attr( $webhook_url ); ?>">
						<?php esc_html_e( 'Copy', 'ict-platform' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Enter this URL in your QuoteWerks webhook configuration.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Webhook Secret', 'ict-platform' ); ?>
				</th>
				<td>
					<input type="text" value="<?php echo esc_attr( $webhook_secret ); ?>" class="large-text" readonly />
					<button type="button" class="button button-secondary copy-webhook-secret" data-clipboard="<?php echo esc_attr( $webhook_secret ); ?>">
						<?php esc_html_e( 'Copy', 'ict-platform' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="regenerate-webhook-secret">
						<?php esc_html_e( 'Regenerate', 'ict-platform' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Use this secret key to sign webhook requests in QuoteWerks.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_webhook_enabled"><?php esc_html_e( 'Enable Webhooks', 'ict-platform' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="ict_quotewerks_webhook_enabled" name="ict_quotewerks_webhook_enabled"
							   value="1" <?php checked( get_option( 'ict_quotewerks_webhook_enabled' ), true ); ?> />
						<?php esc_html_e( 'Process incoming webhooks from QuoteWerks', 'ict-platform' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable this after configuring webhooks in QuoteWerks.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="webhook-events-info">
			<h4><?php esc_html_e( 'Supported Webhook Events:', 'ict-platform' ); ?></h4>
			<ul>
				<li><code>quote.created</code> - <?php esc_html_e( 'When a new quote is created', 'ict-platform' ); ?></li>
				<li><code>quote.updated</code> - <?php esc_html_e( 'When a quote is modified', 'ict-platform' ); ?></li>
				<li><code>quote.approved</code> - <?php esc_html_e( 'When a quote is approved', 'ict-platform' ); ?></li>
				<li><code>quote.converted</code> - <?php esc_html_e( 'When a quote is converted to an order', 'ict-platform' ); ?></li>
				<li><code>order.created</code> - <?php esc_html_e( 'When an order is created', 'ict-platform' ); ?></li>
				<li><code>customer.updated</code> - <?php esc_html_e( 'When customer information is updated', 'ict-platform' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render sync section description.
	 *
	 * @since 1.0.0
	 */
	public function render_sync_section() {
		?>
		<p><?php esc_html_e( 'Configure how quotes are synchronized from QuoteWerks to WordPress.', 'ict-platform' ); ?></p>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_auto_sync"><?php esc_html_e( 'Automatic Sync', 'ict-platform' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="ict_quotewerks_auto_sync" name="ict_quotewerks_auto_sync"
							   value="1" <?php checked( get_option( 'ict_quotewerks_auto_sync', true ), true ); ?> />
						<?php esc_html_e( 'Automatically sync quotes to projects', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_sync_line_items"><?php esc_html_e( 'Sync Line Items', 'ict-platform' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="ict_quotewerks_sync_line_items" name="ict_quotewerks_sync_line_items"
							   value="1" <?php checked( get_option( 'ict_quotewerks_sync_line_items', true ), true ); ?> />
						<?php esc_html_e( 'Import quote line items to inventory', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_create_tasks"><?php esc_html_e( 'Create Tasks', 'ict-platform' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="ict_quotewerks_create_tasks" name="ict_quotewerks_create_tasks"
							   value="1" <?php checked( get_option( 'ict_quotewerks_create_tasks', true ), true ); ?> />
						<?php esc_html_e( 'Automatically create tasks from line items', 'ict-platform' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ict_quotewerks_default_project_status"><?php esc_html_e( 'Default Project Status', 'ict-platform' ); ?></label>
				</th>
				<td>
					<select id="ict_quotewerks_default_project_status" name="ict_quotewerks_default_project_status">
						<?php
						$statuses = array(
							'planning'    => __( 'Planning', 'ict-platform' ),
							'in-progress' => __( 'In Progress', 'ict-platform' ),
							'on-hold'     => __( 'On Hold', 'ict-platform' ),
							'completed'   => __( 'Completed', 'ict-platform' ),
							'cancelled'   => __( 'Cancelled', 'ict-platform' ),
						);
						$current = get_option( 'ict_quotewerks_default_project_status', 'planning' );
						foreach ( $statuses as $value => $label ) {
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
						<?php esc_html_e( 'Status assigned to newly synced projects.', 'ict-platform' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render field mapping section description.
	 *
	 * @since 1.0.0
	 */
	public function render_field_mapping_section() {
		?>
		<p><?php esc_html_e( 'Map QuoteWerks fields to WordPress project fields.', 'ict-platform' ); ?></p>
		<table class="form-table field-mapping-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'QuoteWerks Field', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'WordPress Field', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ict-platform' ); ?></th>
				</tr>
			</thead>
			<tbody id="field-mappings">
				<?php $this->render_field_mappings(); ?>
			</tbody>
		</table>
		<button type="button" class="button button-secondary" id="add-field-mapping">
			<?php esc_html_e( 'Add Mapping', 'ict-platform' ); ?>
		</button>
		<?php
	}

	/**
	 * Render field mappings.
	 *
	 * @since 1.0.0
	 */
	private function render_field_mappings() {
		$mappings = get_option( 'ict_quotewerks_field_mappings', array() );

		// Default mappings if none exist
		if ( empty( $mappings ) ) {
			$mappings = array(
				'DocNumber'     => 'quotewerks_id',
				'DocTotal'      => 'budget_amount',
				'DocDate'       => 'start_date',
				'CustomerName'  => 'client_name',
				'Description'   => 'description',
			);
		}

		$qw_fields = $this->get_quotewerks_fields();
		$wp_fields = $this->get_wordpress_fields();

		foreach ( $mappings as $qw_field => $wp_field ) {
			?>
			<tr class="field-mapping-row">
				<td>
					<select name="ict_quotewerks_field_mappings[<?php echo esc_attr( $qw_field ); ?>][qw]" class="qw-field">
						<?php foreach ( $qw_fields as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $qw_field, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<select name="ict_quotewerks_field_mappings[<?php echo esc_attr( $qw_field ); ?>][wp]" class="wp-field">
						<?php foreach ( $wp_fields as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $wp_field, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<button type="button" class="button button-link-delete remove-mapping">
						<?php esc_html_e( 'Remove', 'ict-platform' ); ?>
					</button>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Get QuoteWerks fields.
	 *
	 * @since  1.0.0
	 * @return array QuoteWerks fields.
	 */
	private function get_quotewerks_fields() {
		return array(
			'DocNumber'        => __( 'Quote Number', 'ict-platform' ),
			'DocTotal'         => __( 'Total Amount', 'ict-platform' ),
			'DocDate'          => __( 'Quote Date', 'ict-platform' ),
			'CustomerName'     => __( 'Customer Name', 'ict-platform' ),
			'CustomerID'       => __( 'Customer ID', 'ict-platform' ),
			'Description'      => __( 'Description', 'ict-platform' ),
			'Status'           => __( 'Status', 'ict-platform' ),
			'SalesPerson'      => __( 'Sales Person', 'ict-platform' ),
			'Terms'            => __( 'Payment Terms', 'ict-platform' ),
			'ShipToAddress'    => __( 'Shipping Address', 'ict-platform' ),
			'BillToAddress'    => __( 'Billing Address', 'ict-platform' ),
			'PurchaseOrder'    => __( 'PO Number', 'ict-platform' ),
			'Notes'            => __( 'Notes', 'ict-platform' ),
		);
	}

	/**
	 * Get WordPress fields.
	 *
	 * @since  1.0.0
	 * @return array WordPress fields.
	 */
	private function get_wordpress_fields() {
		return array(
			'quotewerks_id'  => __( 'QuoteWerks ID', 'ict-platform' ),
			'project_name'   => __( 'Project Name', 'ict-platform' ),
			'budget_amount'  => __( 'Budget Amount', 'ict-platform' ),
			'start_date'     => __( 'Start Date', 'ict-platform' ),
			'end_date'       => __( 'End Date', 'ict-platform' ),
			'client_name'    => __( 'Client Name', 'ict-platform' ),
			'description'    => __( 'Description', 'ict-platform' ),
			'status'         => __( 'Status', 'ict-platform' ),
			'priority'       => __( 'Priority', 'ict-platform' ),
			'notes'          => __( 'Notes', 'ict-platform' ),
			'po_number'      => __( 'PO Number', 'ict-platform' ),
			'location'       => __( 'Location', 'ict-platform' ),
		);
	}

	/**
	 * Render sync stats.
	 *
	 * @since 1.0.0
	 */
	private function render_sync_stats() {
		global $wpdb;

		// Get stats from last 30 days
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_syncs,
				SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
				SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
				MAX(created_at) as last_sync
			FROM " . ICT_SYNC_LOG_TABLE . "
			WHERE entity_type = 'quote'
			AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
			ARRAY_A
		);

		if ( ! $stats ) {
			echo '<p>' . esc_html__( 'No sync activity yet.', 'ict-platform' ) . '</p>';
			return;
		}

		$success_rate = $stats['total_syncs'] > 0
			? round( ( $stats['successful'] / $stats['total_syncs'] ) * 100, 1 )
			: 0;

		?>
		<div class="sync-stats-grid">
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( $stats['total_syncs'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total Syncs (30 days)', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box success">
				<div class="stat-value"><?php echo esc_html( $stats['successful'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Successful', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box error">
				<div class="stat-value"><?php echo esc_html( $stats['failed'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Failed', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( $success_rate ); ?>%</div>
				<div class="stat-label"><?php esc_html_e( 'Success Rate', 'ict-platform' ); ?></div>
			</div>
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( $stats['last_sync'] ? human_time_diff( strtotime( $stats['last_sync'] ), current_time( 'timestamp' ) ) . ' ago' : 'Never' ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Last Sync', 'ict-platform' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render recent activity.
	 *
	 * @since 1.0.0
	 */
	private function render_recent_activity() {
		global $wpdb;

		$recent = $wpdb->get_results(
			"SELECT *
			FROM " . ICT_SYNC_LOG_TABLE . "
			WHERE entity_type = 'quote'
			ORDER BY created_at DESC
			LIMIT 10",
			ARRAY_A
		);

		if ( empty( $recent ) ) {
			echo '<p>' . esc_html__( 'No recent activity.', 'ict-platform' ) . '</p>';
			return;
		}

		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'Quote ID', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'Action', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'ict-platform' ); ?></th>
					<th><?php esc_html_e( 'Message', 'ict-platform' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $row ) : ?>
					<tr>
						<td><?php echo esc_html( human_time_diff( strtotime( $row['created_at'] ), current_time( 'timestamp' ) ) . ' ago' ); ?></td>
						<td><?php echo esc_html( $row['entity_id'] ); ?></td>
						<td><?php echo esc_html( ucfirst( $row['action'] ) ); ?></td>
						<td>
							<span class="status-badge status-<?php echo esc_attr( $row['status'] ); ?>">
								<?php echo esc_html( ucfirst( $row['status'] ) ); ?>
							</span>
						</td>
						<td><?php echo isset( $row['duration_ms'] ) ? esc_html( $row['duration_ms'] . 'ms' ) : '-'; ?></td>
						<td><?php echo esc_html( $row['error_message'] ?: '-' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Generate webhook secret.
	 *
	 * @since  1.0.0
	 * @return string Webhook secret.
	 */
	private function generate_webhook_secret() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * AJAX handler: Test connection.
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'ict_quotewerks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		try {
			$adapter = new ICT_QuoteWerks_Adapter();
			$result = $adapter->test_connection();

			if ( is_wp_error( $result ) ) {
				set_transient( 'ict_quotewerks_connection_test', array( 'success' => false ), HOUR_IN_SECONDS );
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			set_transient( 'ict_quotewerks_connection_test', array( 'success' => true ), HOUR_IN_SECONDS );
			wp_send_json_success( array( 'message' => __( 'Connection successful!', 'ict-platform' ) ) );

		} catch ( Exception $e ) {
			set_transient( 'ict_quotewerks_connection_test', array( 'success' => false ), HOUR_IN_SECONDS );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler: Sync quotes manually.
	 *
	 * @since 1.0.0
	 */
	public function ajax_sync_quotes() {
		check_ajax_referer( 'ict_quotewerks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		try {
			$adapter = new ICT_QuoteWerks_Adapter();
			$result = $adapter->sync_recent_quotes( 10 ); // Sync last 10 quotes

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array(
				'message' => sprintf(
					__( 'Successfully synced %d quotes!', 'ict-platform' ),
					$result['synced']
				),
				'synced' => $result['synced'],
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler: Get quote preview.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_quote_preview() {
		check_ajax_referer( 'ict_quotewerks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$quote_id = isset( $_POST['quote_id'] ) ? sanitize_text_field( $_POST['quote_id'] ) : '';

		if ( empty( $quote_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Quote ID required.', 'ict-platform' ) ) );
		}

		try {
			$adapter = new ICT_QuoteWerks_Adapter();
			$quote = $adapter->get_quote( $quote_id );

			if ( is_wp_error( $quote ) ) {
				wp_send_json_error( array( 'message' => $quote->get_error_message() ) );
			}

			wp_send_json_success( array( 'quote' => $quote ) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler: Regenerate webhook secret.
	 *
	 * @since 1.0.0
	 */
	public function ajax_regenerate_webhook_secret() {
		check_ajax_referer( 'ict_quotewerks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ict-platform' ) ) );
		}

		$new_secret = $this->generate_webhook_secret();
		update_option( 'ict_quotewerks_webhook_secret', $new_secret );

		wp_send_json_success( array(
			'message' => __( 'Webhook secret regenerated successfully!', 'ict-platform' ),
			'secret'  => $new_secret,
		) );
	}
}
