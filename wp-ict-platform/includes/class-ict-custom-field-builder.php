<?php
/**
 * Custom Field Builder
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ICT_Custom_Field_Builder
 *
 * Handles custom field definitions and values for entities.
 */
class ICT_Custom_Field_Builder {

	/**
	 * Singleton instance.
	 *
	 * @var ICT_Custom_Field_Builder
	 */
	private static $instance = null;

	/**
	 * Supported entity types.
	 *
	 * @var array
	 */
	private $entity_types = array(
		'project',
		'time_entry',
		'inventory_item',
		'purchase_order',
		'user',
		'task',
	);

	/**
	 * Supported field types.
	 *
	 * @var array
	 */
	private $field_types = array(
		'text'        => 'Text',
		'textarea'    => 'Text Area',
		'number'      => 'Number',
		'decimal'     => 'Decimal',
		'currency'    => 'Currency',
		'email'       => 'Email',
		'phone'       => 'Phone',
		'url'         => 'URL',
		'date'        => 'Date',
		'datetime'    => 'Date & Time',
		'time'        => 'Time',
		'select'      => 'Dropdown Select',
		'multiselect' => 'Multi-Select',
		'checkbox'    => 'Checkbox',
		'radio'       => 'Radio Buttons',
		'file'        => 'File Upload',
		'image'       => 'Image Upload',
		'color'       => 'Color Picker',
		'user'        => 'User Select',
		'project'     => 'Project Select',
		'formula'     => 'Calculated Field',
	);

	/**
	 * Get singleton instance.
	 *
	 * @since  1.1.0
	 * @return ICT_Custom_Field_Builder
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Initialize the custom field builder.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function init() {
		// Add any initialization hooks here
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_routes() {
		$this->register_endpoints();
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_endpoints() {
		// Field definitions
		register_rest_route(
			'ict/v1',
			'/custom-fields',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_field_definitions' ),
				'permission_callback' => array( $this, 'can_view_fields' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_field' ),
				'permission_callback' => array( $this, 'can_manage_fields' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_field' ),
				'permission_callback' => array( $this, 'can_view_fields' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_field' ),
				'permission_callback' => array( $this, 'can_manage_fields' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_field' ),
				'permission_callback' => array( $this, 'can_manage_fields' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_fields' ),
				'permission_callback' => array( $this, 'can_manage_fields' ),
			)
		);

		// Field values
		register_rest_route(
			'ict/v1',
			'/custom-fields/values/(?P<entity_type>[a-z_]+)/(?P<entity_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_field_values' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields/values/(?P<entity_type>[a-z_]+)/(?P<entity_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_field_values' ),
				'permission_callback' => '__return_true',
			)
		);

		// Field types reference
		register_rest_route(
			'ict/v1',
			'/custom-fields/types',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_field_types' ),
				'permission_callback' => '__return_true',
			)
		);

		// Entity types reference
		register_rest_route(
			'ict/v1',
			'/custom-fields/entity-types',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_entity_types' ),
				'permission_callback' => '__return_true',
			)
		);

		// Field groups
		register_rest_route(
			'ict/v1',
			'/custom-fields/groups',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_field_groups' ),
				'permission_callback' => array( $this, 'can_view_fields' ),
			)
		);

		register_rest_route(
			'ict/v1',
			'/custom-fields/groups',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_field_group' ),
				'permission_callback' => array( $this, 'can_manage_fields' ),
			)
		);
	}

	/**
	 * Check if user can view fields.
	 *
	 * @since  1.1.0
	 * @return bool True if can view.
	 */
	public function can_view_fields() {
		return is_user_logged_in();
	}

	/**
	 * Check if user can manage fields.
	 *
	 * @since  1.1.0
	 * @return bool True if can manage.
	 */
	public function can_manage_fields() {
		return current_user_can( 'manage_custom_fields' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get field definitions.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_field_definitions( $request ) {
		global $wpdb;

		$entity_type = $request->get_param( 'entity_type' );
		$table       = $wpdb->prefix . 'ict_custom_fields';

		$where  = '1=1';
		$values = array();

		if ( $entity_type && in_array( $entity_type, $this->entity_types, true ) ) {
			$where   .= ' AND entity_type = %s';
			$values[] = $entity_type;
		}

		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY field_group, sort_order ASC";

		if ( ! empty( $values ) ) {
			$fields = $wpdb->get_results( $wpdb->prepare( $query, ...$values ), ARRAY_A );
		} else {
			$fields = $wpdb->get_results( $query, ARRAY_A );
		}

		// Decode JSON settings
		foreach ( $fields as &$field ) {
			$field['settings']         = json_decode( $field['settings'], true ) ?? array();
			$field['options']          = json_decode( $field['options'], true ) ?? array();
			$field['validation_rules'] = json_decode( $field['validation_rules'], true ) ?? array();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'fields'  => $fields,
			),
			200
		);
	}

	/**
	 * Get single field.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_field( $request ) {
		global $wpdb;

		$field_id = $request->get_param( 'id' );
		$table    = $wpdb->prefix . 'ict_custom_fields';

		$field = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $field_id ),
			ARRAY_A
		);

		if ( ! $field ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Field not found',
				),
				404
			);
		}

		$field['settings']         = json_decode( $field['settings'], true ) ?? array();
		$field['options']          = json_decode( $field['options'], true ) ?? array();
		$field['validation_rules'] = json_decode( $field['validation_rules'], true ) ?? array();

		return new WP_REST_Response(
			array(
				'success' => true,
				'field'   => $field,
			),
			200
		);
	}

	/**
	 * Create custom field.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function create_field( $request ) {
		global $wpdb;

		$params = $request->get_json_params();

		// Validate required fields
		$required = array( 'entity_type', 'field_name', 'field_label', 'field_type' );
		foreach ( $required as $req ) {
			if ( empty( $params[ $req ] ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => "Missing required field: {$req}",
					),
					400
				);
			}
		}

		// Validate entity type
		if ( ! in_array( $params['entity_type'], $this->entity_types, true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid entity type',
				),
				400
			);
		}

		// Validate field type
		if ( ! isset( $this->field_types[ $params['field_type'] ] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid field type',
				),
				400
			);
		}

		// Generate unique field key
		$field_key = sanitize_key( $params['field_name'] );
		$table     = $wpdb->prefix . 'ict_custom_fields';

		// Check for duplicate key
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE entity_type = %s AND field_key = %s",
				$params['entity_type'],
				$field_key
			)
		);

		if ( $existing ) {
			$field_key .= '_' . uniqid();
		}

		// Get next sort order
		$max_order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$table} WHERE entity_type = %s",
				$params['entity_type']
			)
		);

		$data = array(
			'entity_type'      => $params['entity_type'],
			'field_key'        => $field_key,
			'field_name'       => sanitize_text_field( $params['field_name'] ),
			'field_label'      => sanitize_text_field( $params['field_label'] ),
			'field_type'       => $params['field_type'],
			'description'      => sanitize_textarea_field( $params['description'] ?? '' ),
			'placeholder'      => sanitize_text_field( $params['placeholder'] ?? '' ),
			'default_value'    => $params['default_value'] ?? null,
			'options'          => wp_json_encode( $params['options'] ?? array() ),
			'settings'         => wp_json_encode( $params['settings'] ?? array() ),
			'validation_rules' => wp_json_encode( $params['validation_rules'] ?? array() ),
			'field_group'      => sanitize_text_field( $params['field_group'] ?? 'default' ),
			'sort_order'       => ( (int) $max_order ) + 1,
			'is_required'      => ! empty( $params['is_required'] ) ? 1 : 0,
			'is_active'        => 1,
			'show_in_list'     => ! empty( $params['show_in_list'] ) ? 1 : 0,
			'show_in_form'     => isset( $params['show_in_form'] ) ? ( $params['show_in_form'] ? 1 : 0 ) : 1,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $data );

		if ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Failed to create field',
				),
				500
			);
		}

		$field_id = $wpdb->insert_id;

		return new WP_REST_Response(
			array(
				'success'  => true,
				'field_id' => $field_id,
				'field'    => array_merge( $data, array( 'id' => $field_id ) ),
			),
			201
		);
	}

	/**
	 * Update custom field.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function update_field( $request ) {
		global $wpdb;

		$field_id = $request->get_param( 'id' );
		$params   = $request->get_json_params();
		$table    = $wpdb->prefix . 'ict_custom_fields';

		// Check field exists
		$field = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $field_id ),
			ARRAY_A
		);

		if ( ! $field ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Field not found',
				),
				404
			);
		}

		$data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		// Update allowed fields
		$allowed = array(
			'field_name',
			'field_label',
			'description',
			'placeholder',
			'default_value',
			'field_group',
			'is_required',
			'is_active',
			'show_in_list',
			'show_in_form',
		);

		foreach ( $allowed as $key ) {
			if ( isset( $params[ $key ] ) ) {
				if ( in_array( $key, array( 'is_required', 'is_active', 'show_in_list', 'show_in_form' ), true ) ) {
					$data[ $key ] = $params[ $key ] ? 1 : 0;
				} else {
					$data[ $key ] = sanitize_text_field( $params[ $key ] );
				}
			}
		}

		// Update JSON fields
		if ( isset( $params['options'] ) ) {
			$data['options'] = wp_json_encode( $params['options'] );
		}

		if ( isset( $params['settings'] ) ) {
			$data['settings'] = wp_json_encode( $params['settings'] );
		}

		if ( isset( $params['validation_rules'] ) ) {
			$data['validation_rules'] = wp_json_encode( $params['validation_rules'] );
		}

		$wpdb->update( $table, $data, array( 'id' => $field_id ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Field updated',
			),
			200
		);
	}

	/**
	 * Delete custom field.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function delete_field( $request ) {
		global $wpdb;

		$field_id = $request->get_param( 'id' );
		$table    = $wpdb->prefix . 'ict_custom_fields';

		// Check field exists
		$field = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $field_id ),
			ARRAY_A
		);

		if ( ! $field ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Field not found',
				),
				404
			);
		}

		// Delete field values
		$values_table = $wpdb->prefix . 'ict_custom_field_values';
		$wpdb->delete( $values_table, array( 'field_id' => $field_id ) );

		// Delete field
		$wpdb->delete( $table, array( 'id' => $field_id ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Field deleted',
			),
			200
		);
	}

	/**
	 * Reorder fields.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function reorder_fields( $request ) {
		global $wpdb;

		$params = $request->get_json_params();
		$order  = $params['order'] ?? array();
		$table  = $wpdb->prefix . 'ict_custom_fields';

		foreach ( $order as $position => $field_id ) {
			$wpdb->update(
				$table,
				array( 'sort_order' => $position ),
				array( 'id' => $field_id )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Fields reordered',
			),
			200
		);
	}

	/**
	 * Get field values for entity.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_field_values( $request ) {
		global $wpdb;

		$entity_type = $request->get_param( 'entity_type' );
		$entity_id   = $request->get_param( 'entity_id' );

		if ( ! in_array( $entity_type, $this->entity_types, true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid entity type',
				),
				400
			);
		}

		// Get field definitions
		$fields_table = $wpdb->prefix . 'ict_custom_fields';
		$values_table = $wpdb->prefix . 'ict_custom_field_values';

		$fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.*, v.field_value
				FROM {$fields_table} f
				LEFT JOIN {$values_table} v ON f.id = v.field_id
					AND v.entity_type = %s AND v.entity_id = %d
				WHERE f.entity_type = %s AND f.is_active = 1
				ORDER BY f.field_group, f.sort_order",
				$entity_type,
				$entity_id,
				$entity_type
			),
			ARRAY_A
		);

		// Format response
		$values = array();
		foreach ( $fields as $field ) {
			$value = $field['field_value'];

			// Decode JSON values for multi-value fields
			if ( in_array( $field['field_type'], array( 'multiselect', 'checkbox' ), true ) && $value ) {
				$value = json_decode( $value, true );
			}

			$values[ $field['field_key'] ] = array(
				'field_id'    => $field['id'],
				'field_key'   => $field['field_key'],
				'field_label' => $field['field_label'],
				'field_type'  => $field['field_type'],
				'value'       => $value ?? $field['default_value'],
				'options'     => json_decode( $field['options'], true ) ?? array(),
				'settings'    => json_decode( $field['settings'], true ) ?? array(),
				'is_required' => (bool) $field['is_required'],
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'entity'  => array(
					'type' => $entity_type,
					'id'   => $entity_id,
				),
				'values'  => $values,
			),
			200
		);
	}

	/**
	 * Save field values for entity.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function save_field_values( $request ) {
		global $wpdb;

		$entity_type = $request->get_param( 'entity_type' );
		$entity_id   = $request->get_param( 'entity_id' );
		$params      = $request->get_json_params();
		$values      = $params['values'] ?? array();

		if ( ! in_array( $entity_type, $this->entity_types, true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Invalid entity type',
				),
				400
			);
		}

		$fields_table = $wpdb->prefix . 'ict_custom_fields';
		$values_table = $wpdb->prefix . 'ict_custom_field_values';

		// Get field definitions
		$fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$fields_table} WHERE entity_type = %s AND is_active = 1",
				$entity_type
			),
			OBJECT_K
		);

		$errors = array();

		foreach ( $values as $field_key => $value ) {
			// Find field by key
			$field = null;
			foreach ( $fields as $f ) {
				if ( $f->field_key === $field_key ) {
					$field = $f;
					break;
				}
			}

			if ( ! $field ) {
				continue;
			}

			// Validate required
			if ( $field->is_required && empty( $value ) && $value !== '0' ) {
				$errors[ $field_key ] = sprintf( __( '%s is required', 'ict-platform' ), $field->field_label );
				continue;
			}

			// Validate value
			$validation_result = $this->validate_field_value( $field, $value );
			if ( $validation_result !== true ) {
				$errors[ $field_key ] = $validation_result;
				continue;
			}

			// Encode multi-value fields
			if ( in_array( $field->field_type, array( 'multiselect', 'checkbox' ), true ) && is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}

			// Save value
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$values_table}
					WHERE entity_type = %s AND entity_id = %d AND field_id = %d",
					$entity_type,
					$entity_id,
					$field->id
				)
			);

			if ( $existing ) {
				$wpdb->update(
					$values_table,
					array(
						'field_value' => $value,
						'updated_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $existing )
				);
			} else {
				$wpdb->insert(
					$values_table,
					array(
						'entity_type' => $entity_type,
						'entity_id'   => $entity_id,
						'field_id'    => $field->id,
						'field_value' => $value,
						'created_at'  => current_time( 'mysql' ),
						'updated_at'  => current_time( 'mysql' ),
					)
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'errors'  => $errors,
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Values saved',
			),
			200
		);
	}

	/**
	 * Validate field value.
	 *
	 * @since  1.1.0
	 * @param  object $field Field definition.
	 * @param  mixed  $value Value to validate.
	 * @return bool|string True if valid, error message otherwise.
	 */
	private function validate_field_value( $field, $value ) {
		if ( empty( $value ) && $value !== '0' ) {
			return true; // Empty non-required values are OK
		}

		$rules = json_decode( $field->validation_rules, true ) ?? array();

		switch ( $field->field_type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return __( 'Invalid email address', 'ict-platform' );
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return __( 'Invalid URL', 'ict-platform' );
				}
				break;

			case 'number':
			case 'decimal':
			case 'currency':
				if ( ! is_numeric( $value ) ) {
					return __( 'Must be a number', 'ict-platform' );
				}

				if ( isset( $rules['min'] ) && $value < $rules['min'] ) {
					return sprintf( __( 'Must be at least %s', 'ict-platform' ), $rules['min'] );
				}

				if ( isset( $rules['max'] ) && $value > $rules['max'] ) {
					return sprintf( __( 'Must be no more than %s', 'ict-platform' ), $rules['max'] );
				}
				break;

			case 'text':
			case 'textarea':
				if ( isset( $rules['min_length'] ) && strlen( $value ) < $rules['min_length'] ) {
					return sprintf( __( 'Must be at least %d characters', 'ict-platform' ), $rules['min_length'] );
				}

				if ( isset( $rules['max_length'] ) && strlen( $value ) > $rules['max_length'] ) {
					return sprintf( __( 'Must be no more than %d characters', 'ict-platform' ), $rules['max_length'] );
				}

				if ( isset( $rules['pattern'] ) && ! preg_match( '/' . $rules['pattern'] . '/', $value ) ) {
					return $rules['pattern_message'] ?? __( 'Invalid format', 'ict-platform' );
				}
				break;

			case 'select':
			case 'radio':
				$options      = json_decode( $field->options, true ) ?? array();
				$valid_values = array_column( $options, 'value' );
				if ( ! in_array( $value, $valid_values, true ) ) {
					return __( 'Invalid selection', 'ict-platform' );
				}
				break;

			case 'multiselect':
			case 'checkbox':
				if ( is_array( $value ) ) {
					$options      = json_decode( $field->options, true ) ?? array();
					$valid_values = array_column( $options, 'value' );
					foreach ( $value as $v ) {
						if ( ! in_array( $v, $valid_values, true ) ) {
							return __( 'Invalid selection', 'ict-platform' );
						}
					}
				}
				break;

			case 'date':
				$date = DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
					return __( 'Invalid date format', 'ict-platform' );
				}
				break;

			case 'phone':
				$cleaned = preg_replace( '/[^0-9+]/', '', $value );
				if ( strlen( $cleaned ) < 10 ) {
					return __( 'Invalid phone number', 'ict-platform' );
				}
				break;
		}

		return true;
	}

	/**
	 * Get available field types.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_field_types( $request ) {
		$types = array();

		foreach ( $this->field_types as $type => $label ) {
			$types[] = array(
				'value'       => $type,
				'label'       => $label,
				'icon'        => $this->get_field_type_icon( $type ),
				'has_options' => in_array( $type, array( 'select', 'multiselect', 'radio', 'checkbox' ), true ),
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'types'   => $types,
			),
			200
		);
	}

	/**
	 * Get icon for field type.
	 *
	 * @since  1.1.0
	 * @param  string $type Field type.
	 * @return string Icon name.
	 */
	private function get_field_type_icon( $type ) {
		$icons = array(
			'text'        => 'type',
			'textarea'    => 'align-left',
			'number'      => 'hash',
			'decimal'     => 'percent',
			'currency'    => 'dollar-sign',
			'email'       => 'mail',
			'phone'       => 'phone',
			'url'         => 'link',
			'date'        => 'calendar',
			'datetime'    => 'clock',
			'time'        => 'watch',
			'select'      => 'chevron-down',
			'multiselect' => 'list',
			'checkbox'    => 'check-square',
			'radio'       => 'circle',
			'file'        => 'file',
			'image'       => 'image',
			'color'       => 'droplet',
			'user'        => 'user',
			'project'     => 'folder',
			'formula'     => 'zap',
		);

		return $icons[ $type ] ?? 'square';
	}

	/**
	 * Get available entity types.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_entity_types( $request ) {
		$types = array(
			array(
				'value' => 'project',
				'label' => __( 'Project', 'ict-platform' ),
			),
			array(
				'value' => 'time_entry',
				'label' => __( 'Time Entry', 'ict-platform' ),
			),
			array(
				'value' => 'inventory_item',
				'label' => __( 'Inventory Item', 'ict-platform' ),
			),
			array(
				'value' => 'purchase_order',
				'label' => __( 'Purchase Order', 'ict-platform' ),
			),
			array(
				'value' => 'user',
				'label' => __( 'User', 'ict-platform' ),
			),
			array(
				'value' => 'task',
				'label' => __( 'Task', 'ict-platform' ),
			),
		);

		return new WP_REST_Response(
			array(
				'success'      => true,
				'entity_types' => $types,
			),
			200
		);
	}

	/**
	 * Get field groups.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function get_field_groups( $request ) {
		$groups = get_option(
			'ict_custom_field_groups',
			array(
				'default' => array(
					'label' => __( 'General', 'ict-platform' ),
					'order' => 0,
				),
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'groups'  => $groups,
			),
			200
		);
	}

	/**
	 * Create field group.
	 *
	 * @since  1.1.0
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response.
	 */
	public function create_field_group( $request ) {
		$params = $request->get_json_params();

		$group_key   = sanitize_key( $params['key'] ?? '' );
		$group_label = sanitize_text_field( $params['label'] ?? '' );

		if ( empty( $group_key ) || empty( $group_label ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Group key and label are required',
				),
				400
			);
		}

		$groups = get_option( 'ict_custom_field_groups', array() );

		$groups[ $group_key ] = array(
			'label' => $group_label,
			'order' => count( $groups ),
		);

		update_option( 'ict_custom_field_groups', $groups );

		return new WP_REST_Response(
			array(
				'success' => true,
				'group'   => array(
					'key'   => $group_key,
					'label' => $group_label,
				),
			),
			201
		);
	}

	/**
	 * Calculate formula field value.
	 *
	 * @since  1.1.0
	 * @param  string $formula    Formula string.
	 * @param  array  $field_values Available field values.
	 * @return mixed Calculated value.
	 */
	public function calculate_formula( $formula, $field_values ) {
		// Replace field references with values
		$expression = preg_replace_callback(
			'/\{([a-z_]+)\}/',
			function ( $matches ) use ( $field_values ) {
				$key = $matches[1];
				return isset( $field_values[ $key ] ) ? floatval( $field_values[ $key ] ) : 0;
			},
			$formula
		);

		// Sanitize expression (only allow numbers, operators, and parentheses)
		$expression = preg_replace( '/[^0-9+\-*\/().%]/', '', $expression );

		if ( empty( $expression ) ) {
			return 0;
		}

		// Evaluate expression safely
		try {
			// Use eval carefully with sanitized expression
			$result = @eval( "return {$expression};" );
			return is_numeric( $result ) ? $result : 0;
		} catch ( Exception $e ) {
			return 0;
		}
	}
}
