<?php
/**
 * AI Setup Assistant for ICT Platform
 *
 * Provides intelligent recommendations and guidance during the setup wizard
 * using AI-powered responses based on business context and best practices.
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_AI_Setup_Assistant
 *
 * Handles AI-powered assistance during plugin setup and configuration.
 */
class ICT_AI_Setup_Assistant {

	/**
	 * OpenAI API endpoint.
	 *
	 * @var string
	 */
	private $openai_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Anthropic API endpoint.
	 *
	 * @var string
	 */
	private $anthropic_endpoint = 'https://api.anthropic.com/v1/messages';

	/**
	 * AI provider to use.
	 *
	 * @var string
	 */
	private $ai_provider = 'builtin';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ai_provider = get_option( 'ict_ai_provider', 'builtin' );
	}

	/**
	 * Get recommendation based on context.
	 *
	 * @param string $context Setup context (company, zoho, teams, etc.).
	 * @param string $question Optional specific question.
	 * @return string|WP_Error Recommendation text or error.
	 */
	public function get_recommendation( $context, $question = '' ) {
		// Get company info for context
		$company_info = $this->get_company_context();

		// Get context-specific recommendation
		switch ( $context ) {
			case 'company':
				return $this->get_company_recommendations( $company_info );
			case 'zoho':
				return $this->get_zoho_recommendations( $company_info );
			case 'teams':
				return $this->get_teams_recommendations( $company_info );
			case 'notifications':
				return $this->get_notification_recommendations( $company_info );
			case 'security':
				return $this->get_security_recommendations( $company_info );
			case 'features':
				return $this->get_features_recommendations( $company_info );
			case 'getting_started':
				return $this->get_getting_started_recommendations( $company_info );
			default:
				if ( ! empty( $question ) ) {
					return $this->answer_question( $question );
				}
				return new WP_Error( 'invalid_context', __( 'Invalid context provided', 'ict-platform' ) );
		}
	}

	/**
	 * Answer a general question.
	 *
	 * @param string $question User's question.
	 * @return string|WP_Error Answer text or error.
	 */
	public function answer_question( $question ) {
		// Check if external AI is configured
		$api_key = $this->get_ai_api_key();

		if ( ! empty( $api_key ) && $this->ai_provider !== 'builtin' ) {
			return $this->get_external_ai_response( $question );
		}

		// Use built-in knowledge base
		return $this->get_builtin_response( $question );
	}

	/**
	 * Get company context for personalized recommendations.
	 *
	 * @return array Company information.
	 */
	private function get_company_context() {
		return array(
			'name'      => get_option( 'ict_company_name', get_bloginfo( 'name' ) ),
			'industry'  => get_option( 'ict_industry', 'electrical' ),
			'size'      => get_option( 'ict_company_size', 'small' ),
			'use_cases' => get_option( 'ict_use_cases', array( 'project_management', 'time_tracking' ) ),
			'currency'  => get_option( 'ict_currency', 'USD' ),
		);
	}

	/**
	 * Get company setup recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_company_recommendations( $company_info ) {
		$industry = $company_info['industry'];
		$size     = $company_info['size'];

		$recommendations = array();

		// Industry-specific advice
		switch ( $industry ) {
			case 'electrical':
				$recommendations[] = __( '**For Electrical Contracting:**', 'ict-platform' );
				$recommendations[] = __( '- Enable "Field Service Dispatch" to manage job assignments efficiently', 'ict-platform' );
				$recommendations[] = __( '- Consider connecting Zoho FSM for service appointment scheduling', 'ict-platform' );
				$recommendations[] = __( '- Inventory tracking is essential for managing parts and equipment', 'ict-platform' );
				break;
			case 'ict':
				$recommendations[] = __( '**For ICT/Telecommunications:**', 'ict-platform' );
				$recommendations[] = __( '- Project Management is crucial for network installations and upgrades', 'ict-platform' );
				$recommendations[] = __( '- Consider enabling the Custom Field Builder for equipment serial tracking', 'ict-platform' );
				$recommendations[] = __( '- Zoho Desk integration helps manage customer support tickets', 'ict-platform' );
				break;
			case 'hvac':
				$recommendations[] = __( '**For HVAC Services:**', 'ict-platform' );
				$recommendations[] = __( '- Seasonal scheduling is important - enable calendar features', 'ict-platform' );
				$recommendations[] = __( '- GPS tracking helps optimize technician routes', 'ict-platform' );
				$recommendations[] = __( '- Maintenance contract tracking through custom fields', 'ict-platform' );
				break;
			default:
				$recommendations[] = __( '**General Field Service Recommendations:**', 'ict-platform' );
				$recommendations[] = __( '- Start with Project Management and Time Tracking enabled', 'ict-platform' );
				$recommendations[] = __( '- Add features as your team becomes comfortable with the platform', 'ict-platform' );
		}

		// Size-specific advice
		$recommendations[] = '';
		switch ( $size ) {
			case 'solo':
			case 'small':
				$recommendations[] = __( '**For Your Team Size:**', 'ict-platform' );
				$recommendations[] = __( '- The default role setup should work well - you can customize later', 'ict-platform' );
				$recommendations[] = __( '- Start simple and add integrations as needed', 'ict-platform' );
				$recommendations[] = __( '- Email notifications are usually sufficient initially', 'ict-platform' );
				break;
			case 'medium':
			case 'large':
			case 'enterprise':
				$recommendations[] = __( '**For Larger Organizations:**', 'ict-platform' );
				$recommendations[] = __( '- Set up Microsoft Teams integration for better team communication', 'ict-platform' );
				$recommendations[] = __( '- Consider SMS notifications for field technicians', 'ict-platform' );
				$recommendations[] = __( '- Advanced role management will help with permissions', 'ict-platform' );
				$recommendations[] = __( '- Scheduled reports can help management stay informed', 'ict-platform' );
				break;
		}

		return implode( "\n", $recommendations );
	}

	/**
	 * Get Zoho integration recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_zoho_recommendations( $company_info ) {
		$use_cases = $company_info['use_cases'];
		$size      = $company_info['size'];

		$recommendations   = array();
		$recommendations[] = __( '**Recommended Zoho Services for Your Business:**', 'ict-platform' );
		$recommendations[] = '';

		// Core recommendation
		$recommendations[] = __( '**Zoho CRM** (Highly Recommended)', 'ict-platform' );
		$recommendations[] = __( '- Central hub for customer and project data', 'ict-platform' );
		$recommendations[] = __( '- Syncs contacts, companies, and deals/projects', 'ict-platform' );
		$recommendations[] = '';

		// Based on use cases
		if ( in_array( 'field_service', $use_cases, true ) ) {
			$recommendations[] = __( '**Zoho FSM** (Recommended for Field Service)', 'ict-platform' );
			$recommendations[] = __( '- Service appointment scheduling and dispatch', 'ict-platform' );
			$recommendations[] = __( '- Technician routing and status tracking', 'ict-platform' );
			$recommendations[] = '';
		}

		if ( in_array( 'invoicing', $use_cases, true ) || in_array( 'inventory', $use_cases, true ) ) {
			$recommendations[] = __( '**Zoho Books** (Recommended for Invoicing/Inventory)', 'ict-platform' );
			$recommendations[] = __( '- Invoice generation from completed projects', 'ict-platform' );
			$recommendations[] = __( '- Inventory sync for parts and materials', 'ict-platform' );
			$recommendations[] = __( '- Purchase order management', 'ict-platform' );
			$recommendations[] = '';
		}

		if ( in_array( 'time_tracking', $use_cases, true ) || $size === 'medium' || $size === 'large' || $size === 'enterprise' ) {
			$recommendations[] = __( '**Zoho People** (Recommended for Time Tracking)', 'ict-platform' );
			$recommendations[] = __( '- Employee time and attendance sync', 'ict-platform' );
			$recommendations[] = __( '- Leave management integration', 'ict-platform' );
			$recommendations[] = '';
		}

		// Setup tips
		$recommendations[] = __( '**Setup Tips:**', 'ict-platform' );
		$recommendations[] = __( '1. Start with Zoho CRM - it\'s the foundation for other integrations', 'ict-platform' );
		$recommendations[] = __( '2. Use the SAME Zoho organization for all services for seamless sync', 'ict-platform' );
		$recommendations[] = __( '3. You can add more services later without losing data', 'ict-platform' );

		return implode( "\n", $recommendations );
	}

	/**
	 * Get Teams setup recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_teams_recommendations( $company_info ) {
		$size = $company_info['size'];

		$recommendations   = array();
		$recommendations[] = __( '**Microsoft Teams Integration Guide:**', 'ict-platform' );
		$recommendations[] = '';

		// Recommend approach based on size
		if ( in_array( $size, array( 'solo', 'small' ), true ) ) {
			$recommendations[] = __( '**Recommended: Webhook Method (Simpler)**', 'ict-platform' );
			$recommendations[] = __( 'For smaller teams, the Incoming Webhook method is:', 'ict-platform' );
			$recommendations[] = __( '- Faster to set up (under 5 minutes)', 'ict-platform' );
			$recommendations[] = __( '- No Azure account required', 'ict-platform' );
			$recommendations[] = __( '- Perfect for receiving notifications in a channel', 'ict-platform' );
			$recommendations[] = '';
			$recommendations[] = __( '**Quick Setup Steps:**', 'ict-platform' );
			$recommendations[] = __( '1. In Teams, right-click your desired channel', 'ict-platform' );
			$recommendations[] = __( '2. Select "Connectors" → "Incoming Webhook"', 'ict-platform' );
			$recommendations[] = __( '3. Name it "ICT Platform" and create', 'ict-platform' );
			$recommendations[] = __( '4. Copy the URL and paste it in the Webhook URL field', 'ict-platform' );
		} else {
			$recommendations[] = __( '**Recommended: Full OAuth Integration**', 'ict-platform' );
			$recommendations[] = __( 'For larger organizations, OAuth integration provides:', 'ict-platform' );
			$recommendations[] = __( '- User-specific notifications', 'ict-platform' );
			$recommendations[] = __( '- Interactive message cards', 'ict-platform' );
			$recommendations[] = __( '- Bot capabilities for two-way communication', 'ict-platform' );
			$recommendations[] = '';
			$recommendations[] = __( '**Setup requires:**', 'ict-platform' );
			$recommendations[] = __( '- Azure AD tenant access', 'ict-platform' );
			$recommendations[] = __( '- App registration permissions', 'ict-platform' );
			$recommendations[] = __( '- Admin consent for your organization', 'ict-platform' );
		}

		$recommendations[] = '';
		$recommendations[] = __( '**Best Notification Channels by Type:**', 'ict-platform' );
		$recommendations[] = __( '- Project updates → #general or #projects channel', 'ict-platform' );
		$recommendations[] = __( '- Low stock alerts → #inventory or #operations channel', 'ict-platform' );
		$recommendations[] = __( '- Time entry reminders → Individual or team channels', 'ict-platform' );

		return implode( "\n", $recommendations );
	}

	/**
	 * Get notification recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_notification_recommendations( $company_info ) {
		$size     = $company_info['size'];
		$industry = $company_info['industry'];

		$recommendations   = array();
		$recommendations[] = __( '**Notification Strategy Recommendations:**', 'ict-platform' );
		$recommendations[] = '';

		// Email is always recommended
		$recommendations[] = __( '**Email Notifications** ✓ Recommended', 'ict-platform' );
		$recommendations[] = __( '- Best for: Detailed updates, reports, documentation', 'ict-platform' );
		$recommendations[] = __( '- Enable digest mode if you send many notifications daily', 'ict-platform' );
		$recommendations[] = '';

		// SMS recommendations based on industry
		if ( in_array( $industry, array( 'electrical', 'hvac', 'plumbing' ), true ) ) {
			$recommendations[] = __( '**SMS via Twilio** ✓ Highly Recommended for Field Service', 'ict-platform' );
			$recommendations[] = __( '- Best for: Urgent alerts, job dispatch, schedule changes', 'ict-platform' );
			$recommendations[] = __( '- Field technicians often prefer SMS over email', 'ict-platform' );
			$recommendations[] = __( '- Twilio pricing: ~$0.0075/SMS in the US', 'ict-platform' );
			$recommendations[] = '';
		} else {
			$recommendations[] = __( '**SMS via Twilio** - Optional', 'ict-platform' );
			$recommendations[] = __( '- Consider for urgent time-sensitive notifications', 'ict-platform' );
			$recommendations[] = __( '- Useful if team members are often away from email', 'ict-platform' );
			$recommendations[] = '';
		}

		// Push based on company size
		if ( in_array( $size, array( 'medium', 'large', 'enterprise' ), true ) ) {
			$recommendations[] = __( '**Push Notifications** - Recommended', 'ict-platform' );
			$recommendations[] = __( '- Free alternative to SMS for instant alerts', 'ict-platform' );
			$recommendations[] = __( '- Works in browsers and mobile apps', 'ict-platform' );
			$recommendations[] = __( '- Users must opt-in to receive', 'ict-platform' );
		} else {
			$recommendations[] = __( '**Push Notifications** - Optional', 'ict-platform' );
			$recommendations[] = __( '- Good free option for instant notifications', 'ict-platform' );
			$recommendations[] = __( '- Requires HTTPS and browser permission', 'ict-platform' );
		}

		$recommendations[] = '';
		$recommendations[] = __( '**Pro Tip:** Start with Email, add SMS for field technicians, then add push for office staff.', 'ict-platform' );

		return implode( "\n", $recommendations );
	}

	/**
	 * Get security recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_security_recommendations( $company_info ) {
		$size = $company_info['size'];

		$recommendations   = array();
		$recommendations[] = __( '**Security Best Practices:**', 'ict-platform' );
		$recommendations[] = '';

		// Biometric recommendations
		$recommendations[] = __( '**Biometric Authentication**', 'ict-platform' );
		if ( is_ssl() ) {
			$recommendations[] = __( '✓ Your site uses HTTPS - biometric auth is available', 'ict-platform' );
		} else {
			$recommendations[] = __( '⚠️ HTTPS required - please enable SSL for biometric auth', 'ict-platform' );
		}
		$recommendations[] = '';

		if ( in_array( $size, array( 'medium', 'large', 'enterprise' ), true ) ) {
			$recommendations[] = __( '**Recommended for your organization:**', 'ict-platform' );
			$recommendations[] = __( '- Enable biometric auth for all users', 'ict-platform' );
			$recommendations[] = __( '- Require biometric for mobile app access', 'ict-platform' );
			$recommendations[] = __( '- Use dynamic permissions for project-level access', 'ict-platform' );
		} else {
			$recommendations[] = __( '**Recommended for smaller teams:**', 'ict-platform' );
			$recommendations[] = __( '- Biometric auth is optional but adds security', 'ict-platform' );
			$recommendations[] = __( '- Default roles should be sufficient', 'ict-platform' );
			$recommendations[] = __( '- Keep dynamic permissions disabled for simplicity', 'ict-platform' );
		}

		$recommendations[] = '';
		$recommendations[] = __( '**Role Setup Guide:**', 'ict-platform' );
		$recommendations[] = __( '- **Administrator**: Business owners, IT staff', 'ict-platform' );
		$recommendations[] = __( '- **Project Manager**: Team leads, supervisors', 'ict-platform' );
		$recommendations[] = __( '- **Technician**: Field workers, installers', 'ict-platform' );
		$recommendations[] = __( '- **Inventory Manager**: Warehouse, purchasing staff', 'ict-platform' );
		$recommendations[] = __( '- **Accountant**: Bookkeeper, finance team', 'ict-platform' );
		$recommendations[] = __( '- **Viewer**: Clients, stakeholders (read-only)', 'ict-platform' );

		return implode( "\n", $recommendations );
	}

	/**
	 * Get features recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_features_recommendations( $company_info ) {
		$use_cases = $company_info['use_cases'];
		$industry  = $company_info['industry'];

		$recommendations   = array();
		$recommendations[] = __( '**Feature Recommendations for Your Business:**', 'ict-platform' );
		$recommendations[] = '';

		// Offline mode
		if ( in_array( $industry, array( 'electrical', 'hvac', 'plumbing', 'general' ), true ) ) {
			$recommendations[] = __( '**Offline Mode** ✓ Highly Recommended', 'ict-platform' );
			$recommendations[] = __( '- Essential for field work in areas with poor connectivity', 'ict-platform' );
			$recommendations[] = __( '- Technicians can log time and updates without internet', 'ict-platform' );
			$recommendations[] = __( '- Data syncs automatically when connection is restored', 'ict-platform' );
			$recommendations[] = __( '- Recommended conflict resolution: "Server wins" (safest)', 'ict-platform' );
		} else {
			$recommendations[] = __( '**Offline Mode** - Recommended', 'ict-platform' );
			$recommendations[] = __( '- Useful for mobile workers and unreliable networks', 'ict-platform' );
			$recommendations[] = __( '- Can be disabled if all work is done at connected locations', 'ict-platform' );
		}
		$recommendations[] = '';

		// Custom fields based on use cases
		$recommendations[] = __( '**Custom Field Builder** ✓ Recommended', 'ict-platform' );
		$recommendations[] = __( 'Suggested custom fields for your industry:', 'ict-platform' );

		switch ( $industry ) {
			case 'electrical':
				$recommendations[] = __( '- Panel type (dropdown)', 'ict-platform' );
				$recommendations[] = __( '- Circuit breaker info (text)', 'ict-platform' );
				$recommendations[] = __( '- Permit number (text)', 'ict-platform' );
				$recommendations[] = __( '- Inspection date (date)', 'ict-platform' );
				break;
			case 'ict':
				$recommendations[] = __( '- Equipment serial numbers (text)', 'ict-platform' );
				$recommendations[] = __( '- Network diagrams (file upload)', 'ict-platform' );
				$recommendations[] = __( '- IP addresses (text)', 'ict-platform' );
				$recommendations[] = __( '- Warranty expiration (date)', 'ict-platform' );
				break;
			case 'hvac':
				$recommendations[] = __( '- Equipment model (text)', 'ict-platform' );
				$recommendations[] = __( '- Refrigerant type (dropdown)', 'ict-platform' );
				$recommendations[] = __( '- Filter size (text)', 'ict-platform' );
				$recommendations[] = __( '- Maintenance schedule (dropdown)', 'ict-platform' );
				break;
			default:
				$recommendations[] = __( '- Client contact (text)', 'ict-platform' );
				$recommendations[] = __( '- Site photos (file upload)', 'ict-platform' );
				$recommendations[] = __( '- Priority level (dropdown)', 'ict-platform' );
				$recommendations[] = __( '- Completion checklist (checkbox)', 'ict-platform' );
		}
		$recommendations[] = '';

		// Reporting
		$recommendations[] = __( '**Advanced Reporting** (Always Enabled)', 'ict-platform' );
		$recommendations[] = __( 'Most useful reports for your business:', 'ict-platform' );
		if ( in_array( 'time_tracking', $use_cases, true ) ) {
			$recommendations[] = __( '- Time Entry Analysis (weekly payroll)', 'ict-platform' );
			$recommendations[] = __( '- Overtime Analysis (monthly)', 'ict-platform' );
		}
		if ( in_array( 'project_management', $use_cases, true ) ) {
			$recommendations[] = __( '- Project Summary (for clients)', 'ict-platform' );
			$recommendations[] = __( '- Resource Utilization (capacity planning)', 'ict-platform' );
		}
		if ( in_array( 'inventory', $use_cases, true ) ) {
			$recommendations[] = __( '- Inventory Status (weekly)', 'ict-platform' );
		}

		return implode( "\n", $recommendations );
	}

	/**
	 * Get getting started recommendations.
	 *
	 * @param array $company_info Company context.
	 * @return string Recommendations.
	 */
	private function get_getting_started_recommendations( $company_info ) {
		$size      = $company_info['size'];
		$use_cases = $company_info['use_cases'];

		$recommendations   = array();
		$recommendations[] = __( '**Your Personalized Getting Started Guide:**', 'ict-platform' );
		$recommendations[] = '';

		// First priority based on size
		$recommendations[] = __( '**Week 1 - Foundation:**', 'ict-platform' );
		if ( in_array( $size, array( 'solo', 'small' ), true ) ) {
			$recommendations[] = __( '1. Create your first project to test the workflow', 'ict-platform' );
			$recommendations[] = __( '2. Try logging a time entry', 'ict-platform' );
			$recommendations[] = __( '3. Test notifications to ensure they\'re working', 'ict-platform' );
		} else {
			$recommendations[] = __( '1. Invite your project managers and assign roles', 'ict-platform' );
			$recommendations[] = __( '2. Create a test project together', 'ict-platform' );
			$recommendations[] = __( '3. Have managers review the reporting features', 'ict-platform' );
		}
		$recommendations[] = '';

		$recommendations[] = __( '**Week 2 - Team Onboarding:**', 'ict-platform' );
		if ( in_array( $size, array( 'solo', 'small' ), true ) ) {
			$recommendations[] = __( '1. If you have technicians, set up their accounts', 'ict-platform' );
			$recommendations[] = __( '2. Show them the mobile time tracking feature', 'ict-platform' );
			$recommendations[] = __( '3. Create custom fields specific to your jobs', 'ict-platform' );
		} else {
			$recommendations[] = __( '1. Onboard technicians in small groups', 'ict-platform' );
			$recommendations[] = __( '2. Set up biometric login for mobile devices', 'ict-platform' );
			$recommendations[] = __( '3. Configure team notification preferences', 'ict-platform' );
		}
		$recommendations[] = '';

		// Use case specific
		$recommendations[] = __( '**Based on Your Use Cases:**', 'ict-platform' );
		if ( in_array( 'inventory', $use_cases, true ) ) {
			$recommendations[] = __( '- Import your inventory list (CSV supported)', 'ict-platform' );
			$recommendations[] = __( '- Set reorder points for critical items', 'ict-platform' );
		}
		if ( in_array( 'invoicing', $use_cases, true ) ) {
			$recommendations[] = __( '- Connect Zoho Books if not done already', 'ict-platform' );
			$recommendations[] = __( '- Set up your invoice template', 'ict-platform' );
		}
		if ( in_array( 'field_service', $use_cases, true ) ) {
			$recommendations[] = __( '- Test the mobile app with GPS tracking', 'ict-platform' );
			$recommendations[] = __( '- Try the offline mode in airplane mode', 'ict-platform' );
		}
		$recommendations[] = '';

		$recommendations[] = __( '**Need Help?** Use the AI Assistant anytime by clicking the button in the sidebar, or visit our documentation at docs.ictplatform.com', 'ict-platform' );

		return implode( "\n", $recommendations );
	}

	/**
	 * Get AI API key.
	 *
	 * @return string API key or empty string.
	 */
	private function get_ai_api_key() {
		$key = get_option( 'ict_ai_api_key', '' );
		if ( ! empty( $key ) ) {
			return ICT_Admin_Settings::decrypt( $key );
		}
		return '';
	}

	/**
	 * Get response from external AI provider.
	 *
	 * @param string $question User's question.
	 * @return string|WP_Error Response or error.
	 */
	private function get_external_ai_response( $question ) {
		$api_key      = $this->get_ai_api_key();
		$company_info = $this->get_company_context();

		// Build system context
		$system_prompt = $this->build_system_prompt( $company_info );

		if ( $this->ai_provider === 'openai' ) {
			return $this->call_openai_api( $system_prompt, $question, $api_key );
		} elseif ( $this->ai_provider === 'anthropic' ) {
			return $this->call_anthropic_api( $system_prompt, $question, $api_key );
		}

		return $this->get_builtin_response( $question );
	}

	/**
	 * Build system prompt for AI.
	 *
	 * @param array $company_info Company context.
	 * @return string System prompt.
	 */
	private function build_system_prompt( $company_info ) {
		$prompt  = "You are an AI assistant helping set up the ICT Platform, a WordPress plugin for ICT/electrical contracting business operations management.\n\n";
		$prompt .= "Company Context:\n";
		$prompt .= "- Company Name: {$company_info['name']}\n";
		$prompt .= "- Industry: {$company_info['industry']}\n";
		$prompt .= "- Company Size: {$company_info['size']}\n";
		$prompt .= '- Primary Use Cases: ' . implode( ', ', $company_info['use_cases'] ) . "\n\n";
		$prompt .= "Platform Features:\n";
		$prompt .= "- Zoho Integration (CRM, FSM, Books, People, Desk)\n";
		$prompt .= "- Microsoft Teams notifications\n";
		$prompt .= "- Email, SMS (Twilio), and Push notifications\n";
		$prompt .= "- Offline mode with data sync\n";
		$prompt .= "- Biometric authentication (WebAuthn)\n";
		$prompt .= "- Custom role management\n";
		$prompt .= "- Custom field builder\n";
		$prompt .= "- Advanced reporting with multiple export formats\n\n";
		$prompt .= 'Provide helpful, concise answers relevant to setting up and configuring this platform. ';
		$prompt .= 'Focus on practical advice and step-by-step guidance when appropriate. ';
		$prompt .= 'Format responses using markdown for better readability.';

		return $prompt;
	}

	/**
	 * Call OpenAI API.
	 *
	 * @param string $system_prompt System context.
	 * @param string $question User question.
	 * @param string $api_key API key.
	 * @return string|WP_Error Response or error.
	 */
	private function call_openai_api( $system_prompt, $question, $api_key ) {
		$response = wp_remote_post(
			$this->openai_endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-4o-mini',
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => $system_prompt,
							),
							array(
								'role'    => 'user',
								'content' => $question,
							),
						),
						'max_tokens'  => 1000,
						'temperature' => 0.7,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'openai_error', $body['error']['message'] );
		}

		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			return $body['choices'][0]['message']['content'];
		}

		return new WP_Error( 'invalid_response', __( 'Invalid response from AI', 'ict-platform' ) );
	}

	/**
	 * Call Anthropic API.
	 *
	 * @param string $system_prompt System context.
	 * @param string $question User question.
	 * @param string $api_key API key.
	 * @return string|WP_Error Response or error.
	 */
	private function call_anthropic_api( $system_prompt, $question, $api_key ) {
		$response = wp_remote_post(
			$this->anthropic_endpoint,
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-3-haiku-20240307',
						'max_tokens' => 1000,
						'system'     => $system_prompt,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $question,
							),
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'anthropic_error', $body['error']['message'] );
		}

		if ( isset( $body['content'][0]['text'] ) ) {
			return $body['content'][0]['text'];
		}

		return new WP_Error( 'invalid_response', __( 'Invalid response from AI', 'ict-platform' ) );
	}

	/**
	 * Get response from built-in knowledge base.
	 *
	 * @param string $question User question.
	 * @return string Response.
	 */
	private function get_builtin_response( $question ) {
		$question_lower = strtolower( $question );

		// Knowledge base - common questions and answers
		$knowledge_base = array(
			// Zoho questions
			'zoho'      => array(
				'keywords' => array( 'zoho', 'crm', 'fsm', 'books', 'people', 'desk', 'oauth', 'sync' ),
				'response' => __( "**Zoho Integration Help:**\n\nTo connect Zoho services:\n1. Go to [api-console.zoho.com](https://api-console.zoho.com)\n2. Create a 'Server-based Application'\n3. Add your redirect URI (shown in the wizard)\n4. Copy the Client ID and Client Secret\n\n**Which services do you need?**\n- **CRM**: Customer and project management\n- **FSM**: Field service scheduling\n- **Books**: Invoicing and inventory\n- **People**: Employee time tracking\n- **Desk**: Support tickets", 'ict-platform' ),
			),
			// Teams questions
			'teams'     => array(
				'keywords' => array( 'teams', 'microsoft', 'webhook', 'channel', 'notification' ),
				'response' => __( "**Microsoft Teams Setup:**\n\n**Quick Setup (Webhook):**\n1. In Teams, click '...' next to your channel\n2. Select 'Connectors' → 'Incoming Webhook'\n3. Name it 'ICT Platform' and create\n4. Copy the URL to the wizard\n\n**Full Integration (OAuth):**\nRequires Azure AD app registration. Best for interactive features and user-specific notifications.\n\nWebhook is recommended for most users - it takes less than 5 minutes!", 'ict-platform' ),
			),
			// Twilio/SMS questions
			'twilio'    => array(
				'keywords' => array( 'twilio', 'sms', 'text', 'message', 'phone' ),
				'response' => __( "**Twilio SMS Setup:**\n\n1. Sign up at [twilio.com](https://www.twilio.com/try-twilio)\n2. Get your Account SID and Auth Token from the Console\n3. Purchase or use a trial phone number\n4. Enter credentials in the wizard\n\n**Pricing:** ~$0.0075 per SMS in the US. Free trial includes $15 credit.\n\n**Best for:** Urgent alerts, dispatch notifications, schedule changes for field technicians.", 'ict-platform' ),
			),
			// Biometric questions
			'biometric' => array(
				'keywords' => array( 'biometric', 'fingerprint', 'face', 'touch', 'webauthn', 'fido' ),
				'response' => __( "**Biometric Authentication:**\n\nRequirements:\n- HTTPS (SSL certificate)\n- Compatible browser (Chrome, Firefox, Safari, Edge)\n- Device with biometric sensor or security key\n\n**How it works:**\n1. Users register their device (one-time setup)\n2. On login, they can use fingerprint/face instead of password\n3. Credentials are stored securely on their device\n\n**Supported methods:**\n- Touch ID / Face ID (Mac/iOS)\n- Windows Hello\n- Android fingerprint\n- Hardware security keys (YubiKey, etc.)", 'ict-platform' ),
			),
			// Offline questions
			'offline'   => array(
				'keywords' => array( 'offline', 'sync', 'internet', 'connection', 'field' ),
				'response' => __( "**Offline Mode:**\n\nHow it works:\n1. Data is cached locally on the device\n2. Changes are queued when offline\n3. Queue syncs automatically when connection returns\n\n**Conflict Resolution:**\n- **Server wins**: Safest - server data takes priority\n- **Client wins**: Local changes take priority\n- **Manual**: You review conflicts\n\n**Best practices:**\n- Use 'Server wins' for most setups\n- Keep queue size at 100 (default)\n- Sync interval of 30 seconds works well", 'ict-platform' ),
			),
			// Reporting questions
			'reporting' => array(
				'keywords' => array( 'report', 'export', 'pdf', 'excel', 'csv', 'schedule' ),
				'response' => __( "**Advanced Reporting:**\n\n**Available Reports:**\n- Project Summary\n- Time Entry Analysis\n- Resource Utilization\n- Inventory Status\n- Financial Summary\n- Technician Performance\n- Overtime Analysis\n\n**Export Formats:**\n- PDF (for clients/printing)\n- Excel (XLSX) for analysis\n- CSV for imports\n- JSON for developers\n\n**Scheduled Reports:**\nEnable to automatically generate and email reports on a schedule.", 'ict-platform' ),
			),
			// Custom fields questions
			'custom'    => array(
				'keywords' => array( 'custom', 'field', 'form', 'data' ),
				'response' => __( "**Custom Field Builder:**\n\n**Supported Field Types:**\nText, Number, Email, Phone, URL, Date, Time, DateTime, Select, Multi-select, Checkbox, Radio, Textarea, File Upload, Image, GPS Location, Signature, Formula, Lookup, Currency\n\n**Add fields to:**\n- Projects\n- Time Entries\n- Inventory Items\n- Purchase Orders\n- Users\n\n**Features:**\n- Validation rules\n- Field groups\n- Conditional display\n- Required fields", 'ict-platform' ),
			),
			// Role/permission questions
			'role'      => array(
				'keywords' => array( 'role', 'permission', 'access', 'user', 'capability' ),
				'response' => __( "**Role Management:**\n\n**Default Roles:**\n- **Administrator**: Full access\n- **Project Manager**: Manage projects, resources, reports\n- **Technician**: Log time, view assigned projects\n- **Inventory Manager**: Manage inventory, purchase orders\n- **Accountant**: Financial reports, invoicing\n- **Viewer**: Read-only access\n\n**Dynamic Permissions:**\nWhen enabled, users can have different access levels per project. Useful for contractors who work on specific projects only.", 'ict-platform' ),
			),
		);

		// Find best matching response
		$best_match = null;
		$best_score = 0;

		foreach ( $knowledge_base as $topic => $data ) {
			$score = 0;
			foreach ( $data['keywords'] as $keyword ) {
				if ( strpos( $question_lower, $keyword ) !== false ) {
					++$score;
				}
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_match = $data['response'];
			}
		}

		if ( $best_match ) {
			return $best_match;
		}

		// Default response
		return __( "I can help you with:\n\n- **Zoho Integration**: Setting up CRM, FSM, Books, People, Desk\n- **Microsoft Teams**: Webhook and OAuth configuration\n- **Notifications**: Email, SMS via Twilio, Push notifications\n- **Security**: Biometric auth, role management\n- **Features**: Offline mode, reporting, custom fields\n\nTry asking about a specific topic, or click the suggestion buttons for guided recommendations!\n\nFor detailed documentation, visit [docs.ictplatform.com](https://docs.ictplatform.com)", 'ict-platform' );
	}
}
