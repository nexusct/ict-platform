<?php
/**
 * Email Template Manager
 *
 * Comprehensive email template system including:
 * - Customizable email templates for all notifications
 * - Variable placeholders for dynamic content
 * - HTML and plain text versions
 * - Email preview and testing
 * - Template categories and organization
 * - Multi-language support
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Email_Templates
 */
class ICT_Email_Templates {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Email_Templates
	 */
	private static $instance = null;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Available placeholders.
	 *
	 * @var array
	 */
	private $placeholders = array();

	/**
	 * Get singleton instance.
	 *
	 * @return ICT_Email_Templates
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

		$this->table = $wpdb->prefix . 'ict_email_templates';
		$this->init_placeholders();
		$this->init_hooks();
	}

	/**
	 * Initialize placeholders.
	 */
	private function init_placeholders() {
		$this->placeholders = array(
			'general' => array(
				'{site_name}'    => 'Website name',
				'{site_url}'     => 'Website URL',
				'{admin_email}'  => 'Admin email address',
				'{current_date}' => 'Current date',
				'{current_time}' => 'Current time',
				'{year}'         => 'Current year',
			),
			'user'    => array(
				'{user_name}'  => 'User display name',
				'{user_email}' => 'User email',
				'{user_first}' => 'User first name',
				'{user_last}'  => 'User last name',
				'{user_role}'  => 'User role',
			),
			'project' => array(
				'{project_name}'   => 'Project name',
				'{project_number}' => 'Project number',
				'{project_status}' => 'Project status',
				'{project_url}'    => 'Project URL',
				'{client_name}'    => 'Client name',
				'{client_email}'   => 'Client email',
			),
			'invoice' => array(
				'{invoice_number}'     => 'Invoice number',
				'{invoice_date}'       => 'Invoice date',
				'{invoice_due}'        => 'Due date',
				'{invoice_total}'      => 'Invoice total',
				'{invoice_due_amount}' => 'Amount due',
				'{payment_link}'       => 'Payment link',
			),
			'quote'   => array(
				'{quote_number}' => 'Quote number',
				'{quote_date}'   => 'Quote date',
				'{quote_valid}'  => 'Valid until date',
				'{quote_total}'  => 'Quote total',
				'{quote_link}'   => 'Quote view link',
			),
			'time'    => array(
				'{time_date}'        => 'Time entry date',
				'{time_hours}'       => 'Hours worked',
				'{time_project}'     => 'Project name',
				'{time_description}' => 'Work description',
			),
		);
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		register_activation_hook( ICT_PLUGIN_FILE, array( $this, 'maybe_create_tables' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_create_tables' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'ict_send_email', array( $this, 'process_email' ), 10, 4 );
	}

	/**
	 * Create database table.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(200) NOT NULL,
            category varchar(50) DEFAULT 'general',
            subject varchar(255) NOT NULL,
            body_html longtext NOT NULL,
            body_text longtext,
            from_name varchar(200) DEFAULT NULL,
            from_email varchar(200) DEFAULT NULL,
            reply_to varchar(200) DEFAULT NULL,
            cc varchar(500) DEFAULT NULL,
            bcc varchar(500) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            language varchar(10) DEFAULT 'en',
            variables longtext,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug_language (slug, language),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$this->seed_default_templates();
	}

	/**
	 * Seed default email templates.
	 */
	private function seed_default_templates() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
		if ( $count > 0 ) {
			return;
		}

		$templates = array(
			array(
				'slug'      => 'welcome_user',
				'name'      => 'Welcome New User',
				'category'  => 'user',
				'subject'   => 'Welcome to {site_name}!',
				'body_html' => '<h1>Welcome, {user_first}!</h1>
<p>Your account has been created at {site_name}.</p>
<p>You can log in at: <a href="{site_url}/wp-login.php">{site_url}</a></p>
<p>Best regards,<br>{site_name} Team</p>',
			),
			array(
				'slug'      => 'project_assigned',
				'name'      => 'Project Assignment Notification',
				'category'  => 'project',
				'subject'   => 'You have been assigned to project: {project_name}',
				'body_html' => '<h2>Project Assignment</h2>
<p>Hi {user_first},</p>
<p>You have been assigned to the following project:</p>
<ul>
<li><strong>Project:</strong> {project_name}</li>
<li><strong>Project Number:</strong> {project_number}</li>
<li><strong>Client:</strong> {client_name}</li>
</ul>
<p><a href="{project_url}">View Project Details</a></p>
<p>Best regards,<br>{site_name}</p>',
			),
			array(
				'slug'      => 'invoice_sent',
				'name'      => 'Invoice Sent to Client',
				'category'  => 'invoice',
				'subject'   => 'Invoice {invoice_number} from {site_name}',
				'body_html' => '<h2>Invoice {invoice_number}</h2>
<p>Dear {client_name},</p>
<p>Please find attached your invoice.</p>
<table>
<tr><td><strong>Invoice Number:</strong></td><td>{invoice_number}</td></tr>
<tr><td><strong>Date:</strong></td><td>{invoice_date}</td></tr>
<tr><td><strong>Due Date:</strong></td><td>{invoice_due}</td></tr>
<tr><td><strong>Total:</strong></td><td>{invoice_total}</td></tr>
</table>
<p><a href="{payment_link}">Pay Online</a></p>
<p>Thank you for your business!</p>',
			),
			array(
				'slug'      => 'invoice_reminder',
				'name'      => 'Payment Reminder',
				'category'  => 'invoice',
				'subject'   => 'Payment Reminder - Invoice {invoice_number}',
				'body_html' => '<h2>Payment Reminder</h2>
<p>Dear {client_name},</p>
<p>This is a reminder that invoice {invoice_number} is due for payment.</p>
<p><strong>Amount Due:</strong> {invoice_due_amount}</p>
<p><strong>Due Date:</strong> {invoice_due}</p>
<p><a href="{payment_link}">Pay Now</a></p>
<p>If you have already made payment, please disregard this notice.</p>',
			),
			array(
				'slug'      => 'quote_sent',
				'name'      => 'Quote Sent to Client',
				'category'  => 'quote',
				'subject'   => 'Quote {quote_number} from {site_name}',
				'body_html' => '<h2>Quote {quote_number}</h2>
<p>Dear {client_name},</p>
<p>Please find your quote attached.</p>
<p><strong>Quote Total:</strong> {quote_total}</p>
<p><strong>Valid Until:</strong> {quote_valid}</p>
<p><a href="{quote_link}">View Quote Online</a></p>
<p>Please let us know if you have any questions.</p>',
			),
			array(
				'slug'      => 'quote_accepted',
				'name'      => 'Quote Accepted Notification',
				'category'  => 'quote',
				'subject'   => 'Quote {quote_number} has been accepted',
				'body_html' => '<h2>Quote Accepted!</h2>
<p>{client_name} has accepted quote {quote_number}.</p>
<p><strong>Quote Total:</strong> {quote_total}</p>
<p>You can now proceed with creating the project.</p>',
			),
			array(
				'slug'      => 'time_approved',
				'name'      => 'Time Entry Approved',
				'category'  => 'time',
				'subject'   => 'Your time entry has been approved',
				'body_html' => '<p>Hi {user_first},</p>
<p>Your time entry has been approved:</p>
<ul>
<li><strong>Date:</strong> {time_date}</li>
<li><strong>Hours:</strong> {time_hours}</li>
<li><strong>Project:</strong> {time_project}</li>
</ul>',
			),
			array(
				'slug'      => 'certification_expiring',
				'name'      => 'Certification Expiring Soon',
				'category'  => 'compliance',
				'subject'   => 'Your certification is expiring soon',
				'body_html' => '<h2>Certification Expiring</h2>
<p>Hi {user_first},</p>
<p>Your certification is expiring soon. Please arrange for renewal.</p>
<p>Log in to your account to view details and update your certification.</p>',
			),
			array(
				'slug'      => 'password_reset',
				'name'      => 'Password Reset Request',
				'category'  => 'user',
				'subject'   => 'Password Reset Request - {site_name}',
				'body_html' => '<h2>Password Reset</h2>
<p>Hi {user_first},</p>
<p>We received a request to reset your password.</p>
<p><a href="{reset_link}">Click here to reset your password</a></p>
<p>If you did not request this, please ignore this email.</p>',
			),
			array(
				'slug'      => 'new_message',
				'name'      => 'New Message Notification',
				'category'  => 'communication',
				'subject'   => 'New message from {sender_name}',
				'body_html' => '<p>Hi {user_first},</p>
<p>You have received a new message from {sender_name}.</p>
<p><a href="{message_link}">View Message</a></p>',
			),
		);

		foreach ( $templates as $template ) {
			$wpdb->insert(
				$this->table,
				array(
					'slug'      => $template['slug'],
					'name'      => $template['name'],
					'category'  => $template['category'],
					'subject'   => $template['subject'],
					'body_html' => $template['body_html'],
					'body_text' => wp_strip_all_tags( $template['body_html'] ),
				)
			);
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$namespace = 'ict/v1';

		// Templates.
		register_rest_route(
			$namespace,
			'/email-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_templates' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/email-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_get_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'rest_update_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_delete_template' ),
					'permission_callback' => array( $this, 'check_manage_permission' ),
				),
			)
		);

		// By slug.
		register_rest_route(
			$namespace,
			'/email-templates/slug/(?P<slug>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_template_by_slug' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Preview.
		register_rest_route(
			$namespace,
			'/email-templates/(?P<id>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_preview_template' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Send test.
		register_rest_route(
			$namespace,
			'/email-templates/(?P<id>\d+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_send_test' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Duplicate.
		register_rest_route(
			$namespace,
			'/email-templates/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_duplicate_template' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Placeholders.
		register_rest_route(
			$namespace,
			'/email-templates/placeholders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_placeholders' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);

		// Categories.
		register_rest_route(
			$namespace,
			'/email-templates/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_categories' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
			)
		);
	}

	/**
	 * Check manage permission.
	 */
	public function check_manage_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get templates.
	 */
	public function rest_get_templates( $request ) {
		global $wpdb;

		$category = $request->get_param( 'category' );
		$language = $request->get_param( 'language' ) ?: 'en';

		$sql  = "SELECT * FROM {$this->table} WHERE language = %s";
		$args = array( $language );

		if ( $category ) {
			$sql   .= ' AND category = %s';
			$args[] = $category;
		}

		$sql .= ' ORDER BY category, name';

		$templates = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

		return rest_ensure_response(
			array(
				'success'   => true,
				'templates' => $templates,
			)
		);
	}

	/**
	 * Get single template.
	 */
	public function rest_get_template( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$template->variables = json_decode( $template->variables, true );

		return rest_ensure_response(
			array(
				'success'  => true,
				'template' => $template,
			)
		);
	}

	/**
	 * Get template by slug.
	 */
	public function rest_get_template_by_slug( $request ) {
		global $wpdb;

		$slug     = sanitize_text_field( $request->get_param( 'slug' ) );
		$language = $request->get_param( 'language' ) ?: 'en';

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE slug = %s AND language = %s",
				$slug,
				$language
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'template' => $template,
			)
		);
	}

	/**
	 * Create template.
	 */
	public function rest_create_template( $request ) {
		global $wpdb;

		$slug     = sanitize_title( $request->get_param( 'slug' ) );
		$language = sanitize_text_field( $request->get_param( 'language' ) ) ?: 'en';

		// Check for duplicate.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE slug = %s AND language = %s",
				$slug,
				$language
			)
		);

		if ( $existing ) {
			return new WP_Error( 'duplicate', 'Template with this slug already exists', array( 'status' => 409 ) );
		}

		$wpdb->insert(
			$this->table,
			array(
				'slug'       => $slug,
				'name'       => sanitize_text_field( $request->get_param( 'name' ) ),
				'category'   => sanitize_text_field( $request->get_param( 'category' ) ) ?: 'general',
				'subject'    => sanitize_text_field( $request->get_param( 'subject' ) ),
				'body_html'  => wp_kses_post( $request->get_param( 'body_html' ) ),
				'body_text'  => sanitize_textarea_field( $request->get_param( 'body_text' ) ) ?: wp_strip_all_tags( $request->get_param( 'body_html' ) ),
				'from_name'  => sanitize_text_field( $request->get_param( 'from_name' ) ),
				'from_email' => sanitize_email( $request->get_param( 'from_email' ) ),
				'reply_to'   => sanitize_email( $request->get_param( 'reply_to' ) ),
				'cc'         => sanitize_text_field( $request->get_param( 'cc' ) ),
				'bcc'        => sanitize_text_field( $request->get_param( 'bcc' ) ),
				'is_active'  => $request->get_param( 'is_active' ) !== false ? 1 : 0,
				'language'   => $language,
				'variables'  => wp_json_encode( $request->get_param( 'variables' ) ),
				'created_by' => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'success'     => true,
				'template_id' => $wpdb->insert_id,
				'message'     => 'Template created successfully',
			)
		);
	}

	/**
	 * Update template.
	 */
	public function rest_update_template( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$fields = array(
			'name',
			'category',
			'subject',
			'body_html',
			'body_text',
			'from_name',
			'from_email',
			'reply_to',
			'cc',
			'bcc',
			'is_active',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				if ( $field === 'body_html' ) {
					$data[ $field ] = wp_kses_post( $value );
				} elseif ( in_array( $field, array( 'from_email', 'reply_to' ) ) ) {
					$data[ $field ] = sanitize_email( $value );
				} else {
					$data[ $field ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
				}
			}
		}

		$variables = $request->get_param( 'variables' );
		if ( $variables !== null ) {
			$data['variables'] = wp_json_encode( $variables );
		}

		if ( ! empty( $data ) ) {
			$wpdb->update( $this->table, $data, array( 'id' => $id ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Template updated successfully',
			)
		);
	}

	/**
	 * Delete template.
	 */
	public function rest_delete_template( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$wpdb->delete( $this->table, array( 'id' => $id ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Template deleted successfully',
			)
		);
	}

	/**
	 * Preview template.
	 */
	public function rest_preview_template( $request ) {
		global $wpdb;

		$id        = intval( $request->get_param( 'id' ) );
		$test_data = $request->get_param( 'data' ) ?: array();

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		// Apply sample data.
		$sample_data = $this->get_sample_data();
		$data        = array_merge( $sample_data, $test_data );

		$subject   = $this->replace_placeholders( $template->subject, $data );
		$body_html = $this->replace_placeholders( $template->body_html, $data );
		$body_text = $this->replace_placeholders( $template->body_text, $data );

		// Wrap in email layout.
		$body_html = $this->wrap_html_email( $body_html );

		return rest_ensure_response(
			array(
				'success'   => true,
				'subject'   => $subject,
				'body_html' => $body_html,
				'body_text' => $body_text,
			)
		);
	}

	/**
	 * Send test email.
	 */
	public function rest_send_test( $request ) {
		global $wpdb;

		$id       = intval( $request->get_param( 'id' ) );
		$to_email = sanitize_email( $request->get_param( 'to_email' ) );

		if ( ! $to_email ) {
			$to_email = wp_get_current_user()->user_email;
		}

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$data      = $this->get_sample_data();
		$test_data = $request->get_param( 'data' );
		if ( is_array( $test_data ) ) {
			$data = array_merge( $data, $test_data );
		}

		$subject   = '[TEST] ' . $this->replace_placeholders( $template->subject, $data );
		$body_html = $this->replace_placeholders( $template->body_html, $data );
		$body_html = $this->wrap_html_email( $body_html );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( $template->from_name || $template->from_email ) {
			$from_name  = $template->from_name ?: get_bloginfo( 'name' );
			$from_email = $template->from_email ?: get_option( 'admin_email' );
			$headers[]  = "From: {$from_name} <{$from_email}>";
		}

		$sent = wp_mail( $to_email, $subject, $body_html, $headers );

		return rest_ensure_response(
			array(
				'success' => $sent,
				'message' => $sent ? 'Test email sent successfully' : 'Failed to send test email',
			)
		);
	}

	/**
	 * Duplicate template.
	 */
	public function rest_duplicate_template( $request ) {
		global $wpdb;

		$id = intval( $request->get_param( 'id' ) );

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( ! $template ) {
			return new WP_Error( 'not_found', 'Template not found', array( 'status' => 404 ) );
		}

		$new_slug = $template->slug . '_copy_' . time();
		$new_name = $template->name . ' (Copy)';

		$data = (array) $template;
		unset( $data['id'], $data['created_at'], $data['updated_at'] );
		$data['slug']       = $new_slug;
		$data['name']       = $new_name;
		$data['created_by'] = get_current_user_id();

		$wpdb->insert( $this->table, $data );

		return rest_ensure_response(
			array(
				'success'     => true,
				'template_id' => $wpdb->insert_id,
				'message'     => 'Template duplicated successfully',
			)
		);
	}

	/**
	 * Get placeholders.
	 */
	public function rest_get_placeholders( $request ) {
		return rest_ensure_response(
			array(
				'success'      => true,
				'placeholders' => $this->placeholders,
			)
		);
	}

	/**
	 * Get categories.
	 */
	public function rest_get_categories( $request ) {
		global $wpdb;

		$categories = $wpdb->get_results(
			"SELECT category, COUNT(*) as count FROM {$this->table} GROUP BY category ORDER BY category"
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Process email using template.
	 *
	 * @param string $slug     Template slug.
	 * @param string $to       Recipient email.
	 * @param array  $data     Placeholder data.
	 * @param array  $options  Additional options.
	 * @return bool
	 */
	public function process_email( $slug, $to, $data = array(), $options = array() ) {
		global $wpdb;

		$language = $options['language'] ?? 'en';

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE slug = %s AND language = %s AND is_active = 1",
				$slug,
				$language
			)
		);

		if ( ! $template ) {
			// Fallback to English.
			$template = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE slug = %s AND language = 'en' AND is_active = 1",
					$slug
				)
			);
		}

		if ( ! $template ) {
			return false;
		}

		// Merge with default data.
		$default_data = $this->get_default_data();
		$data         = array_merge( $default_data, $data );

		// Replace placeholders.
		$subject   = $this->replace_placeholders( $template->subject, $data );
		$body_html = $this->replace_placeholders( $template->body_html, $data );
		$body_html = $this->wrap_html_email( $body_html );

		// Build headers.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$from_name  = $template->from_name ?: get_bloginfo( 'name' );
		$from_email = $template->from_email ?: get_option( 'admin_email' );
		$headers[]  = "From: {$from_name} <{$from_email}>";

		if ( $template->reply_to ) {
			$headers[] = "Reply-To: {$template->reply_to}";
		}

		if ( $template->cc ) {
			$headers[] = "Cc: {$template->cc}";
		}

		if ( $template->bcc ) {
			$headers[] = "Bcc: {$template->bcc}";
		}

		// Attachments.
		$attachments = $options['attachments'] ?? array();

		return wp_mail( $to, $subject, $body_html, $headers, $attachments );
	}

	/**
	 * Replace placeholders in content.
	 *
	 * @param string $content Content with placeholders.
	 * @param array  $data    Replacement data.
	 * @return string
	 */
	private function replace_placeholders( $content, $data ) {
		foreach ( $data as $key => $value ) {
			$placeholder = '{' . $key . '}';
			$content     = str_replace( $placeholder, $value, $content );
		}

		// Remove any unreplaced placeholders.
		$content = preg_replace( '/\{[a-z_]+\}/', '', $content );

		return $content;
	}

	/**
	 * Get default placeholder data.
	 *
	 * @return array
	 */
	private function get_default_data() {
		return array(
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'admin_email'  => get_option( 'admin_email' ),
			'current_date' => current_time( 'F j, Y' ),
			'current_time' => current_time( 'g:i a' ),
			'year'         => current_time( 'Y' ),
		);
	}

	/**
	 * Get sample data for preview.
	 *
	 * @return array
	 */
	private function get_sample_data() {
		$current_user = wp_get_current_user();

		return array_merge(
			$this->get_default_data(),
			array(
				'user_name'          => $current_user->display_name ?: 'John Doe',
				'user_email'         => $current_user->user_email ?: 'john@example.com',
				'user_first'         => $current_user->first_name ?: 'John',
				'user_last'          => $current_user->last_name ?: 'Doe',
				'user_role'          => 'Project Manager',
				'project_name'       => 'Sample Project',
				'project_number'     => 'PRJ-2024-001',
				'project_status'     => 'In Progress',
				'project_url'        => home_url( '/project/sample' ),
				'client_name'        => 'Acme Corporation',
				'client_email'       => 'contact@acme.com',
				'invoice_number'     => 'INV-2024-001',
				'invoice_date'       => current_time( 'F j, Y' ),
				'invoice_due'        => date( 'F j, Y', strtotime( '+30 days' ) ),
				'invoice_total'      => '$1,500.00',
				'invoice_due_amount' => '$1,500.00',
				'payment_link'       => home_url( '/pay/sample' ),
				'quote_number'       => 'Q-2024-001',
				'quote_date'         => current_time( 'F j, Y' ),
				'quote_valid'        => date( 'F j, Y', strtotime( '+30 days' ) ),
				'quote_total'        => '$2,000.00',
				'quote_link'         => home_url( '/quote/sample' ),
				'time_date'          => current_time( 'F j, Y' ),
				'time_hours'         => '8.0',
				'time_project'       => 'Sample Project',
				'time_description'   => 'Development work',
				'sender_name'        => 'Jane Smith',
				'message_link'       => home_url( '/messages/1' ),
				'reset_link'         => home_url( '/reset-password/sample-token' ),
			)
		);
	}

	/**
	 * Wrap HTML content in email layout.
	 *
	 * @param string $content HTML content.
	 * @return string
	 */
	private function wrap_html_email( $content ) {
		$site_name = get_bloginfo( 'name' );
		$year      = current_time( 'Y' );

		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 30px; background: #fff; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        a { color: #0073aa; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . esc_html( $site_name ) . '</h1>
        </div>
        <div class="content">
            ' . $content . '
        </div>
        <div class="footer">
            <p>&copy; ' . $year . ' ' . esc_html( $site_name ) . '. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
	}

	/**
	 * Send email using template.
	 *
	 * @param string $slug Template slug.
	 * @param string $to   Recipient email.
	 * @param array  $data Placeholder data.
	 * @return bool
	 */
	public function send( $slug, $to, $data = array() ) {
		return apply_filters( 'ict_send_email', $slug, $to, $data, array() );
	}
}
