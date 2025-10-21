<?php
/**
 * Integration Manager
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Integration_Manager
 *
 * Manages Zoho service integrations.
 */
class ICT_Integration_Manager {

	/**
	 * Available Zoho services.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	private $services = array( 'crm', 'fsm', 'books', 'people', 'desk' );

	/**
	 * Get integration status for all services.
	 *
	 * @since  1.0.0
	 * @return array Service connection status.
	 */
	public function get_integration_status() {
		$status = array();

		foreach ( $this->services as $service ) {
			$status[ $service ] = array(
				'connected'    => $this->is_service_connected( $service ),
				'last_sync'    => get_option( "ict_zoho_{$service}_last_sync" ),
				'sync_status'  => get_option( "ict_zoho_{$service}_sync_status", 'idle' ),
				'error_count'  => $this->get_service_error_count( $service ),
			);
		}

		return $status;
	}

	/**
	 * Check if a service is connected.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @return bool True if connected.
	 */
	public function is_service_connected( $service ) {
		$access_token = get_option( "ict_zoho_{$service}_access_token" );
		return ! empty( $access_token );
	}

	/**
	 * Get error count for a service.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @return int Error count.
	 */
	private function get_service_error_count( $service ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . ICT_SYNC_LOG_TABLE . "
				WHERE zoho_service = %s
				AND status = 'error'
				AND synced_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
				$service
			)
		);

		return (int) $count;
	}

	/**
	 * Test connection to a Zoho service.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @return array Test result.
	 */
	public function test_connection( $service ) {
		if ( ! in_array( $service, $this->services, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid service', 'ict-platform' ),
			);
		}

		// Implementation will be in service-specific adapters
		$adapter_class = 'ICT_Zoho_' . ucfirst( $service ) . '_Adapter';

		if ( ! class_exists( $adapter_class ) ) {
			return array(
				'success' => false,
				'message' => __( 'Service adapter not found', 'ict-platform' ),
			);
		}

		$adapter = new $adapter_class();
		return $adapter->test_connection();
	}

	/**
	 * Disconnect a service.
	 *
	 * @since  1.0.0
	 * @param  string $service Service name.
	 * @return bool True on success.
	 */
	public function disconnect_service( $service ) {
		if ( ! in_array( $service, $this->services, true ) ) {
			return false;
		}

		delete_option( "ict_zoho_{$service}_access_token" );
		delete_option( "ict_zoho_{$service}_refresh_token" );
		delete_option( "ict_zoho_{$service}_token_expires" );
		delete_option( "ict_zoho_{$service}_last_sync" );

		return true;
	}
}
