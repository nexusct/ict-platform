<?php

declare(strict_types=1);

namespace ICT_Platform\Util;

/**
 * Sync logging utility
 *
 * Handles logging of sync operations and queue management.
 *
 * @package ICT_Platform\Util
 * @since   2.0.0
 */
class SyncLogger {

	/**
	 * Log sync activity
	 *
	 * @param array<string, mixed> $data Log data
	 * @return int|false Log ID or false on failure
	 */
	public function log( array $data ): int|false {
		global $wpdb;

		$defaults = array(
			'entity_type'   => '',
			'entity_id'     => null,
			'direction'     => 'outbound',
			'zoho_service'  => '',
			'action'        => '',
			'status'        => 'success',
			'request_data'  => null,
			'response_data' => null,
			'error_message' => null,
			'duration_ms'   => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		// Encode JSON data
		if ( is_array( $data['request_data'] ) ) {
			$data['request_data'] = wp_json_encode( $data['request_data'] );
		}
		if ( is_array( $data['response_data'] ) ) {
			$data['response_data'] = wp_json_encode( $data['response_data'] );
		}

		$result = $wpdb->insert(
			ICT_SYNC_LOG_TABLE,
			$data,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Queue item for sync
	 *
	 * @param array<string, mixed> $data Queue data
	 * @return int|false Queue ID or false on failure
	 */
	public function queueSync( array $data ): int|false {
		global $wpdb;

		$defaults = array(
			'entity_type'  => '',
			'entity_id'    => 0,
			'action'       => 'update',
			'zoho_service' => '',
			'priority'     => 5,
			'payload'      => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Encode payload
		if ( is_array( $data['payload'] ) ) {
			$data['payload'] = wp_json_encode( $data['payload'] );
		}

		// Check if already queued
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . ICT_SYNC_QUEUE_TABLE . "
                WHERE entity_type = %s
                AND entity_id = %d
                AND status = 'pending'",
				$data['entity_type'],
				$data['entity_id']
			)
		);

		if ( $existing ) {
			// Update existing queue item
			$wpdb->update(
				ICT_SYNC_QUEUE_TABLE,
				array(
					'action'   => $data['action'],
					'priority' => $data['priority'],
					'payload'  => $data['payload'],
				),
				array( 'id' => $existing ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		// Insert new queue item
		$result = $wpdb->insert(
			ICT_SYNC_QUEUE_TABLE,
			$data,
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get sync logs
	 *
	 * @param array<string, mixed> $args Query arguments
	 * @return array<object> Sync logs
	 */
	public function getLogs( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'entity_type'  => null,
			'entity_id'    => null,
			'zoho_service' => null,
			'status'       => null,
			'direction'    => null,
			'limit'        => 50,
			'offset'       => 0,
			'orderby'      => 'synced_at',
			'order'        => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['entity_type'] ) {
			$where[]  = 'entity_type = %s';
			$values[] = $args['entity_type'];
		}

		if ( $args['entity_id'] ) {
			$where[]  = 'entity_id = %d';
			$values[] = $args['entity_id'];
		}

		if ( $args['zoho_service'] ) {
			$where[]  = 'zoho_service = %s';
			$values[] = $args['zoho_service'];
		}

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['direction'] ) {
			$where[]  = 'direction = %s';
			$values[] = $args['direction'];
		}

		$sql = 'SELECT * FROM ' . ICT_SYNC_LOG_TABLE . '
                WHERE ' . implode( ' AND ', $where ) . "
                ORDER BY {$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Get pending sync queue items
	 *
	 * @param int $limit Maximum items to retrieve
	 * @return array<object> Queue items
	 */
	public function getPendingQueue( int $limit = 20 ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_SYNC_QUEUE_TABLE . "
                WHERE status = 'pending'
                ORDER BY priority ASC, created_at ASC
                LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Mark queue item as processing
	 *
	 * @param int $queueId Queue item ID
	 * @return bool Success status
	 */
	public function markProcessing( int $queueId ): bool {
		global $wpdb;

		return (bool) $wpdb->update(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'status'   => 'processing',
				'attempts' => new \stdClass(),
			),
			array( 'id' => $queueId ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark queue item as completed
	 *
	 * @param int $queueId Queue item ID
	 * @return bool Success status
	 */
	public function markCompleted( int $queueId ): bool {
		global $wpdb;

		return (bool) $wpdb->update(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $queueId ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark queue item as failed
	 *
	 * @param int    $queueId      Queue item ID
	 * @param string $errorMessage Error message
	 * @return bool Success status
	 */
	public function markFailed( int $queueId, string $errorMessage ): bool {
		global $wpdb;

		return (bool) $wpdb->update(
			ICT_SYNC_QUEUE_TABLE,
			array(
				'status'        => 'failed',
				'error_message' => $errorMessage,
			),
			array( 'id' => $queueId ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
