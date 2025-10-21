<?php
/**
 * Helper utility functions
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Helper
 *
 * Provides utility functions used throughout the plugin.
 */
class ICT_Helper {

	/**
	 * Format currency value.
	 *
	 * @since  1.0.0
	 * @param  float  $amount   The amount to format.
	 * @param  string $currency Currency code (default: USD).
	 * @return string Formatted currency string.
	 */
	public static function format_currency( $amount, $currency = null ) {
		if ( is_null( $currency ) ) {
			$currency = get_option( 'ict_currency', 'USD' );
		}

		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'AUD' => 'A$',
			'CAD' => 'C$',
		);

		$symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';

		return $symbol . number_format( (float) $amount, 2, '.', ',' );
	}

	/**
	 * Calculate hours between two timestamps.
	 *
	 * @since  1.0.0
	 * @param  string $start Start datetime.
	 * @param  string $end   End datetime.
	 * @return float Hours difference.
	 */
	public static function calculate_hours( $start, $end ) {
		$start_time = strtotime( $start );
		$end_time   = strtotime( $end );

		if ( ! $start_time || ! $end_time || $end_time < $start_time ) {
			return 0;
		}

		$diff_seconds = $end_time - $start_time;
		return round( $diff_seconds / 3600, 2 );
	}

	/**
	 * Round time to nearest interval.
	 *
	 * @since  1.0.0
	 * @param  string $time     Time to round.
	 * @param  int    $interval Minutes interval (default: 15).
	 * @return string Rounded time.
	 */
	public static function round_time( $time, $interval = 15 ) {
		$timestamp = strtotime( $time );
		$minutes   = date( 'i', $timestamp );
		$hours     = date( 'H', $timestamp );

		$rounded_minutes = round( $minutes / $interval ) * $interval;

		if ( $rounded_minutes >= 60 ) {
			$hours++;
			$rounded_minutes = 0;
		}

		return date( 'Y-m-d', $timestamp ) . ' ' . sprintf( '%02d:%02d:00', $hours, $rounded_minutes );
	}

	/**
	 * Check if time entry is overtime.
	 *
	 * @since  1.0.0
	 * @param  int   $technician_id Technician user ID.
	 * @param  float $hours         Hours worked.
	 * @param  string $date         Date to check.
	 * @return bool True if overtime.
	 */
	public static function is_overtime( $technician_id, $hours, $date ) {
		global $wpdb;

		$threshold = get_option( 'ict_overtime_threshold', 8 );

		// Get total hours for the day
		$total_hours = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total_hours) FROM " . ICT_TIME_ENTRIES_TABLE . "
				WHERE technician_id = %d
				AND DATE(clock_in) = %s",
				$technician_id,
				$date
			)
		);

		$total_hours = (float) $total_hours + $hours;

		return $total_hours > $threshold;
	}

	/**
	 * Sanitize sync status.
	 *
	 * @since  1.0.0
	 * @param  string $status Status value.
	 * @return string Sanitized status.
	 */
	public static function sanitize_sync_status( $status ) {
		$allowed_statuses = array( 'pending', 'syncing', 'synced', 'error', 'conflict' );
		return in_array( $status, $allowed_statuses, true ) ? $status : 'pending';
	}

	/**
	 * Generate unique project number.
	 *
	 * @since  1.0.0
	 * @return string Project number.
	 */
	public static function generate_project_number() {
		$prefix = get_option( 'ict_project_number_prefix', 'PRJ' );
		$year   = date( 'Y' );

		global $wpdb;
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM " . ICT_PROJECTS_TABLE . "
			WHERE YEAR(created_at) = " . $year
		);

		$number = str_pad( $count + 1, 4, '0', STR_PAD_LEFT );

		return $prefix . '-' . $year . '-' . $number;
	}

	/**
	 * Generate unique PO number.
	 *
	 * @since  1.0.0
	 * @return string PO number.
	 */
	public static function generate_po_number() {
		$prefix = get_option( 'ict_po_number_prefix', 'PO' );
		$year   = date( 'Y' );

		global $wpdb;
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM " . ICT_PURCHASE_ORDERS_TABLE . "
			WHERE YEAR(created_at) = " . $year
		);

		$number = str_pad( $count + 1, 4, '0', STR_PAD_LEFT );

		return $prefix . '-' . $year . '-' . $number;
	}

	/**
	 * Log sync activity.
	 *
	 * @since  1.0.0
	 * @param  array $data Log data.
	 * @return int|false Log ID or false on failure.
	 */
	public static function log_sync( $data ) {
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

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Queue item for sync.
	 *
	 * @since  1.0.0
	 * @param  array $data Queue data.
	 * @return int|false Queue ID or false on failure.
	 */
	public static function queue_sync( $data ) {
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
				"SELECT id FROM " . ICT_SYNC_QUEUE_TABLE . "
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
			return $existing;
		}

		// Insert new queue item
		$result = $wpdb->insert(
			ICT_SYNC_QUEUE_TABLE,
			$data,
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Sanitize and validate coordinates.
	 *
	 * @since  1.0.0
	 * @param  mixed $coords Coordinates (lat,lng string or array).
	 * @return string|null Sanitized coordinates or null.
	 */
	public static function sanitize_coordinates( $coords ) {
		if ( is_string( $coords ) ) {
			$coords = explode( ',', $coords );
		}

		if ( ! is_array( $coords ) || count( $coords ) !== 2 ) {
			return null;
		}

		$lat = (float) trim( $coords[0] );
		$lng = (float) trim( $coords[1] );

		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return null;
		}

		return $lat . ',' . $lng;
	}

	/**
	 * Get user's full name.
	 *
	 * @since  1.0.0
	 * @param  int $user_id User ID.
	 * @return string Full name or username.
	 */
	public static function get_user_display_name( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return __( 'Unknown User', 'ict-platform' );
		}

		$first_name = get_user_meta( $user_id, 'first_name', true );
		$last_name  = get_user_meta( $user_id, 'last_name', true );

		if ( $first_name || $last_name ) {
			return trim( $first_name . ' ' . $last_name );
		}

		return $user->display_name;
	}
}
