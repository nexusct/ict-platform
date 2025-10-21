<?php
/**
 * Sync Engine
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Sync_Engine
 *
 * Handles bidirectional synchronization with Zoho services.
 */
class ICT_Sync_Engine {

	/**
	 * Process sync queue.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_sync_queue() {
		global $wpdb;

		// Get pending queue items (limit to prevent timeout)
		$queue_items = $wpdb->get_results(
			"SELECT * FROM " . ICT_SYNC_QUEUE_TABLE . "
			WHERE status = 'pending'
			AND attempts < max_attempts
			ORDER BY priority DESC, scheduled_at ASC
			LIMIT 20"
		);

		if ( empty( $queue_items ) ) {
			return;
		}

		foreach ( $queue_items as $item ) {
			$this->process_queue_item( $item );
		}
	}

	/**
	 * Process a single queue item.
	 *
	 * @since  1.0.0
	 * @param  object $item Queue item.
	 * @return void
	 */
	private function process_queue_item( $item ) {
		global $wpdb;

		// Mark as processing
		$wpdb->update(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'status'     => 'processing',
				'attempts'   => $item->attempts + 1,
			),
			array( 'id' => $item->id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		$start_time = microtime( true );
		$result     = $this->sync_entity( $item );
		$duration   = round( ( microtime( true ) - $start_time ) * 1000 );

		// Update queue status
		if ( $result['success'] ) {
			$wpdb->update(
				ICT_SYNC_QUEUE_TABLE,
				array(
					'status'       => 'completed',
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Check if we should retry
			$status = ( $item->attempts + 1 >= $item->max_attempts ) ? 'failed' : 'pending';

			$wpdb->update(
				ICT_SYNC_QUEUE_TABLE,
				array(
					'status'     => $status,
					'last_error' => $result['error'],
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// Log the sync attempt
		ICT_Helper::log_sync(
			array(
				'entity_type'   => $item->entity_type,
				'entity_id'     => $item->entity_id,
				'direction'     => 'outbound',
				'zoho_service'  => $item->zoho_service,
				'action'        => $item->action,
				'status'        => $result['success'] ? 'success' : 'error',
				'request_data'  => $item->payload,
				'response_data' => $result['data'] ?? null,
				'error_message' => $result['error'] ?? null,
				'duration_ms'   => $duration,
			)
		);
	}

	/**
	 * Sync an entity to Zoho.
	 *
	 * @since  1.0.0
	 * @param  object $item Queue item.
	 * @return array Result with success status and data/error.
	 */
	private function sync_entity( $item ) {
		// Get appropriate adapter
		$adapter_class = 'ICT_Zoho_' . ucfirst( $item->zoho_service ) . '_Adapter';

		if ( ! class_exists( $adapter_class ) ) {
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Adapter class %s not found', 'ict-platform' ), $adapter_class ),
			);
		}

		try {
			$adapter = new $adapter_class();
			$payload = json_decode( $item->payload, true );

			// Call appropriate method based on action
			switch ( $item->action ) {
				case 'create':
					$result = $adapter->create( $item->entity_type, $payload );
					break;
				case 'update':
					$result = $adapter->update( $item->entity_type, $item->entity_id, $payload );
					break;
				case 'delete':
					$result = $adapter->delete( $item->entity_type, $item->entity_id );
					break;
				default:
					return array(
						'success' => false,
						'error'   => sprintf( __( 'Unknown action: %s', 'ict-platform' ), $item->action ),
					);
			}

			return array(
				'success' => true,
				'data'    => $result,
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Trigger manual sync for an entity.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @param  string $service     Zoho service.
	 * @return bool True on success.
	 */
	public function trigger_sync( $entity_type, $entity_id, $service ) {
		return ICT_Helper::queue_sync(
			array(
				'entity_type'  => $entity_type,
				'entity_id'    => $entity_id,
				'action'       => 'update',
				'zoho_service' => $service,
				'priority'     => 1, // High priority for manual sync
			)
		);
	}
}
