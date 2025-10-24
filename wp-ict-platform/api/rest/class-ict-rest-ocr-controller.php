<?php
/**
 * REST API OCR Controller
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_REST_OCR_Controller extends WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'ict/v1';
        $this->rest_base = 'expenses';
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/ocr',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'analyze' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'attachment_id' => array(
                        'required' => false,
                        'type'     => 'integer',
                    ),
                    'file_url' => array(
                        'required' => false,
                        'type'     => 'string',
                    ),
                ),
            )
        );
    }

    public function permission_check() {
        return is_user_logged_in();
    }

    /**
     * Analyze a receipt image/PDF and return extracted fields.
     */
    public function analyze( $request ) {
        $attachment_id = absint( $request->get_param( 'attachment_id' ) );
        $file_url      = $request->get_param( 'file_url' );

        $path = '';
        if ( $attachment_id ) {
            $path = get_attached_file( $attachment_id );
        } elseif ( $file_url ) {
            $file = download_url( esc_url_raw( $file_url ) );
            if ( is_wp_error( $file ) ) {
                return new WP_Error( 'ocr_download_failed', $file->get_error_message(), array( 'status' => 400 ) );
            }
            $path = $file;
        } else {
            return new WP_Error( 'ocr_no_file', __( 'No file provided', 'ict-platform' ), array( 'status' => 400 ) );
        }

        if ( ! file_exists( $path ) ) {
            return new WP_Error( 'ocr_file_missing', __( 'File not found', 'ict-platform' ), array( 'status' => 400 ) );
        }

        require_once ICT_PLATFORM_PLUGIN_DIR . 'includes/services/class-ict-ocr-service.php';
        $result = ICT_OCR_Service::analyze( $path );

        if ( isset( $file ) && is_string( $file ) && file_exists( $file ) ) {
            @unlink( $file );
        }

        if ( empty( $result['success'] ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => isset( $result['message'] ) ? $result['message'] : __( 'OCR failed', 'ict-platform' ),
            ), 200 );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => array(
                'fields' => isset( $result['fields'] ) ? $result['fields'] : array(),
                'text'   => isset( $result['text'] ) ? $result['text'] : '',
            ),
        ), 200 );
    }
}

