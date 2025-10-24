<?php
/**
 * Extra API Registrations (modular controllers)
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Extra_API {
    /**
     * Register additional REST controllers.
     */
    public static function register() {
        if ( class_exists( 'ICT_REST_OCR_Controller' ) ) {
            $c = new ICT_REST_OCR_Controller();
            $c->register_routes();
        }
        if ( class_exists( 'ICT_REST_Routing_Controller' ) ) {
            $c = new ICT_REST_Routing_Controller();
            $c->register_routes();
        }
        if ( class_exists( 'ICT_REST_Signatures_Controller' ) ) {
            $c = new ICT_REST_Signatures_Controller();
            $c->register_routes();
        }
        if ( class_exists( 'ICT_REST_Data_Health_Controller' ) ) {
            $c = new ICT_REST_Data_Health_Controller();
            $c->register_routes();
        }
    }
}

