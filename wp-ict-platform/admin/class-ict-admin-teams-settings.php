<?php
/**
 * Admin - Teams Settings
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Admin_Teams_Settings {
    public function register_settings() {
        add_settings_section(
            'ict_teams_section',
            __( 'Microsoft Teams Integration', 'ict-platform' ),
            function () {
                echo '<p>' . esc_html__( 'Configure an incoming webhook to receive alerts (e.g., low stock).', 'ict-platform' ) . '</p>';
            },
            'ict-settings'
        );

        register_setting( 'ict-settings', 'ict_teams_webhook_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ) );

        add_settings_field(
            'ict_teams_webhook_url',
            __( 'Teams Webhook URL', 'ict-platform' ),
            function () {
                $value = esc_attr( get_option( 'ict_teams_webhook_url', '' ) );
                echo '<input type="url" name="ict_teams_webhook_url" value="' . $value . '" class="regular-text" placeholder="https://outlook.office.com/webhook/..." />';
            },
            'ict-settings',
            'ict_teams_section'
        );
    }
}

