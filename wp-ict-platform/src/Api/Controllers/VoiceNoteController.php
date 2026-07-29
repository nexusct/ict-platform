<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Voice Notes REST API Controller
 *
 * Handles voice note recording, storage, and transcription.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class VoiceNoteController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'voice-notes';

	/**
	 * Maximum file size (20MB).
	 *
	 * @var int
	 */
	private int $max_file_size = 20 * 1024 * 1024;

	/**
	 * Register routes for voice notes.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /voice-notes - List all voice notes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItems' ),
					'permission_callback' => array( $this, 'canViewProjects' ),
					'args'                => $this->getCollectionParams(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createItem' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET/DELETE /voice-notes/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getItem' ),
					'permission_callback' => array( $this, 'canViewProjects' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'deleteItem' ),
					'permission_callback' => array( $this, 'canManageProjects' ),
				),
			)
		);

		// POST /voice-notes/upload - Upload audio file
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'uploadAudio' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /voice-notes/project/{project_id} - Get voice notes for project
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/project/(?P<project_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProjectVoiceNotes' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// POST /voice-notes/{id}/transcribe - Request transcription
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/transcribe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'requestTranscription' ),
				'permission_callback' => array( $this, 'canManageProjects' ),
			)
		);

		// PUT /voice-notes/{id}/transcription - Update transcription
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/transcription',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateTranscription' ),
				'permission_callback' => array( $this, 'canManageProjects' ),
			)
		);

		// GET /voice-notes/my - Get current user's voice notes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/my',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getMyVoiceNotes' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get all voice notes.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItems( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$page     = (int) $request->get_param( 'page' ) ?: 1;
		$per_page = min( (int) $request->get_param( 'per_page' ) ?: 20, 100 );
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$where_values  = array();

		if ( $project_id = $request->get_param( 'project_id' ) ) {
			$where_clauses[] = 'v.project_id = %d';
			$where_values[]  = (int) $project_id;
		}

		if ( $recorded_by = $request->get_param( 'recorded_by' ) ) {
			$where_clauses[] = 'v.recorded_by = %d';
			$where_values[]  = (int) $recorded_by;
		}

		if ( $search = $request->get_param( 'search' ) ) {
			$where_clauses[] = '(v.transcription LIKE %s OR v.tags LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count
		$count_sql = 'SELECT COUNT(*) FROM ' . ICT_VOICE_NOTES_TABLE . ' v WHERE ' . $where_sql;
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get voice notes with user info
		$query        = 'SELECT v.*, u.display_name as recorded_by_name
                  FROM ' . ICT_VOICE_NOTES_TABLE . " v
                  LEFT JOIN {$wpdb->users} u ON v.recorded_by = u.ID
                  WHERE " . $where_sql . '
                  ORDER BY v.created_at DESC
                  LIMIT %d OFFSET %d';
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

		// Add audio URLs
		$upload_dir = wp_upload_dir();
		foreach ( $items as &$item ) {
			if ( $item->audio_path ) {
				$item->audio_url = $upload_dir['baseurl'] . $item->audio_path;
			}
		}

		return $this->paginated( $items ?: array(), $total, $page, $per_page );
	}

	/**
	 * Get a single voice note.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id         = (int) $request->get_param( 'id' );
		$voice_note = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT v.*, u.display_name as recorded_by_name
                 FROM ' . ICT_VOICE_NOTES_TABLE . " v
                 LEFT JOIN {$wpdb->users} u ON v.recorded_by = u.ID
                 WHERE v.id = %d",
				$id
			)
		);

		if ( ! $voice_note ) {
			return $this->error( 'not_found', 'Voice note not found', 404 );
		}

		if ( $voice_note->audio_path ) {
			$upload_dir            = wp_upload_dir();
			$voice_note->audio_url = $upload_dir['baseurl'] . $voice_note->audio_path;
		}

		return $this->success( $voice_note );
	}

	/**
	 * Create a new voice note record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function createItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$audio_path = sanitize_text_field( $request->get_param( 'audio_path' ) );

		if ( ! $audio_path ) {
			return $this->error( 'validation', 'Audio path is required. Upload the audio file first.', 400 );
		}

		$data = array(
			'project_id'           => $request->get_param( 'project_id' ) ? (int) $request->get_param( 'project_id' ) : null,
			'entity_type'          => sanitize_text_field( $request->get_param( 'entity_type' ) ?: 'project' ),
			'entity_id'            => $request->get_param( 'entity_id' ) ? (int) $request->get_param( 'entity_id' ) : null,
			'recorded_by'          => get_current_user_id(),
			'audio_path'           => $audio_path,
			'duration_seconds'     => (int) $request->get_param( 'duration_seconds' ),
			'file_size'            => (int) $request->get_param( 'file_size' ),
			'transcription_status' => 'pending',
			'location_latitude'    => $request->get_param( 'latitude' ) ? (float) $request->get_param( 'latitude' ) : null,
			'location_longitude'   => $request->get_param( 'longitude' ) ? (float) $request->get_param( 'longitude' ) : null,
			'tags'                 => sanitize_text_field( $request->get_param( 'tags' ) ),
		);

		$result = $wpdb->insert( ICT_VOICE_NOTES_TABLE, $data );

		if ( $result === false ) {
			return $this->error( 'insert_failed', 'Failed to create voice note record', 500 );
		}

		$voice_note = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_VOICE_NOTES_TABLE . ' WHERE id = %d', $wpdb->insert_id )
		);

		// Add audio URL
		$upload_dir            = wp_upload_dir();
		$voice_note->audio_url = $upload_dir['baseurl'] . $voice_note->audio_path;

		return $this->success( $voice_note, 201 );
	}

	/**
	 * Upload audio file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function uploadAudio( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();

		if ( empty( $files['audio'] ) ) {
			return $this->error( 'no_file', 'No audio file uploaded', 400 );
		}

		$file = $files['audio'];

		// Validate file type
		$allowed_types = array(
			'audio/mpeg',
			'audio/mp3',
			'audio/wav',
			'audio/x-wav',
			'audio/webm',
			'audio/ogg',
			'audio/mp4',
			'audio/m4a',
			'audio/x-m4a',
		);

		if ( ! in_array( $file['type'], $allowed_types ) ) {
			return $this->error( 'invalid_type', 'Only audio files are allowed (MP3, WAV, WebM, OGG, M4A)', 400 );
		}

		// Check file size
		if ( $file['size'] > $this->max_file_size ) {
			return $this->error( 'file_too_large', 'File size exceeds 20MB limit', 400 );
		}

		// Set up upload directory
		$upload_dir = wp_upload_dir();
		$audio_dir  = $upload_dir['basedir'] . '/ict-platform/voice-notes/' . date( 'Y/m' );

		if ( ! file_exists( $audio_dir ) ) {
			wp_mkdir_p( $audio_dir );
		}

		// Generate unique filename
		$ext       = pathinfo( $file['name'], PATHINFO_EXTENSION ) ?: 'mp3';
		$filename  = 'vn_' . get_current_user_id() . '_' . time() . '_' . uniqid() . '.' . $ext;
		$file_path = $audio_dir . '/' . $filename;

		// Move uploaded file
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return $this->error( 'upload_failed', 'Failed to move uploaded file', 500 );
		}

		// Get relative path for storage
		$relative_path = str_replace( $upload_dir['basedir'], '', $file_path );

		return $this->success(
			array(
				'audio_path' => $relative_path,
				'file_size'  => $file['size'],
				'url'        => $upload_dir['baseurl'] . $relative_path,
			),
			201
		);
	}

	/**
	 * Delete a voice note.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deleteItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id         = (int) $request->get_param( 'id' );
		$voice_note = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_VOICE_NOTES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $voice_note ) {
			return $this->error( 'not_found', 'Voice note not found', 404 );
		}

		// Delete audio file
		if ( $voice_note->audio_path ) {
			$upload_dir = wp_upload_dir();
			$file_path  = $upload_dir['basedir'] . $voice_note->audio_path;
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}
		}

		$wpdb->delete( ICT_VOICE_NOTES_TABLE, array( 'id' => $id ) );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * Get voice notes for a project.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProjectVoiceNotes( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$project_id = (int) $request->get_param( 'project_id' );

		$voice_notes = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT v.*, u.display_name as recorded_by_name
                 FROM ' . ICT_VOICE_NOTES_TABLE . " v
                 LEFT JOIN {$wpdb->users} u ON v.recorded_by = u.ID
                 WHERE v.project_id = %d
                 ORDER BY v.created_at DESC",
				$project_id
			)
		);

		// Add audio URLs
		$upload_dir = wp_upload_dir();
		foreach ( $voice_notes as &$note ) {
			if ( $note->audio_path ) {
				$note->audio_url = $upload_dir['baseurl'] . $note->audio_path;
			}
		}

		return $this->success( $voice_notes ?: array() );
	}

	/**
	 * Request transcription for a voice note.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function requestTranscription( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id         = (int) $request->get_param( 'id' );
		$voice_note = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_VOICE_NOTES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $voice_note ) {
			return $this->error( 'not_found', 'Voice note not found', 404 );
		}

		// Update status to processing
		$wpdb->update(
			ICT_VOICE_NOTES_TABLE,
			array( 'transcription_status' => 'processing' ),
			array( 'id' => $id )
		);

		// Queue transcription job (in real implementation, this would call a transcription service)
		// For now, we'll just mark it as pending manual transcription
		// You would integrate with services like Whisper, AWS Transcribe, Google Speech-to-Text, etc.

		return $this->success(
			array(
				'status'  => 'processing',
				'message' => 'Transcription request queued. This may take a few minutes.',
			)
		);
	}

	/**
	 * Update transcription for a voice note.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateTranscription( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id         = (int) $request->get_param( 'id' );
		$voice_note = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_VOICE_NOTES_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $voice_note ) {
			return $this->error( 'not_found', 'Voice note not found', 404 );
		}

		$transcription = $request->get_param( 'transcription' );

		if ( $transcription === null ) {
			return $this->error( 'validation', 'Transcription text is required', 400 );
		}

		$wpdb->update(
			ICT_VOICE_NOTES_TABLE,
			array(
				'transcription'        => sanitize_textarea_field( $transcription ),
				'transcription_status' => 'completed',
			),
			array( 'id' => $id )
		);

		$voice_note = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_VOICE_NOTES_TABLE . ' WHERE id = %d', $id )
		);

		return $this->success( $voice_note );
	}

	/**
	 * Get current user's voice notes.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getMyVoiceNotes( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$user_id = get_current_user_id();

		$voice_notes = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . ICT_VOICE_NOTES_TABLE . '
                 WHERE recorded_by = %d
                 ORDER BY created_at DESC
                 LIMIT 50',
				$user_id
			)
		);

		// Add audio URLs
		$upload_dir = wp_upload_dir();
		foreach ( $voice_notes as &$note ) {
			if ( $note->audio_path ) {
				$note->audio_url = $upload_dir['baseurl'] . $note->audio_path;
			}
		}

		return $this->success( $voice_notes ?: array() );
	}
}
