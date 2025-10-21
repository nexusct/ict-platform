<?php
/**
 * Zoho API Rate Limiter
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Zoho_Rate_Limiter
 *
 * Manages API rate limiting to prevent exceeding Zoho's limits.
 */
class ICT_Zoho_Rate_Limiter {

	/**
	 * Service name.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $service;

	/**
	 * Rate limit (requests per minute).
	 *
	 * @since  1.0.0
	 * @var    int
	 */
	protected $rate_limit;

	/**
	 * Transient key for rate tracking.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $transient_key;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param string $service Service name.
	 */
	public function __construct( $service ) {
		$this->service       = $service;
		$this->rate_limit    = get_option( 'ict_sync_rate_limit', 60 );
		$this->transient_key = "ict_zoho_{$service}_rate_count";
	}

	/**
	 * Check if request is allowed under rate limit.
	 *
	 * @since  1.0.0
	 * @return bool True if allowed.
	 */
	public function check() {
		$count = get_transient( $this->transient_key );

		if ( false === $count ) {
			// No requests in current window
			return true;
		}

		return $count < $this->rate_limit;
	}

	/**
	 * Record a request.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function record() {
		$count = get_transient( $this->transient_key );

		if ( false === $count ) {
			// Start new window (1 minute)
			set_transient( $this->transient_key, 1, MINUTE_IN_SECONDS );
		} else {
			// Increment counter
			set_transient( $this->transient_key, $count + 1, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Get current request count.
	 *
	 * @since  1.0.0
	 * @return int Request count.
	 */
	public function get_count() {
		$count = get_transient( $this->transient_key );
		return false === $count ? 0 : (int) $count;
	}

	/**
	 * Get remaining requests in current window.
	 *
	 * @since  1.0.0
	 * @return int Remaining requests.
	 */
	public function get_remaining() {
		return max( 0, $this->rate_limit - $this->get_count() );
	}

	/**
	 * Reset rate counter.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function reset() {
		delete_transient( $this->transient_key );
	}

	/**
	 * Get time until rate limit resets.
	 *
	 * @since  1.0.0
	 * @return int Seconds until reset.
	 */
	public function get_reset_time() {
		$timeout = get_option( '_transient_timeout_' . $this->transient_key );

		if ( ! $timeout ) {
			return 0;
		}

		return max( 0, $timeout - time() );
	}
}
