<?php
/**
 * REST API Routing Controller
 *
 * Provides simple route optimization using nearest neighbor.
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_REST_Routing_Controller extends WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'ict/v1';
        $this->rest_base = 'route';
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/optimize',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'optimize' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }

    public function permission_check() {
        return current_user_can( 'manage_ict_projects' ) || current_user_can( 'view_ict_projects' );
    }

    /**
     * Optimize route order with a nearest neighbor heuristic.
     * Request body: { start: {lat,lng}, waypoints: [{id,lat,lng}, ...] }
     */
    public function optimize( $request ) {
        $start     = $request->get_param( 'start' );
        $waypoints = $request->get_param( 'waypoints' );

        if ( empty( $start['lat'] ) || empty( $start['lng'] ) || ! is_array( $waypoints ) ) {
            return new WP_Error( 'route_invalid', __( 'Invalid input', 'ict-platform' ), array( 'status' => 400 ) );
        }

        $remaining = array_values( $waypoints );
        $order     = array();
        $current   = array( 'lat' => (float) $start['lat'], 'lng' => (float) $start['lng'] );

        while ( ! empty( $remaining ) ) {
            $nearest_index = 0;
            $nearest_dist  = PHP_FLOAT_MAX;
            foreach ( $remaining as $i => $wp ) {
                $d = $this->haversine( $current['lat'], $current['lng'], (float) $wp['lat'], (float) $wp['lng'] );
                if ( $d < $nearest_dist ) {
                    $nearest_dist  = $d;
                    $nearest_index = $i;
                }
            }
            $chosen  = $remaining[ $nearest_index ];
            $order[] = $chosen;
            $current = array( 'lat' => (float) $chosen['lat'], 'lng' => (float) $chosen['lng'] );
            array_splice( $remaining, $nearest_index, 1 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'ordered' => $order,
            ),
        ), 200 );
    }

    private function haversine( $lat1, $lon1, $lat2, $lon2 ) {
        $R = 6371; // km
        $dLat = deg2rad( $lat2 - $lat1 );
        $dLon = deg2rad( $lon2 - $lon1 );
        $a = sin( $dLat / 2 ) * sin( $dLat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dLon / 2 ) * sin( $dLon / 2 );
        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
        return $R * $c;
    }
}

