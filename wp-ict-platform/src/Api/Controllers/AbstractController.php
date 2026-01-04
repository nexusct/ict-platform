<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use ICT_Platform\Api\Router;
use ICT_Platform\Util\Helper;
use ICT_Platform\Util\Cache;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Abstract REST API Controller
 *
 * Base class for all REST API controllers with common functionality.
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
abstract class AbstractController extends WP_REST_Controller {

	/**
	 * API namespace
	 */
	protected string $namespace = Router::NAMESPACE;

	/**
	 * Helper utility instance
	 */
	protected Helper $helper;

	/**
	 * Cache instance
	 */
	protected Cache $cache;

	/**
	 * Constructor
	 *
	 * @param Helper $helper Helper utility instance
	 * @param Cache  $cache  Cache instance
	 */
	public function __construct( Helper $helper, Cache $cache ) {
		$this->helper = $helper;
		$this->cache  = $cache;
	}

	/**
	 * Register routes
	 */
	abstract public function registerRoutes(): void;

	/**
	 * Create a success response
	 *
	 * @param mixed $data    Response data
	 * @param int   $status  HTTP status code
	 * @return WP_REST_Response
	 */
	protected function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Create an error response
	 *
	 * @param string $code    Error code
	 * @param string $message Error message
	 * @param int    $status  HTTP status code
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Create a paginated response
	 *
	 * @param array<mixed> $items      Items to return
	 * @param int          $total      Total items count
	 * @param int          $page       Current page
	 * @param int          $perPage    Items per page
	 * @return WP_REST_Response
	 */
	protected function paginated( array $items, int $total, int $page, int $perPage ): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $items,
				'meta'    => array(
					'total'       => $total,
					'page'        => $page,
					'per_page'    => $perPage,
					'total_pages' => (int) ceil( $total / $perPage ),
				),
			),
			200
		);

		// Add pagination headers
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) ceil( $total / $perPage ) );

		return $response;
	}

	/**
	 * Validate request parameters
	 *
	 * @param WP_REST_Request                     $request Request object
	 * @param array<string, array<string, mixed>> $rules   Validation rules
	 * @return WP_Error|true Returns WP_Error on failure, true on success
	 */
	protected function validate( WP_REST_Request $request, array $rules ): WP_Error|bool {
		foreach ( $rules as $field => $fieldRules ) {
			$value = $request->get_param( $field );

			if ( ! empty( $fieldRules['required'] ) && empty( $value ) ) {
				return $this->error(
					'missing_required_field',
					sprintf( __( 'The %s field is required.', 'ict-platform' ), $field ),
					400
				);
			}

			if ( ! empty( $value ) ) {
				if ( ! empty( $fieldRules['type'] ) ) {
					$valid = match ( $fieldRules['type'] ) {
						'int', 'integer' => is_numeric( $value ),
						'string'         => is_string( $value ),
						'bool', 'boolean' => is_bool( $value ) || in_array( $value, array( 'true', 'false', '0', '1' ), true ),
						'array'          => is_array( $value ),
						'email'          => is_email( $value ),
						default          => true,
					};

					if ( ! $valid ) {
						return $this->error(
							'invalid_field_type',
							sprintf( __( 'The %1$s field must be a %2$s.', 'ict-platform' ), $field, $fieldRules['type'] ),
							400
						);
					}
				}

				if ( ! empty( $fieldRules['min'] ) && strlen( (string) $value ) < $fieldRules['min'] ) {
					return $this->error(
						'field_too_short',
						sprintf( __( 'The %1$s field must be at least %2$d characters.', 'ict-platform' ), $field, $fieldRules['min'] ),
						400
					);
				}

				if ( ! empty( $fieldRules['max'] ) && strlen( (string) $value ) > $fieldRules['max'] ) {
					return $this->error(
						'field_too_long',
						sprintf( __( 'The %1$s field must be at most %2$d characters.', 'ict-platform' ), $field, $fieldRules['max'] ),
						400
					);
				}

				if ( ! empty( $fieldRules['enum'] ) && ! in_array( $value, $fieldRules['enum'], true ) ) {
					return $this->error(
						'invalid_field_value',
						sprintf( __( 'The %1$s field must be one of: %2$s.', 'ict-platform' ), $field, implode( ', ', $fieldRules['enum'] ) ),
						400
					);
				}
			}
		}

		return true;
	}

	/**
	 * Get pagination parameters from request
	 *
	 * @param WP_REST_Request $request Request object
	 * @return array{page: int, per_page: int, offset: int}
	 */
	protected function getPaginationParams( WP_REST_Request $request ): array {
		$page    = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$perPage = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$offset  = ( $page - 1 ) * $perPage;

		return array(
			'page'     => $page,
			'per_page' => $perPage,
			'offset'   => $offset,
		);
	}

	/**
	 * Check if user can manage ICT projects
	 *
	 * @return bool
	 */
	protected function canManageProjects(): bool {
		// In Divi Visual Builder context, use elevated permissions
		if ( Router::isDiviBuilderRequest() ) {
			return current_user_can( 'edit_posts' );
		}

		return current_user_can( 'manage_ict_projects' );
	}

	/**
	 * Check if user can view ICT projects
	 *
	 * @return bool
	 */
	protected function canViewProjects(): bool {
		if ( Router::isDiviBuilderRequest() ) {
			return current_user_can( 'edit_posts' );
		}

		return current_user_can( 'manage_ict_projects' ) || current_user_can( 'view_ict_projects' );
	}

	/**
	 * Check if user can manage ICT inventory
	 *
	 * @return bool
	 */
	protected function canManageInventory(): bool {
		return current_user_can( 'manage_ict_inventory' );
	}

	/**
	 * Check if user can approve time entries
	 *
	 * @return bool
	 */
	protected function canApproveTime(): bool {
		return current_user_can( 'approve_ict_time_entries' );
	}

	/**
	 * Check if user can manage sync
	 *
	 * @return bool
	 */
	protected function canManageSync(): bool {
		return current_user_can( 'manage_ict_sync' );
	}

	/**
	 * Check if user can view reports
	 *
	 * @return bool
	 */
	protected function canViewReports(): bool {
		return current_user_can( 'view_ict_reports' );
	}

	/**
	 * Get the current user ID
	 *
	 * @return int
	 */
	protected function getCurrentUserId(): int {
		return get_current_user_id();
	}

	/**
	 * Sanitize input data
	 *
	 * @param array<string, mixed>  $data  Input data
	 * @param array<string, string> $types Field types mapping
	 * @return array<string, mixed> Sanitized data
	 */
	protected function sanitize( array $data, array $types ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$type = $types[ $key ] ?? 'string';

			$sanitized[ $key ] = match ( $type ) {
				'int', 'integer'  => (int) $value,
				'float', 'double' => (float) $value,
				'bool', 'boolean' => filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
				'email'           => sanitize_email( $value ),
				'url'             => esc_url_raw( $value ),
				'textarea'        => sanitize_textarea_field( $value ),
				'html'            => wp_kses_post( $value ),
				default           => sanitize_text_field( $value ),
			};
		}

		return $sanitized;
	}
}
