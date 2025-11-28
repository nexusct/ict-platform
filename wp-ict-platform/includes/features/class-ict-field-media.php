<?php
/**
 * Field Media (Voice Notes, Photos with GPS, Signatures)
 *
 * Media capture capabilities for field technicians.
 *
 * @package    ICT_Platform
 * @subpackage Features
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICT_Field_Media {

    private static $instance = null;
    private $media_table;
    private $signatures_table;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->media_table = $wpdb->prefix . 'ict_field_media';
        $this->signatures_table = $wpdb->prefix . 'ict_signatures';
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
    }

    public function register_routes() {
        // Voice Notes
        register_rest_route( 'ict/v1', '/media/voice-notes', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_voice_notes' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'upload_voice_note' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/media/voice-notes/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_voice_note' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_voice_note' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/media/voice-notes/(?P<id>\d+)/transcribe', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'transcribe_voice_note' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        // Photo Attachments
        register_rest_route( 'ict/v1', '/media/photos', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_photos' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'upload_photo' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/media/photos/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_photo' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_photo' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_photo' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        // Digital Signatures
        register_rest_route( 'ict/v1', '/media/signatures', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_signatures' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'capture_signature' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
        ) );

        register_rest_route( 'ict/v1', '/media/signatures/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_signature' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( 'ict/v1', '/media/signatures/verify/(?P<token>[a-zA-Z0-9]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'verify_signature' ),
            'permission_callback' => '__return_true',
        ) );

        // Entity media
        register_rest_route( 'ict/v1', '/media/(?P<entity_type>[a-z_]+)/(?P<entity_id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_entity_media' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function check_permission() {
        return is_user_logged_in();
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->media_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            media_type enum('voice_note','photo','document') NOT NULL,
            entity_type varchar(50),
            entity_id bigint(20) unsigned,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) unsigned,
            mime_type varchar(100),
            title varchar(255),
            description text,
            transcription text,
            latitude decimal(10,8),
            longitude decimal(11,8),
            location_accuracy decimal(10,2),
            location_address varchar(500),
            captured_at datetime,
            duration_seconds int,
            width int,
            height int,
            metadata text,
            uploaded_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY media_type (media_type),
            KEY entity (entity_type, entity_id),
            KEY uploaded_by (uploaded_by),
            KEY captured_at (captured_at)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->signatures_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            signature_type enum('approval','acknowledgment','completion','contract') DEFAULT 'acknowledgment',
            signatory_name varchar(255) NOT NULL,
            signatory_email varchar(255),
            signatory_title varchar(100),
            signature_data longtext NOT NULL,
            signature_image_path varchar(500),
            verification_token varchar(64),
            ip_address varchar(45),
            user_agent varchar(500),
            latitude decimal(10,8),
            longitude decimal(11,8),
            signed_at datetime DEFAULT CURRENT_TIMESTAMP,
            captured_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY verification_token (verification_token),
            KEY entity (entity_type, entity_id),
            KEY signatory_email (signatory_email),
            KEY signed_at (signed_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }

    // Voice Notes
    public function get_voice_notes( $request ) {
        global $wpdb;

        $entity_type = $request->get_param( 'entity_type' );
        $entity_id = $request->get_param( 'entity_id' );

        $where = "media_type = 'voice_note'";
        $values = array();

        if ( $entity_type && $entity_id ) {
            $where .= ' AND entity_type = %s AND entity_id = %d';
            $values[] = $entity_type;
            $values[] = (int) $entity_id;
        }

        $query = "SELECT m.*, u.display_name as uploaded_by_name
                  FROM {$this->media_table} m
                  LEFT JOIN {$wpdb->users} u ON m.uploaded_by = u.ID
                  WHERE {$where} ORDER BY m.created_at DESC";

        $notes = ! empty( $values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
            : $wpdb->get_results( $query );

        return rest_ensure_response( $notes );
    }

    public function upload_voice_note( $request ) {
        global $wpdb;

        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', 'No audio file uploaded', array( 'status' => 400 ) );
        }

        $file = $files['file'];
        $allowed = array( 'audio/webm', 'audio/mp3', 'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/m4a' );

        if ( ! in_array( $file['type'], $allowed, true ) ) {
            return new WP_Error( 'invalid_type', 'Invalid audio format', array( 'status' => 400 ) );
        }

        $upload = $this->save_file( $file, 'voice-notes' );

        if ( is_wp_error( $upload ) ) {
            return $upload;
        }

        $data = array(
            'media_type'        => 'voice_note',
            'entity_type'       => sanitize_text_field( $request->get_param( 'entity_type' ) ),
            'entity_id'         => (int) $request->get_param( 'entity_id' ),
            'file_name'         => $upload['file_name'],
            'file_path'         => $upload['file_path'],
            'file_size'         => $file['size'],
            'mime_type'         => $file['type'],
            'title'             => sanitize_text_field( $request->get_param( 'title' ) ),
            'description'       => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'duration_seconds'  => (int) $request->get_param( 'duration' ),
            'latitude'          => (float) $request->get_param( 'latitude' ),
            'longitude'         => (float) $request->get_param( 'longitude' ),
            'location_accuracy' => (float) $request->get_param( 'accuracy' ),
            'captured_at'       => current_time( 'mysql' ),
            'uploaded_by'       => get_current_user_id(),
        );

        $wpdb->insert( $this->media_table, $data );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $wpdb->insert_id,
            'url'     => $upload['url'],
        ) );
    }

    public function get_voice_note( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $note = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, u.display_name as uploaded_by_name
             FROM {$this->media_table} m
             LEFT JOIN {$wpdb->users} u ON m.uploaded_by = u.ID
             WHERE m.id = %d AND m.media_type = 'voice_note'",
            $id
        ) );

        if ( ! $note ) {
            return new WP_Error( 'not_found', 'Voice note not found', array( 'status' => 404 ) );
        }

        $note->url = $this->get_file_url( $note->file_path );

        return rest_ensure_response( $note );
    }

    public function delete_voice_note( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $note = $wpdb->get_row( $wpdb->prepare(
            "SELECT file_path FROM {$this->media_table} WHERE id = %d", $id
        ) );

        if ( $note && file_exists( $note->file_path ) ) {
            unlink( $note->file_path );
        }

        $wpdb->delete( $this->media_table, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function transcribe_voice_note( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        // Would integrate with speech-to-text service
        $transcription = 'Transcription service not configured';

        $wpdb->update( $this->media_table, array(
            'transcription' => $transcription,
        ), array( 'id' => $id ) );

        return rest_ensure_response( array(
            'success'       => true,
            'transcription' => $transcription,
        ) );
    }

    // Photos
    public function get_photos( $request ) {
        global $wpdb;

        $entity_type = $request->get_param( 'entity_type' );
        $entity_id = $request->get_param( 'entity_id' );

        $where = "media_type = 'photo'";
        $values = array();

        if ( $entity_type && $entity_id ) {
            $where .= ' AND entity_type = %s AND entity_id = %d';
            $values[] = $entity_type;
            $values[] = (int) $entity_id;
        }

        $query = "SELECT m.*, u.display_name as uploaded_by_name
                  FROM {$this->media_table} m
                  LEFT JOIN {$wpdb->users} u ON m.uploaded_by = u.ID
                  WHERE {$where} ORDER BY m.created_at DESC";

        $photos = ! empty( $values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
            : $wpdb->get_results( $query );

        foreach ( $photos as $photo ) {
            $photo->url = $this->get_file_url( $photo->file_path );
            $photo->thumbnail_url = $this->get_thumbnail_url( $photo->file_path );
        }

        return rest_ensure_response( $photos );
    }

    public function upload_photo( $request ) {
        global $wpdb;

        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', 'No image file uploaded', array( 'status' => 400 ) );
        }

        $file = $files['file'];
        $allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

        if ( ! in_array( $file['type'], $allowed, true ) ) {
            return new WP_Error( 'invalid_type', 'Invalid image format', array( 'status' => 400 ) );
        }

        $upload = $this->save_file( $file, 'photos' );

        if ( is_wp_error( $upload ) ) {
            return $upload;
        }

        // Get image dimensions
        $size = getimagesize( $upload['file_path'] );

        // Create thumbnail
        $this->create_thumbnail( $upload['file_path'] );

        // Parse EXIF for GPS
        $exif = @exif_read_data( $upload['file_path'] );
        $gps = $this->parse_gps_from_exif( $exif );

        $data = array(
            'media_type'        => 'photo',
            'entity_type'       => sanitize_text_field( $request->get_param( 'entity_type' ) ),
            'entity_id'         => (int) $request->get_param( 'entity_id' ),
            'file_name'         => $upload['file_name'],
            'file_path'         => $upload['file_path'],
            'file_size'         => $file['size'],
            'mime_type'         => $file['type'],
            'title'             => sanitize_text_field( $request->get_param( 'title' ) ),
            'description'       => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'width'             => $size[0] ?? null,
            'height'            => $size[1] ?? null,
            'latitude'          => $gps['lat'] ?? (float) $request->get_param( 'latitude' ),
            'longitude'         => $gps['lng'] ?? (float) $request->get_param( 'longitude' ),
            'location_accuracy' => (float) $request->get_param( 'accuracy' ),
            'captured_at'       => $this->get_capture_date( $exif ) ?? current_time( 'mysql' ),
            'metadata'          => wp_json_encode( $exif ),
            'uploaded_by'       => get_current_user_id(),
        );

        // Reverse geocode if we have coordinates
        if ( $data['latitude'] && $data['longitude'] ) {
            $data['location_address'] = $this->reverse_geocode( $data['latitude'], $data['longitude'] );
        }

        $wpdb->insert( $this->media_table, $data );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $wpdb->insert_id,
            'url'     => $upload['url'],
        ) );
    }

    public function get_photo( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $photo = $wpdb->get_row( $wpdb->prepare(
            "SELECT m.*, u.display_name as uploaded_by_name
             FROM {$this->media_table} m
             LEFT JOIN {$wpdb->users} u ON m.uploaded_by = u.ID
             WHERE m.id = %d AND m.media_type = 'photo'",
            $id
        ) );

        if ( ! $photo ) {
            return new WP_Error( 'not_found', 'Photo not found', array( 'status' => 404 ) );
        }

        $photo->url = $this->get_file_url( $photo->file_path );
        $photo->thumbnail_url = $this->get_thumbnail_url( $photo->file_path );
        $photo->metadata = json_decode( $photo->metadata, true );

        return rest_ensure_response( $photo );
    }

    public function update_photo( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $data = array();

        $title = $request->get_param( 'title' );
        if ( null !== $title ) {
            $data['title'] = sanitize_text_field( $title );
        }

        $description = $request->get_param( 'description' );
        if ( null !== $description ) {
            $data['description'] = sanitize_textarea_field( $description );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'no_data', 'No data to update', array( 'status' => 400 ) );
        }

        $wpdb->update( $this->media_table, $data, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function delete_photo( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $photo = $wpdb->get_row( $wpdb->prepare(
            "SELECT file_path FROM {$this->media_table} WHERE id = %d", $id
        ) );

        if ( $photo ) {
            if ( file_exists( $photo->file_path ) ) {
                unlink( $photo->file_path );
            }
            $thumb = $this->get_thumbnail_path( $photo->file_path );
            if ( file_exists( $thumb ) ) {
                unlink( $thumb );
            }
        }

        $wpdb->delete( $this->media_table, array( 'id' => $id ) );

        return rest_ensure_response( array( 'success' => true ) );
    }

    // Signatures
    public function get_signatures( $request ) {
        global $wpdb;

        $entity_type = $request->get_param( 'entity_type' );
        $entity_id = $request->get_param( 'entity_id' );

        $where = '1=1';
        $values = array();

        if ( $entity_type && $entity_id ) {
            $where = 'entity_type = %s AND entity_id = %d';
            $values[] = $entity_type;
            $values[] = (int) $entity_id;
        }

        $query = "SELECT s.*, u.display_name as captured_by_name
                  FROM {$this->signatures_table} s
                  LEFT JOIN {$wpdb->users} u ON s.captured_by = u.ID
                  WHERE {$where} ORDER BY s.signed_at DESC";

        $signatures = ! empty( $values )
            ? $wpdb->get_results( $wpdb->prepare( $query, $values ) )
            : $wpdb->get_results( $query );

        foreach ( $signatures as $sig ) {
            $sig->signature_image_url = $this->get_file_url( $sig->signature_image_path );
            unset( $sig->signature_data ); // Don't expose raw data
        }

        return rest_ensure_response( $signatures );
    }

    public function capture_signature( $request ) {
        global $wpdb;

        $signature_data = $request->get_param( 'signature_data' );

        if ( empty( $signature_data ) ) {
            return new WP_Error( 'no_signature', 'No signature data provided', array( 'status' => 400 ) );
        }

        // Generate verification token
        $token = wp_generate_password( 32, false );

        // Save signature image
        $image_path = $this->save_signature_image( $signature_data, $token );

        $data = array(
            'entity_type'          => sanitize_text_field( $request->get_param( 'entity_type' ) ),
            'entity_id'            => (int) $request->get_param( 'entity_id' ),
            'signature_type'       => sanitize_text_field( $request->get_param( 'signature_type' ) ?: 'acknowledgment' ),
            'signatory_name'       => sanitize_text_field( $request->get_param( 'signatory_name' ) ),
            'signatory_email'      => sanitize_email( $request->get_param( 'signatory_email' ) ),
            'signatory_title'      => sanitize_text_field( $request->get_param( 'signatory_title' ) ),
            'signature_data'       => $signature_data,
            'signature_image_path' => $image_path,
            'verification_token'   => $token,
            'ip_address'           => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'           => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'latitude'             => (float) $request->get_param( 'latitude' ),
            'longitude'            => (float) $request->get_param( 'longitude' ),
            'captured_by'          => get_current_user_id(),
        );

        $wpdb->insert( $this->signatures_table, $data );
        $signature_id = $wpdb->insert_id;

        // Send verification email if provided
        if ( $data['signatory_email'] ) {
            $this->send_signature_confirmation( $data, $token );
        }

        do_action( 'ict_signature_captured', $signature_id, $data );

        return rest_ensure_response( array(
            'success'            => true,
            'id'                 => $signature_id,
            'verification_token' => $token,
            'verification_url'   => rest_url( "ict/v1/media/signatures/verify/{$token}" ),
        ) );
    }

    public function get_signature( $request ) {
        global $wpdb;
        $id = (int) $request->get_param( 'id' );

        $signature = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, u.display_name as captured_by_name
             FROM {$this->signatures_table} s
             LEFT JOIN {$wpdb->users} u ON s.captured_by = u.ID
             WHERE s.id = %d",
            $id
        ) );

        if ( ! $signature ) {
            return new WP_Error( 'not_found', 'Signature not found', array( 'status' => 404 ) );
        }

        $signature->signature_image_url = $this->get_file_url( $signature->signature_image_path );
        unset( $signature->signature_data );

        return rest_ensure_response( $signature );
    }

    public function verify_signature( $request ) {
        global $wpdb;
        $token = sanitize_text_field( $request->get_param( 'token' ) );

        $signature = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, entity_type, entity_id, signature_type, signatory_name,
                    signatory_email, signatory_title, signed_at, ip_address,
                    latitude, longitude
             FROM {$this->signatures_table}
             WHERE verification_token = %s",
            $token
        ) );

        if ( ! $signature ) {
            return new WP_Error( 'invalid_token', 'Invalid verification token', array( 'status' => 404 ) );
        }

        return rest_ensure_response( array(
            'valid'     => true,
            'signature' => $signature,
        ) );
    }

    public function get_entity_media( $request ) {
        global $wpdb;

        $entity_type = sanitize_text_field( $request->get_param( 'entity_type' ) );
        $entity_id = (int) $request->get_param( 'entity_id' );

        $media = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->media_table}
             WHERE entity_type = %s AND entity_id = %d
             ORDER BY created_at DESC",
            $entity_type, $entity_id
        ) );

        foreach ( $media as $item ) {
            $item->url = $this->get_file_url( $item->file_path );
            if ( $item->media_type === 'photo' ) {
                $item->thumbnail_url = $this->get_thumbnail_url( $item->file_path );
            }
        }

        $signatures = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, signature_type, signatory_name, signed_at
             FROM {$this->signatures_table}
             WHERE entity_type = %s AND entity_id = %d
             ORDER BY signed_at DESC",
            $entity_type, $entity_id
        ) );

        return rest_ensure_response( array(
            'media'      => $media,
            'signatures' => $signatures,
        ) );
    }

    // Helper methods
    private function save_file( $file, $subfolder ) {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/ict-media/' . $subfolder . '/' . date( 'Y/m' ) . '/';

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        $ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $new_name = uniqid() . '-' . time() . '.' . $ext;
        $file_path = $target_dir . $new_name;

        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new WP_Error( 'upload_failed', 'Failed to save file', array( 'status' => 500 ) );
        }

        return array(
            'file_name' => $new_name,
            'file_path' => $file_path,
            'url'       => $this->get_file_url( $file_path ),
        );
    }

    private function get_file_url( $file_path ) {
        $upload_dir = wp_upload_dir();
        return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
    }

    private function get_thumbnail_path( $file_path ) {
        $info = pathinfo( $file_path );
        return $info['dirname'] . '/' . $info['filename'] . '-thumb.' . $info['extension'];
    }

    private function get_thumbnail_url( $file_path ) {
        return $this->get_file_url( $this->get_thumbnail_path( $file_path ) );
    }

    private function create_thumbnail( $file_path, $max_width = 300, $max_height = 300 ) {
        $image = wp_get_image_editor( $file_path );

        if ( is_wp_error( $image ) ) {
            return;
        }

        $image->resize( $max_width, $max_height, false );
        $thumb_path = $this->get_thumbnail_path( $file_path );
        $image->save( $thumb_path );
    }

    private function parse_gps_from_exif( $exif ) {
        if ( empty( $exif['GPSLatitude'] ) || empty( $exif['GPSLongitude'] ) ) {
            return null;
        }

        $lat = $this->gps_to_decimal( $exif['GPSLatitude'], $exif['GPSLatitudeRef'] );
        $lng = $this->gps_to_decimal( $exif['GPSLongitude'], $exif['GPSLongitudeRef'] );

        return array( 'lat' => $lat, 'lng' => $lng );
    }

    private function gps_to_decimal( $gps, $ref ) {
        $degrees = count( $gps ) > 0 ? $this->gps_value( $gps[0] ) : 0;
        $minutes = count( $gps ) > 1 ? $this->gps_value( $gps[1] ) : 0;
        $seconds = count( $gps ) > 2 ? $this->gps_value( $gps[2] ) : 0;

        $decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );

        if ( $ref === 'S' || $ref === 'W' ) {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    private function gps_value( $value ) {
        $parts = explode( '/', $value );
        if ( count( $parts ) === 2 ) {
            return (float) $parts[0] / (float) $parts[1];
        }
        return (float) $value;
    }

    private function get_capture_date( $exif ) {
        if ( ! empty( $exif['DateTimeOriginal'] ) ) {
            return date( 'Y-m-d H:i:s', strtotime( $exif['DateTimeOriginal'] ) );
        }
        return null;
    }

    private function reverse_geocode( $lat, $lng ) {
        // Would use geocoding service
        return null;
    }

    private function save_signature_image( $data, $token ) {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/ict-media/signatures/' . date( 'Y/m' ) . '/';

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        $file_path = $target_dir . $token . '.png';

        // Convert base64 to image
        if ( strpos( $data, 'data:image' ) === 0 ) {
            $data = explode( ',', $data )[1];
        }

        file_put_contents( $file_path, base64_decode( $data ) );

        return $file_path;
    }

    private function send_signature_confirmation( $data, $token ) {
        $verify_url = rest_url( "ict/v1/media/signatures/verify/{$token}" );

        $subject = 'Signature Confirmation';
        $message = sprintf(
            "Hello %s,\n\nYour signature has been recorded on %s.\n\n" .
            "To verify this signature, visit: %s\n\nThank you.",
            $data['signatory_name'],
            current_time( 'mysql' ),
            $verify_url
        );

        wp_mail( $data['signatory_email'], $subject, $message );
    }
}
