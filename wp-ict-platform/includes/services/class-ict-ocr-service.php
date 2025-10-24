<?php
/**
 * OCR Service
 *
 * Provides a pluggable OCR interface with a simple fallback parser.
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_OCR_Service {

    /**
     * Analyze an image or PDF and extract receipt-like fields.
     *
     * @param string $file_path Local filesystem path.
     * @return array{success:bool, fields?:array<string,mixed>, text?:string, message?:string}
     */
    public static function analyze( $file_path ) {
        $result = array(
            'success' => false,
            'message' => __( 'No OCR provider configured', 'ict-platform' ),
        );

        /**
         * Filter: ict_ocr_providers
         *
         * Allows 3rd-parties to provide OCR providers as callables receiving ($file_path): array.
         */
        $providers = apply_filters( 'ict_ocr_providers', array() );
        foreach ( $providers as $provider ) {
            try {
                $response = call_user_func( $provider, $file_path );
                if ( is_array( $response ) && ! empty( $response['success'] ) ) {
                    return $response;
                }
            } catch ( \Throwable $e ) {
                // Try next provider
                continue;
            }
        }

        // Fallback: try heuristics on filename
        $basename = basename( $file_path );
        $fields   = self::heuristic_from_filename( $basename );
        if ( ! empty( $fields ) ) {
            return array(
                'success' => true,
                'fields'  => $fields,
                'text'    => '',
            );
        }

        return $result;
    }

    /**
     * Heuristic: parse amount/date from filename like "receipt_2025-01-01_123.45.jpg".
     */
    protected static function heuristic_from_filename( $name ) {
        $out = array();
        if ( preg_match( '/(20\d{2}-\d{2}-\d{2})/', $name, $m ) ) {
            $out['date'] = $m[1];
        }
        if ( preg_match( '/(\d+\.\d{2})/', $name, $m2 ) ) {
            $out['amount'] = (float) $m2[1];
        }
        return $out;
    }
}

