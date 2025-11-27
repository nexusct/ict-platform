<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Fleet/Vehicle Tracking REST API Controller
 *
 * Handles vehicle management, GPS tracking, and fleet monitoring.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class FleetController extends AbstractController
{
    /**
     * REST base for this controller.
     *
     * @var string
     */
    protected string $rest_base = 'fleet';

    /**
     * Register routes for fleet management.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // GET /fleet - List all vehicles
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'getItems'],
                    'permission_callback' => [$this, 'canViewProjects'],
                    'args'                => $this->getCollectionParams(),
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'createItem'],
                    'permission_callback' => [$this, 'canManageFleet'],
                ],
            ]
        );

        // GET/PUT/DELETE /fleet/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'getItem'],
                    'permission_callback' => [$this, 'canViewProjects'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'updateItem'],
                    'permission_callback' => [$this, 'canManageFleet'],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'deleteItem'],
                    'permission_callback' => [$this, 'canManageFleet'],
                ],
            ]
        );

        // POST /fleet/{id}/location - Update vehicle location
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/location',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'updateLocation'],
                'permission_callback' => '__return_true',
            ]
        );

        // GET /fleet/{id}/locations - Get vehicle location history
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/locations',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getLocationHistory'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /fleet/map - Get all vehicles with current locations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/map',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getMapData'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // POST /fleet/{id}/assign - Assign driver/project
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/assign',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'assignVehicle'],
                'permission_callback' => [$this, 'canManageProjects'],
            ]
        );

        // POST /fleet/{id}/service - Log service/maintenance
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/service',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'logService'],
                'permission_callback' => [$this, 'canManageFleet'],
            ]
        );

        // GET /fleet/due-service - Get vehicles due for service
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/due-service',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getDueService'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /fleet/expiring - Get vehicles with expiring documents
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/expiring',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getExpiringDocuments'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /fleet/types - Get vehicle types
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/types',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getTypes'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Get all vehicles.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $page     = (int) $request->get_param('page') ?: 1;
        $per_page = min((int) $request->get_param('per_page') ?: 20, 100);
        $offset   = ($page - 1) * $per_page;

        $where_clauses = ['1=1'];
        $where_values  = [];

        if ($type = $request->get_param('vehicle_type')) {
            $where_clauses[] = 'vehicle_type = %s';
            $where_values[]  = sanitize_text_field($type);
        }

        if ($status = $request->get_param('status')) {
            $where_clauses[] = 'status = %s';
            $where_values[]  = sanitize_text_field($status);
        }

        if ($driver = $request->get_param('assigned_driver')) {
            $where_clauses[] = 'assigned_driver = %d';
            $where_values[]  = (int) $driver;
        }

        if ($search = $request->get_param('search')) {
            $where_clauses[] = '(vehicle_name LIKE %s OR vehicle_number LIKE %s OR license_plate LIKE %s)';
            $search_term     = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
            $where_values[]  = $search_term;
            $where_values[]  = $search_term;
            $where_values[]  = $search_term;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM " . ICT_FLEET_TABLE . " WHERE " . $where_sql;
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, ...$where_values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Get vehicles
        $query = "SELECT * FROM " . ICT_FLEET_TABLE . " WHERE " . $where_sql . " ORDER BY vehicle_name ASC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($query, ...$query_values));

        // Add driver info and last location
        foreach ($items as &$item) {
            if ($item->assigned_driver) {
                $user = get_userdata($item->assigned_driver);
                $item->driver_name = $user ? $user->display_name : null;
            }

            // Get last location
            $item->last_location = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT latitude, longitude, speed, recorded_at
                     FROM " . ICT_FLEET_LOCATIONS_TABLE . "
                     WHERE vehicle_id = %d
                     ORDER BY recorded_at DESC
                     LIMIT 1",
                    $item->id
                )
            );
        }

        return $this->paginated($items ?: [], $total, $page, $per_page);
    }

    /**
     * Get a single vehicle.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        if (!$vehicle) {
            return $this->error('not_found', 'Vehicle not found', 404);
        }

        // Add driver info
        if ($vehicle->assigned_driver) {
            $user = get_userdata($vehicle->assigned_driver);
            $vehicle->driver_name = $user ? $user->display_name : null;
        }

        // Get last location
        $vehicle->last_location = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT latitude, longitude, speed, heading, recorded_at, address
                 FROM " . ICT_FLEET_LOCATIONS_TABLE . "
                 WHERE vehicle_id = %d
                 ORDER BY recorded_at DESC
                 LIMIT 1",
                $id
            )
        );

        return $this->success($vehicle);
    }

    /**
     * Create a new vehicle.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $data = [
            'vehicle_name'          => sanitize_text_field($request->get_param('vehicle_name')),
            'vehicle_number'        => sanitize_text_field($request->get_param('vehicle_number')),
            'vehicle_type'          => sanitize_text_field($request->get_param('vehicle_type')),
            'make'                  => sanitize_text_field($request->get_param('make')),
            'model'                 => sanitize_text_field($request->get_param('model')),
            'year'                  => (int) $request->get_param('year'),
            'vin'                   => sanitize_text_field($request->get_param('vin')),
            'license_plate'         => sanitize_text_field($request->get_param('license_plate')),
            'color'                 => sanitize_text_field($request->get_param('color')),
            'status'                => sanitize_text_field($request->get_param('status') ?: 'available'),
            'current_mileage'       => (int) $request->get_param('current_mileage'),
            'fuel_type'             => sanitize_text_field($request->get_param('fuel_type')),
            'fuel_capacity'         => (float) $request->get_param('fuel_capacity'),
            'insurance_expiry'      => sanitize_text_field($request->get_param('insurance_expiry')),
            'registration_expiry'   => sanitize_text_field($request->get_param('registration_expiry')),
            'service_interval_miles' => (int) ($request->get_param('service_interval_miles') ?: 5000),
            'gps_device_id'         => sanitize_text_field($request->get_param('gps_device_id')),
            'notes'                 => sanitize_textarea_field($request->get_param('notes')),
        ];

        // Generate QR code
        $data['qr_code'] = 'VH-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $result = $wpdb->insert(ICT_FLEET_TABLE, $data);

        if ($result === false) {
            return $this->error('insert_failed', 'Failed to create vehicle', 500);
        }

        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $wpdb->insert_id)
        );

        // Create QR code record
        $wpdb->insert(ICT_QR_CODES_TABLE, [
            'code'        => $vehicle->qr_code,
            'entity_type' => 'vehicle',
            'entity_id'   => $vehicle->id,
            'label'       => $vehicle->vehicle_name,
            'is_active'   => 1,
            'created_by'  => get_current_user_id(),
        ]);

        return $this->success($vehicle, 201);
    }

    /**
     * Update a vehicle.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        if (!$existing) {
            return $this->error('not_found', 'Vehicle not found', 404);
        }

        $updateable = [
            'vehicle_name', 'vehicle_number', 'vehicle_type', 'make', 'model',
            'year', 'vin', 'license_plate', 'color', 'status', 'current_mileage',
            'fuel_type', 'fuel_capacity', 'insurance_expiry', 'registration_expiry',
            'service_interval_miles', 'gps_device_id', 'notes'
        ];

        $data = [];
        foreach ($updateable as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                if (in_array($field, ['year', 'current_mileage', 'service_interval_miles'])) {
                    $data[$field] = (int) $value;
                } elseif ($field === 'fuel_capacity') {
                    $data[$field] = (float) $value;
                } else {
                    $data[$field] = sanitize_text_field($value);
                }
            }
        }

        if (empty($data)) {
            return $this->error('no_data', 'No data to update', 400);
        }

        $wpdb->update(ICT_FLEET_TABLE, $data, ['id' => $id]);

        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        return $this->success($vehicle);
    }

    /**
     * Delete a vehicle.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function deleteItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        if (!$vehicle) {
            return $this->error('not_found', 'Vehicle not found', 404);
        }

        // Delete location history
        $wpdb->delete(ICT_FLEET_LOCATIONS_TABLE, ['vehicle_id' => $id]);

        // Delete QR code
        $wpdb->delete(ICT_QR_CODES_TABLE, ['entity_type' => 'vehicle', 'entity_id' => $id]);

        // Delete vehicle
        $wpdb->delete(ICT_FLEET_TABLE, ['id' => $id]);

        return $this->success(['deleted' => true]);
    }

    /**
     * Update vehicle location.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function updateLocation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        if (!$vehicle) {
            return $this->error('not_found', 'Vehicle not found', 404);
        }

        $latitude = (float) $request->get_param('latitude');
        $longitude = (float) $request->get_param('longitude');

        if (!$latitude || !$longitude) {
            return $this->error('validation', 'Latitude and longitude are required', 400);
        }

        $location_data = [
            'vehicle_id'    => $id,
            'latitude'      => $latitude,
            'longitude'     => $longitude,
            'speed'         => (float) ($request->get_param('speed') ?: 0),
            'heading'       => (int) ($request->get_param('heading') ?: 0),
            'altitude'      => $request->get_param('altitude') ? (float) $request->get_param('altitude') : null,
            'accuracy'      => $request->get_param('accuracy') ? (float) $request->get_param('accuracy') : null,
            'address'       => sanitize_text_field($request->get_param('address')),
            'engine_status' => sanitize_text_field($request->get_param('engine_status') ?: 'on'),
            'recorded_at'   => current_time('mysql'),
        ];

        $wpdb->insert(ICT_FLEET_LOCATIONS_TABLE, $location_data);

        return $this->success([
            'id'          => $wpdb->insert_id,
            'vehicle_id'  => $id,
            'recorded_at' => $location_data['recorded_at'],
        ], 201);
    }

    /**
     * Get vehicle location history.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getLocationHistory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $date_from = sanitize_text_field($request->get_param('date_from') ?: date('Y-m-d 00:00:00'));
        $date_to = sanitize_text_field($request->get_param('date_to') ?: date('Y-m-d 23:59:59'));
        $limit = min((int) ($request->get_param('limit') ?: 1000), 5000);

        $locations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT latitude, longitude, speed, heading, recorded_at, address
                 FROM " . ICT_FLEET_LOCATIONS_TABLE . "
                 WHERE vehicle_id = %d
                 AND recorded_at BETWEEN %s AND %s
                 ORDER BY recorded_at ASC
                 LIMIT %d",
                $id,
                $date_from,
                $date_to,
                $limit
            )
        );

        return $this->success([
            'vehicle_id' => $id,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'count'      => count($locations),
            'locations'  => $locations ?: [],
        ]);
    }

    /**
     * Get map data for all vehicles.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getMapData(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        // Get all vehicles with their last location
        $vehicles = $wpdb->get_results(
            "SELECT v.*,
                    l.latitude, l.longitude, l.speed, l.heading, l.recorded_at as location_time,
                    u.display_name as driver_name
             FROM " . ICT_FLEET_TABLE . " v
             LEFT JOIN (
                 SELECT l1.*
                 FROM " . ICT_FLEET_LOCATIONS_TABLE . " l1
                 INNER JOIN (
                     SELECT vehicle_id, MAX(recorded_at) as max_time
                     FROM " . ICT_FLEET_LOCATIONS_TABLE . "
                     GROUP BY vehicle_id
                 ) l2 ON l1.vehicle_id = l2.vehicle_id AND l1.recorded_at = l2.max_time
             ) l ON v.id = l.vehicle_id
             LEFT JOIN {$wpdb->users} u ON v.assigned_driver = u.ID
             WHERE v.status != 'retired'"
        );

        return $this->success($vehicles ?: []);
    }

    /**
     * Assign vehicle to driver/project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function assignVehicle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        if (!$vehicle) {
            return $this->error('not_found', 'Vehicle not found', 404);
        }

        $data = [
            'assigned_driver'  => $request->get_param('driver_id') ? (int) $request->get_param('driver_id') : null,
            'assigned_project' => $request->get_param('project_id') ? (int) $request->get_param('project_id') : null,
            'status'           => $request->get_param('driver_id') ? 'in-use' : 'available',
        ];

        $wpdb->update(ICT_FLEET_TABLE, $data, ['id' => $id]);

        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        return $this->success($vehicle);
    }

    /**
     * Log service/maintenance.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function logService(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        if (!$vehicle) {
            return $this->error('not_found', 'Vehicle not found', 404);
        }

        $service_date = sanitize_text_field($request->get_param('service_date') ?: date('Y-m-d'));
        $mileage = (int) ($request->get_param('mileage') ?: $vehicle->current_mileage);
        $interval = $vehicle->service_interval_miles ?: 5000;

        // Calculate next service
        $next_service_mileage = $mileage + $interval;

        $notes = $vehicle->notes . "\n[" . $service_date . "] Service at " . number_format($mileage) . " miles: " . sanitize_textarea_field($request->get_param('notes'));

        $wpdb->update(
            ICT_FLEET_TABLE,
            [
                'last_service_date' => $service_date,
                'current_mileage'   => $mileage,
                'notes'             => $notes,
            ],
            ['id' => $id]
        );

        $vehicle = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_FLEET_TABLE . " WHERE id = %d", $id)
        );

        return $this->success($vehicle);
    }

    /**
     * Get vehicles due for service.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getDueService(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $threshold = (int) ($request->get_param('miles_threshold') ?: 500);

        // Get vehicles where current mileage is close to next service
        $vehicles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *,
                        (COALESCE(last_service_date, created_at)) as last_service,
                        (current_mileage %% service_interval_miles) as miles_since_service
                 FROM " . ICT_FLEET_TABLE . "
                 WHERE status != 'retired'
                 AND (current_mileage %% service_interval_miles) >= (service_interval_miles - %d)
                 ORDER BY (current_mileage %% service_interval_miles) DESC",
                $threshold
            )
        );

        return $this->success($vehicles ?: []);
    }

    /**
     * Get vehicles with expiring documents.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getExpiringDocuments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $days_ahead = (int) ($request->get_param('days') ?: 30);
        $target_date = date('Y-m-d', strtotime('+' . $days_ahead . ' days'));

        $vehicles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *,
                        CASE
                            WHEN insurance_expiry <= %s THEN 'insurance'
                            WHEN registration_expiry <= %s THEN 'registration'
                            ELSE 'both'
                        END as expiring_document
                 FROM " . ICT_FLEET_TABLE . "
                 WHERE status != 'retired'
                 AND (insurance_expiry <= %s OR registration_expiry <= %s)
                 ORDER BY LEAST(COALESCE(insurance_expiry, '9999-12-31'), COALESCE(registration_expiry, '9999-12-31')) ASC",
                $target_date,
                $target_date,
                $target_date,
                $target_date
            )
        );

        return $this->success($vehicles ?: []);
    }

    /**
     * Get vehicle types.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getTypes(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $types = [
            'van'         => 'Service Van',
            'truck'       => 'Truck',
            'pickup'      => 'Pickup Truck',
            'suv'         => 'SUV',
            'sedan'       => 'Sedan',
            'box-truck'   => 'Box Truck',
            'trailer'     => 'Trailer',
            'specialty'   => 'Specialty Vehicle',
        ];

        return $this->success($types);
    }

    /**
     * Check if user can manage fleet.
     *
     * @return bool
     */
    protected function canManageFleet(): bool
    {
        return current_user_can('manage_ict_inventory') || current_user_can('manage_options');
    }
}
