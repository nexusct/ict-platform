<?php
/**
 * REST API Signatures Controller
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_REST_Signatures_Controller extends WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'ict/v1';
        $this->rest_base = 'signatures';
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'permission_check' ),
            )
        );
    }

    public function permission_check() {
        return is_user_logged_in();
    }

    public function create_item( $request ) {
        global $wpdb;

        $entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
        $entity_id   = absint( $request->get_param( 'entity_id' ) );
        $signer_name = sanitize_text_field( $request->get_param( 'signer_name' ) );
        $image_base64 = $request->get_param( 'image_base64' );

        if ( empty( $entity_type ) || $entity_id <= 0 || empty( $image_base64 ) ) {
            return new WP_Error( 'invalid_input', __( 'Missing required fields', 'ict-platform' ), array( 'status' => 400 ) );
        }

        // Decode image
        $data = explode( ',', $image_base64 );
        $raw  = base64_decode( end( $data ) );
        if ( false === $raw ) {
            return new WP_Error( 'invalid_image', __( 'Invalid image data', 'ict-platform' ), array( 'status' => 400 ) );
        }

        // Store in uploads/ict-platform/signatures
        $upload_dir = wp_upload_dir();
        $dir        = trailingslashit( $upload_dir['basedir'] ) . 'ict-platform/signatures/';
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $filename = 'signature_' . time() . '_' . $entity_type . '_' . $entity_id . '.png';
        $path     = $dir . $filename;
        file_put_contents( $path, $raw );

        $hash = hash_file( 'sha256', $path );
        $url  = trailingslashit( $upload_dir['baseurl'] ) . 'ict-platform/signatures/' . $filename;

        $wpdb->insert(
            ICT_SIGNATURES_TABLE,
            array(
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'signer_name' => $signer_name,
                'signed_at'   => current_time( 'mysql' ),
                'file_url'    => $url,
                'file_path'   => $path,
                'hash'        => $hash,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        $id = $wpdb->insert_id;

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'id'          => $id,
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'signer_name' => $signer_name,
                'signed_at'   => current_time( 'mysql' ),
                'file_url'    => $url,
                'hash'        => $hash,
            ),
        ), 201 );
    }

    public function get_item( $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ICT_SIGNATURES_TABLE . ' WHERE id = %d', $id ), ARRAY_A );
        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Signature not found', 'ict-platform' ), array( 'status' => 404 ) );
        }
        return new WP_REST_Response( array( 'success' => true, 'data' => $row ), 200 );
    }
}

