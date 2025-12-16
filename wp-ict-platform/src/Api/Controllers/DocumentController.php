<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Document Management REST API Controller
 *
 * Handles document upload, management, and retrieval.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class DocumentController extends AbstractController
{
    /**
     * REST base for this controller.
     *
     * @var string
     */
    protected string $rest_base = 'documents';

    /**
     * Allowed file types for upload.
     *
     * @var array
     */
    private array $allowed_types = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'zip'  => 'application/zip',
    ];

    /**
     * Register routes for documents.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // GET /documents - List all documents
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
                    'permission_callback' => [$this, 'canManageProjects'],
                ],
            ]
        );

        // GET/PUT/DELETE /documents/{id}
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
                    'permission_callback' => [$this, 'canManageProjects'],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'deleteItem'],
                    'permission_callback' => [$this, 'canManageProjects'],
                ],
            ]
        );

        // POST /documents/upload - Handle file upload
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/upload',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'uploadDocument'],
                'permission_callback' => [$this, 'canManageProjects'],
            ]
        );

        // GET /documents/project/{project_id} - Get documents for a project
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/project/(?P<project_id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getProjectDocuments'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /documents/categories - Get document categories
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/categories',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getCategories'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );
    }

    /**
     * Get all documents with filtering and pagination.
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

        if ($project_id = $request->get_param('project_id')) {
            $where_clauses[] = 'project_id = %d';
            $where_values[]  = (int) $project_id;
        }

        if ($category = $request->get_param('category')) {
            $where_clauses[] = 'category = %s';
            $where_values[]  = sanitize_text_field($category);
        }

        if ($search = $request->get_param('search')) {
            $where_clauses[] = '(document_name LIKE %s OR original_filename LIKE %s)';
            $search_term     = '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%';
            $where_values[]  = $search_term;
            $where_values[]  = $search_term;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM " . ICT_DOCUMENTS_TABLE . " WHERE " . $where_sql;
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, ...$where_values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Get documents
        $query = "SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE " . $where_sql . " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($query, ...$query_values));

        return $this->paginated($items ?: [], $total, $page, $per_page);
    }

    /**
     * Get a single document.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $document = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE id = %d", $id)
        );

        if (!$document) {
            return $this->error('not_found', 'Document not found', 404);
        }

        return $this->success($document);
    }

    /**
     * Create a new document record.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $data = [
            'project_id'        => $request->get_param('project_id') ? (int) $request->get_param('project_id') : null,
            'entity_type'       => sanitize_text_field($request->get_param('entity_type') ?: 'project'),
            'entity_id'         => $request->get_param('entity_id') ? (int) $request->get_param('entity_id') : null,
            'document_name'     => sanitize_text_field($request->get_param('document_name')),
            'original_filename' => sanitize_file_name($request->get_param('original_filename')),
            'file_path'         => sanitize_text_field($request->get_param('file_path')),
            'file_type'         => sanitize_text_field($request->get_param('file_type')),
            'file_size'         => (int) $request->get_param('file_size'),
            'mime_type'         => sanitize_mime_type($request->get_param('mime_type')),
            'category'          => sanitize_text_field($request->get_param('category') ?: 'general'),
            'description'       => sanitize_textarea_field($request->get_param('description')),
            'is_public'         => (int) (bool) $request->get_param('is_public'),
            'uploaded_by'       => get_current_user_id(),
            'tags'              => sanitize_text_field($request->get_param('tags')),
        ];

        $result = $wpdb->insert(ICT_DOCUMENTS_TABLE, $data);

        if ($result === false) {
            return $this->error('insert_failed', 'Failed to create document record', 500);
        }

        $document = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE id = %d", $wpdb->insert_id)
        );

        $this->logActivity('create', 'document', $document->id, $document->document_name);

        return $this->success($document, 201);
    }

    /**
     * Upload a document file.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function uploadDocument(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return $this->error('no_file', 'No file uploaded', 400);
        }

        $file = $files['file'];

        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($this->allowed_types[$ext])) {
            return $this->error('invalid_type', 'File type not allowed', 400);
        }

        // Check file size (max 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            return $this->error('file_too_large', 'File size exceeds 50MB limit', 400);
        }

        // Set up upload directory
        $upload_dir = wp_upload_dir();
        $ict_dir = $upload_dir['basedir'] . '/ict-platform/documents/' . date('Y/m');

        if (!file_exists($ict_dir)) {
            wp_mkdir_p($ict_dir);
        }

        // Generate unique filename
        $filename = wp_unique_filename($ict_dir, sanitize_file_name($file['name']));
        $file_path = $ict_dir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return $this->error('upload_failed', 'Failed to move uploaded file', 500);
        }

        // Get relative path for storage
        $relative_path = str_replace($upload_dir['basedir'], '', $file_path);

        return $this->success([
            'file_path'         => $relative_path,
            'original_filename' => $file['name'],
            'file_type'         => $ext,
            'file_size'         => $file['size'],
            'mime_type'         => $file['type'],
            'url'               => $upload_dir['baseurl'] . $relative_path,
        ], 201);
    }

    /**
     * Update a document.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE id = %d", $id)
        );

        if (!$existing) {
            return $this->error('not_found', 'Document not found', 404);
        }

        $data = [];
        $updateable = ['document_name', 'category', 'description', 'is_public', 'tags'];

        foreach ($updateable as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                $data[$field] = is_string($value) ? sanitize_text_field($value) : $value;
            }
        }

        if (empty($data)) {
            return $this->error('no_data', 'No data to update', 400);
        }

        $wpdb->update(ICT_DOCUMENTS_TABLE, $data, ['id' => $id]);

        $document = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE id = %d", $id)
        );

        $this->logActivity('update', 'document', $id, $document->document_name);

        return $this->success($document);
    }

    /**
     * Delete a document.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function deleteItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $document = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE id = %d", $id)
        );

        if (!$document) {
            return $this->error('not_found', 'Document not found', 404);
        }

        // Delete file from disk
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . $document->file_path;
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete record
        $wpdb->delete(ICT_DOCUMENTS_TABLE, ['id' => $id]);

        $this->logActivity('delete', 'document', $id, $document->document_name);

        return $this->success(['deleted' => true]);
    }

    /**
     * Get documents for a specific project.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getProjectDocuments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $project_id = (int) $request->get_param('project_id');

        $documents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_DOCUMENTS_TABLE . " WHERE project_id = %d ORDER BY created_at DESC",
                $project_id
            )
        );

        return $this->success($documents ?: []);
    }

    /**
     * Get document categories.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getCategories(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $categories = [
            'general'     => 'General Documents',
            'contract'    => 'Contracts & Agreements',
            'permit'      => 'Permits & Licenses',
            'invoice'     => 'Invoices & Billing',
            'photo'       => 'Photos & Images',
            'drawing'     => 'Drawings & Plans',
            'report'      => 'Reports',
            'safety'      => 'Safety Documents',
            'warranty'    => 'Warranties',
            'manual'      => 'Manuals & Guides',
        ];

        return $this->success($categories);
    }

    /**
     * Log activity for audit trail.
     *
     * @param string $action Action performed.
     * @param string $entity_type Entity type.
     * @param int $entity_id Entity ID.
     * @param string $entity_name Entity name.
     * @return void
     */
    private function logActivity(string $action, string $entity_type, int $entity_id, string $entity_name): void
    {
        global $wpdb;

        $wpdb->insert(ICT_ACTIVITY_LOG_TABLE, [
            'user_id'     => get_current_user_id(),
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'entity_name' => $entity_name,
            'description' => sprintf('%s %s: %s', ucfirst($action), $entity_type, $entity_name),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}
