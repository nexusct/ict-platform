<?php
/**
 * Enhanced Sync Queue Processor
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Sync_Queue_Processor
 *
 * Enhanced processor for handling sync queue with batch processing and error recovery.
 */
class ICT_Sync_Queue_Processor {

	/**
	 * Maximum items to process per batch.
	 *
	 * @since  1.0.0
	 * @var    int
	 */
	protected $batch_size = 20;

	/**
	 * Maximum execution time (seconds).
	 *
	 * @since  1.0.0
	 * @var    int
	 */
	protected $max_execution_time = 30;

	/**
	 * Start time of current batch.
	 *
	 * @since  1.0.0
	 * @var    float
	 */
	protected $start_time;

	/**
	 * Process sync queue.
	 *
	 * @since 1.0.0
	 * @return array Processing results.
	 */
	public function process() {
		$this->start_time = microtime( true );

		$results = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
			'skipped'   => 0,
		);

		while ( $this->should_continue() ) {
			$items = $this->get_next_batch();

			if ( empty( $items ) ) {
				break;
			}

			foreach ( $items as $item ) {
				if ( ! $this->should_continue() ) {
					break;
				}

				$result = $this->process_item( $item );

				++$results['processed'];

				if ( $result['success'] ) {
					++$results['succeeded'];
				} elseif ( $result['skipped'] ) {
					++$results['skipped'];
				} else {
					++$results['failed'];
				}
			}
		}

		// Update metrics
		$this->update_metrics( $results );

		return $results;
	}

	/**
	 * Check if processing should continue.
	 *
	 * @since  1.0.0
	 * @return bool True if should continue.
	 */
	protected function should_continue() {
		// Check execution time
		$elapsed = microtime( true ) - $this->start_time;

		return $elapsed < $this->max_execution_time;
	}

	/**
	 * Get next batch of queue items.
	 *
	 * @since  1.0.0
	 * @return array Queue items.
	 */
	protected function get_next_batch() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_SYNC_QUEUE_TABLE . "
				WHERE status = 'pending'
				AND attempts < max_attempts
				AND (scheduled_at IS NULL OR scheduled_at <= NOW())
				ORDER BY priority DESC, scheduled_at ASC, id ASC
				LIMIT %d",
				$this->batch_size
			)
		);
	}

	/**
	 * Process a single queue item.
	 *
	 * @since  1.0.0
	 * @param  object $item Queue item.
	 * @return array Result.
	 */
	protected function process_item( $item ) {
		global $wpdb;

		// Mark as processing
		$wpdb->update(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'status'     => 'processing',
				'attempts'   => $item->attempts + 1,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		// Check if entity still exists
		if ( ! $this->entity_exists( $item->entity_type, $item->entity_id ) ) {
			$wpdb->update(
				ICT_SYNC_QUEUE_TABLE,
				array(
					'status'       => 'skipped',
					'last_error'   => 'Entity no longer exists',
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return array(
				'success' => false,
				'skipped' => true,
			);
		}

		// Process sync
		$start_time = microtime( true );

		try {
			$result   = $this->sync_entity( $item );
			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			// Update queue status
			$wpdb->update(
				ICT_SYNC_QUEUE_TABLE,
				array(
					'status'       => 'completed',
					'processed_at' => current_time( 'mysql' ),
					'last_error'   => null,
				),
				array( 'id' => $item->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Log success
			ICT_Helper::log_sync(
				array(
					'entity_type'   => $item->entity_type,
					'entity_id'     => $item->entity_id,
					'direction'     => 'outbound',
					'zoho_service'  => $item->zoho_service,
					'action'        => $item->action,
					'status'        => 'success',
					'request_data'  => $item->payload,
					'response_data' => $result,
					'duration_ms'   => $duration,
				)
			);

			return array(
				'success' => true,
				'result'  => $result,
			);

		} catch ( Exception $e ) {
			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			// Determine if should retry
			$should_retry = $this->should_retry( $item, $e );
			$new_status   = $should_retry ? 'pending' : 'failed';

			// Calculate backoff delay
			$delay = $this->calculate_backoff_delay( $item->attempts );

			$wpdb->update(
				ICT_SYNC_QUEUE_TABLE,
				array(
					'status'       => $new_status,
					'last_error'   => substr( $e->getMessage(), 0, 500 ),
					'scheduled_at' => $should_retry ? date( 'Y-m-d H:i:s', time() + $delay ) : null,
				),
				array( 'id' => $item->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Log error
			ICT_Helper::log_sync(
				array(
					'entity_type'   => $item->entity_type,
					'entity_id'     => $item->entity_id,
					'direction'     => 'outbound',
					'zoho_service'  => $item->zoho_service,
					'action'        => $item->action,
					'status'        => 'error',
					'request_data'  => $item->payload,
					'error_message' => $e->getMessage(),
					'duration_ms'   => $duration,
				)
			);

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Sync entity to Zoho.
	 *
	 * @since  1.0.0
	 * @param  object $item Queue item.
	 * @return mixed Sync result.
	 * @throws Exception On error.
	 */
	protected function sync_entity( $item ) {
		// Get appropriate adapter
		$adapter_class = 'ICT_Zoho_' . ucfirst( $item->zoho_service ) . '_Adapter';

		if ( ! class_exists( $adapter_class ) ) {
			throw new Exception(
				sprintf( __( 'Adapter class %s not found', 'ict-platform' ), $adapter_class )
			);
		}

		$adapter = new $adapter_class();
		$payload = json_decode( $item->payload, true );

		// Get entity data
		$entity_data = $this->get_entity_data( $item->entity_type, $item->entity_id );

		if ( empty( $entity_data ) ) {
			throw new Exception( __( 'Entity data not found', 'ict-platform' ) );
		}

		// Merge payload with entity data
		$data = array_merge( $entity_data, $payload ?? array() );

		// Call appropriate method
		switch ( $item->action ) {
			case 'create':
				return $adapter->create( $item->entity_type, $data );
			case 'update':
				return $adapter->update( $item->entity_type, $item->entity_id, $data );
			case 'delete':
				return $adapter->delete( $item->entity_type, $item->entity_id );
			default:
				throw new Exception(
					sprintf( __( 'Unknown action: %s', 'ict-platform' ), $item->action )
				);
		}
	}

	/**
	 * Check if entity exists.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @return bool True if exists.
	 */
	protected function entity_exists( $entity_type, $entity_id ) {
		global $wpdb;

		$tables = array(
			'project'          => ICT_PROJECTS_TABLE,
			'time_entry'       => ICT_TIME_ENTRIES_TABLE,
			'inventory_item'   => ICT_INVENTORY_ITEMS_TABLE,
			'purchase_order'   => ICT_PURCHASE_ORDERS_TABLE,
			'project_resource' => ICT_PROJECT_RESOURCES_TABLE,
		);

		if ( ! isset( $tables[ $entity_type ] ) ) {
			return false;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables[$entity_type]} WHERE id = %d",
				$entity_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get entity data.
	 *
	 * @since  1.0.0
	 * @param  string $entity_type Entity type.
	 * @param  int    $entity_id   Entity ID.
	 * @return array Entity data.
	 */
	protected function get_entity_data( $entity_type, $entity_id ) {
		global $wpdb;

		$tables = array(
			'project'          => ICT_PROJECTS_TABLE,
			'time_entry'       => ICT_TIME_ENTRIES_TABLE,
			'inventory_item'   => ICT_INVENTORY_ITEMS_TABLE,
			'purchase_order'   => ICT_PURCHASE_ORDERS_TABLE,
			'project_resource' => ICT_PROJECT_RESOURCES_TABLE,
		);

		if ( ! isset( $tables[ $entity_type ] ) ) {
			return array();
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tables[$entity_type]} WHERE id = %d",
				$entity_id
			),
			ARRAY_A
		);

		return $row ?? array();
	}

	/**
	 * Determine if item should be retried.
	 *
	 * @since  1.0.0
	 * @param  object    $item Queue item.
	 * @param  Exception $e    Exception thrown.
	 * @return bool True if should retry.
	 */
	protected function should_retry( $item, $e ) {
		// Check if attempts remaining
		if ( $item->attempts >= $item->max_attempts ) {
			return false;
		}

		// Don't retry certain errors
		$non_retryable_errors = array(
			'not found',
			'not authenticated',
			'invalid credentials',
			'permission denied',
		);

		$error_message = strtolower( $e->getMessage() );

		foreach ( $non_retryable_errors as $error ) {
			if ( strpos( $error_message, $error ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Calculate exponential backoff delay.
	 *
	 * @since  1.0.0
	 * @param  int $attempts Number of attempts.
	 * @return int Delay in seconds.
	 */
	protected function calculate_backoff_delay( $attempts ) {
		// Exponential backoff: 30s, 60s, 120s, 240s
		$base_delay = 30;
		$max_delay  = 240;

		$delay = $base_delay * pow( 2, $attempts - 1 );

		return min( $delay, $max_delay );
	}

	/**
	 * Update processing metrics.
	 *
	 * @since  1.0.0
	 * @param  array $results Processing results.
	 * @return void
	 */
	protected function update_metrics( $results ) {
		// Store metrics in transient
		$metrics = get_transient( 'ict_sync_metrics' );

		if ( ! $metrics ) {
			$metrics = array(
				'total_processed' => 0,
				'total_succeeded' => 0,
				'total_failed'    => 0,
				'last_run'        => current_time( 'mysql' ),
			);
		}

		$metrics['total_processed'] += $results['processed'];
		$metrics['total_succeeded'] += $results['succeeded'];
		$metrics['total_failed']    += $results['failed'];
		$metrics['last_run']         = current_time( 'mysql' );

		set_transient( 'ict_sync_metrics', $metrics, DAY_IN_SECONDS );
	}

	/**
	 * Get pending queue count.
	 *
	 * @since  1.0.0
	 * @return int Pending count.
	 */
	public function get_pending_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . ICT_SYNC_QUEUE_TABLE . "
			WHERE status = 'pending' AND attempts < max_attempts"
		);
	}

	/**
	 * Clear failed queue items.
	 *
	 * @since  1.0.0
	 * @return int Number of items cleared.
	 */
	public function clear_failed() {
		global $wpdb;

		return $wpdb->delete(
			ICT_SYNC_QUEUE_TABLE,
			array( 'status' => 'failed' ),
			array( '%s' )
		);
	}

	/**
	 * Retry failed queue items.
	 *
	 * @since  1.0.0
	 * @return int Number of items reset.
	 */
	public function retry_failed() {
		global $wpdb;

		return $wpdb->update(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'status'       => 'pending',
				'attempts'     => 0,
				'scheduled_at' => current_time( 'mysql' ),
			),
			array( 'status' => 'failed' ),
			array( '%s', '%d', '%s' ),
			array( '%s' )
		);
	}
}
