<?php
/**
 * Centralized data validation and normalization utilities
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ICT_Data_Validator
 *
 * Lightweight validation helpers to improve input data quality.
 */
class ICT_Data_Validator {

    /**
     * Validate a non-empty string up to max length.
     *
     * @param mixed $value Input value.
     * @param int   $max   Maximum length.
     * @return string Sanitized value.
     * @throws InvalidArgumentException When invalid.
     */
    public static function required_string( $value, $max = 255 ) {
        $value = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
        if ( $value === '' ) {
            throw new InvalidArgumentException( __( 'Value is required.', 'ict-platform' ) );
        }
        if ( strlen( $value ) > $max ) {
            throw new InvalidArgumentException( sprintf( __( 'Value exceeds maximum length of %d.', 'ict-platform' ), $max ) );
        }
        return $value;
    }

    /**
     * Validate optional string with max length.
     */
    public static function optional_string( $value, $max = 65535 ) {
        if ( null === $value || '' === $value ) {
            return null;
        }
        $value = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
        if ( strlen( $value ) > $max ) {
            throw new InvalidArgumentException( sprintf( __( 'Value exceeds maximum length of %d.', 'ict-platform' ), $max ) );
        }
        return $value;
    }

    /**
     * Validate positive decimal number (>= 0).
     */
    public static function non_negative_decimal( $value, $precision = 2 ) {
        if ( null === $value || '' === $value ) {
            return 0.0;
        }
        if ( ! is_numeric( $value ) ) {
            throw new InvalidArgumentException( __( 'Invalid numeric value.', 'ict-platform' ) );
        }
        $number = round( floatval( $value ), $precision );
        if ( $number < 0 ) {
            throw new InvalidArgumentException( __( 'Value cannot be negative.', 'ict-platform' ) );
        }
        return $number;
    }

    /**
     * Validate integer ID (> 0).
     */
    public static function id( $value ) {
        $id = intval( $value );
        if ( $id <= 0 ) {
            throw new InvalidArgumentException( __( 'Invalid identifier.', 'ict-platform' ) );
        }
        return $id;
    }

    /**
     * Validate status against allowed set.
     */
    public static function enum( $value, array $allowed, $default = null ) {
        $value = is_string( $value ) ? trim( strtolower( $value ) ) : $default;
        if ( null !== $value && ! in_array( $value, $allowed, true ) ) {
            throw new InvalidArgumentException( __( 'Invalid value provided.', 'ict-platform' ) );
        }
        return $value ?? $default;
    }

    /**
     * Validate ISO8601 date/datetime string.
     */
    public static function iso_datetime( $value, $required = false ) {
        if ( ! $value ) {
            if ( $required ) {
                throw new InvalidArgumentException( __( 'Date/time is required.', 'ict-platform' ) );
            }
            return null;
        }
        $ts = strtotime( $value );
        if ( false === $ts ) {
            throw new InvalidArgumentException( __( 'Invalid date/time format.', 'ict-platform' ) );
        }
        return date( 'Y-m-d H:i:s', $ts );
    }

    /**
     * Validate coordinates using existing helper.
     */
    public static function coordinates( $value ) {
        $coords = ICT_Helper::sanitize_coordinates( $value );
        if ( null === $coords ) {
            throw new InvalidArgumentException( __( 'Invalid coordinates.', 'ict-platform' ) );
        }
        return $coords;
    }
}

