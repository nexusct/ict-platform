<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Project REST API Controller
 *
 * Handles all project-related API endpoints.
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class ProjectController extends AbstractController
{
    /**
     * Route base
     */
    protected string $rest_base = 'projects';

    /**
     * Register routes
     */
    public function registerRoutes(): void
    {
        // GET /projects
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => [$this, 'getItemsPermissionsCheck'],
                'args'                => $this->getCollectionParams(),
            ]
        );

        // POST /projects
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createItem'],
                'permission_callback' => [$this, 'createItemPermissionsCheck'],
                'args'                => $this->getCreateParams(),
            ]
        );

        // GET /projects/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => [$this, 'getItemPermissionsCheck'],
                'args'                => [
                    'id' => [
                        'description' => __('Unique identifier for the project.', 'ict-platform'),
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                ],
            ]
        );

        // PUT/PATCH /projects/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateItem'],
                'permission_callback' => [$this, 'updateItemPermissionsCheck'],
                'args'                => $this->getUpdateParams(),
            ]
        );

        // DELETE /projects/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'deleteItemPermissionsCheck'],
                'args'                => [
                    'id' => [
                        'description' => __('Unique identifier for the project.', 'ict-platform'),
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Get collection of projects
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function getItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $pagination = $this->getPaginationParams($request);
        $status = $request->get_param('status');
        $search = $request->get_param('search');

        $where = ['1=1'];
        $values = [];

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        if ($search) {
            $where[] = '(project_name LIKE %s OR project_number LIKE %s)';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // Get total count
        $countSql = "SELECT COUNT(*) FROM " . ICT_PROJECTS_TABLE . " WHERE " . implode(' AND ', $where);
        $total = (int) $wpdb->get_var($wpdb->prepare($countSql, $values));

        // Get items
        $sql = "SELECT * FROM " . ICT_PROJECTS_TABLE . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d";

        $values[] = $pagination['per_page'];
        $values[] = $pagination['offset'];

        $projects = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Transform data
        $items = array_map([$this, 'prepareItem'], $projects);

        return $this->paginated($items, $total, $pagination['page'], $pagination['per_page']);
    }

    /**
     * Get a single project
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');

        $project = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
                $id
            )
        );

        if (!$project) {
            return $this->error('not_found', __('Project not found.', 'ict-platform'), 404);
        }

        return $this->success($this->prepareItem($project));
    }

    /**
     * Create a new project
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $validation = $this->validate($request, [
            'project_name' => ['required' => true, 'type' => 'string', 'min' => 1, 'max' => 255],
            'client_name'  => ['type' => 'string', 'max' => 255],
            'status'       => ['enum' => ['draft', 'active', 'completed', 'cancelled']],
        ]);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $data = $this->sanitize([
            'project_name'   => $request->get_param('project_name'),
            'project_number' => $this->helper->generateProjectNumber(),
            'client_name'    => $request->get_param('client_name') ?? '',
            'status'         => $request->get_param('status') ?? 'draft',
            'description'    => $request->get_param('description') ?? '',
            'start_date'     => $request->get_param('start_date'),
            'end_date'       => $request->get_param('end_date'),
            'budget'         => $request->get_param('budget') ?? 0,
            'created_by'     => $this->getCurrentUserId(),
        ], [
            'project_name'   => 'string',
            'project_number' => 'string',
            'client_name'    => 'string',
            'status'         => 'string',
            'description'    => 'textarea',
            'budget'         => 'float',
            'created_by'     => 'int',
        ]);

        $result = $wpdb->insert(
            ICT_PROJECTS_TABLE,
            $data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d']
        );

        if (!$result) {
            return $this->error('create_failed', __('Failed to create project.', 'ict-platform'), 500);
        }

        $project = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d",
                $wpdb->insert_id
            )
        );

        return $this->success($this->prepareItem($project), 201);
    }

    /**
     * Update a project
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');

        // Check if project exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d", $id)
        );

        if (!$exists) {
            return $this->error('not_found', __('Project not found.', 'ict-platform'), 404);
        }

        $data = [];
        $format = [];

        $fields = ['project_name', 'client_name', 'status', 'description', 'start_date', 'end_date', 'budget'];

        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
                $format[] = $field === 'budget' ? '%f' : '%s';
            }
        }

        if (empty($data)) {
            return $this->error('no_data', __('No data to update.', 'ict-platform'), 400);
        }

        $data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $wpdb->update(
            ICT_PROJECTS_TABLE,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($result === false) {
            return $this->error('update_failed', __('Failed to update project.', 'ict-platform'), 500);
        }

        $project = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_PROJECTS_TABLE . " WHERE id = %d", $id)
        );

        return $this->success($this->prepareItem($project));
    }

    /**
     * Delete a project
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function deleteItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');

        $result = $wpdb->delete(
            ICT_PROJECTS_TABLE,
            ['id' => $id],
            ['%d']
        );

        if (!$result) {
            return $this->error('delete_failed', __('Failed to delete project.', 'ict-platform'), 500);
        }

        return $this->success(['deleted' => true]);
    }

    /**
     * Permission check for getting items
     *
     * @return bool
     */
    public function getItemsPermissionsCheck(): bool
    {
        return $this->canViewProjects();
    }

    /**
     * Permission check for getting single item
     *
     * @return bool
     */
    public function getItemPermissionsCheck(): bool
    {
        return $this->canViewProjects();
    }

    /**
     * Permission check for creating item
     *
     * @return bool
     */
    public function createItemPermissionsCheck(): bool
    {
        return $this->canManageProjects();
    }

    /**
     * Permission check for updating item
     *
     * @return bool
     */
    public function updateItemPermissionsCheck(): bool
    {
        return $this->canManageProjects();
    }

    /**
     * Permission check for deleting item
     *
     * @return bool
     */
    public function deleteItemPermissionsCheck(): bool
    {
        return $this->canManageProjects();
    }

    /**
     * Prepare a project item for response
     *
     * @param object $project Raw project object
     * @return array<string, mixed>
     */
    private function prepareItem(object $project): array
    {
        return [
            'id'             => (int) $project->id,
            'project_number' => $project->project_number,
            'project_name'   => $project->project_name,
            'client_name'    => $project->client_name,
            'status'         => $project->status,
            'description'    => $project->description ?? '',
            'start_date'     => $project->start_date,
            'end_date'       => $project->end_date,
            'budget'         => (float) ($project->budget ?? 0),
            'created_by'     => (int) $project->created_by,
            'created_at'     => $project->created_at,
            'updated_at'     => $project->updated_at,
        ];
    }

    /**
     * Get collection parameters
     *
     * @return array<string, array<string, mixed>>
     */
    private function getCollectionParams(): array
    {
        return [
            'page'     => [
                'description' => __('Current page of the collection.', 'ict-platform'),
                'type'        => 'integer',
                'default'     => 1,
                'minimum'     => 1,
            ],
            'per_page' => [
                'description' => __('Maximum number of items per page.', 'ict-platform'),
                'type'        => 'integer',
                'default'     => 20,
                'minimum'     => 1,
                'maximum'     => 100,
            ],
            'status'   => [
                'description' => __('Filter by project status.', 'ict-platform'),
                'type'        => 'string',
                'enum'        => ['draft', 'active', 'completed', 'cancelled'],
            ],
            'search'   => [
                'description' => __('Search term.', 'ict-platform'),
                'type'        => 'string',
            ],
        ];
    }

    /**
     * Get create parameters
     *
     * @return array<string, array<string, mixed>>
     */
    private function getCreateParams(): array
    {
        return [
            'project_name' => [
                'description' => __('Project name.', 'ict-platform'),
                'type'        => 'string',
                'required'    => true,
            ],
            'client_name'  => [
                'description' => __('Client name.', 'ict-platform'),
                'type'        => 'string',
            ],
            'status'       => [
                'description' => __('Project status.', 'ict-platform'),
                'type'        => 'string',
                'enum'        => ['draft', 'active', 'completed', 'cancelled'],
                'default'     => 'draft',
            ],
            'description'  => [
                'description' => __('Project description.', 'ict-platform'),
                'type'        => 'string',
            ],
            'start_date'   => [
                'description' => __('Project start date.', 'ict-platform'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'end_date'     => [
                'description' => __('Project end date.', 'ict-platform'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'budget'       => [
                'description' => __('Project budget.', 'ict-platform'),
                'type'        => 'number',
            ],
        ];
    }

    /**
     * Get update parameters
     *
     * @return array<string, array<string, mixed>>
     */
    private function getUpdateParams(): array
    {
        $params = $this->getCreateParams();
        $params['id'] = [
            'description' => __('Unique identifier for the project.', 'ict-platform'),
            'type'        => 'integer',
            'required'    => true,
        ];
        unset($params['project_name']['required']);
        return $params;
    }
}
