<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Activity Feed REST API Controller
 *
 * Handles activity logging and audit trail.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class ActivityController extends AbstractController
{
    /**
     * REST base for this controller.
     *
     * @var string
     */
    protected string $rest_base = 'activity';

    /**
     * Register routes for activity feed.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // GET /activity - Get activity feed
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => [$this, 'canViewProjects'],
                'args'                => $this->getCollectionParams(),
            ]
        );

        // GET /activity/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /activity/entity/{type}/{id} - Get activity for entity
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/entity/(?P<entity_type>[a-z_-]+)/(?P<entity_id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getEntityActivity'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /activity/user/{user_id} - Get activity for user
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/user/(?P<user_id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getUserActivity'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /activity/my - Get current user's activity
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/my',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getMyActivity'],
                'permission_callback' => '__return_true',
            ]
        );

        // GET /activity/summary - Get activity summary
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/summary',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSummary'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // GET /activity/timeline - Get timeline view
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/timeline',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getTimeline'],
                'permission_callback' => [$this, 'canViewProjects'],
            ]
        );

        // DELETE /activity/cleanup - Cleanup old activity
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/cleanup',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'cleanupOldActivity'],
                'permission_callback' => [$this, 'isAdmin'],
            ]
        );
    }

    /**
     * Get activity feed.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $page     = (int) $request->get_param('page') ?: 1;
        $per_page = min((int) $request->get_param('per_page') ?: 50, 100);
        $offset   = ($page - 1) * $per_page;

        $where_clauses = ['1=1'];
        $where_values  = [];

        if ($action = $request->get_param('action')) {
            $where_clauses[] = 'a.action = %s';
            $where_values[]  = sanitize_text_field($action);
        }

        if ($entity_type = $request->get_param('entity_type')) {
            $where_clauses[] = 'a.entity_type = %s';
            $where_values[]  = sanitize_text_field($entity_type);
        }

        if ($user_id = $request->get_param('user_id')) {
            $where_clauses[] = 'a.user_id = %d';
            $where_values[]  = (int) $user_id;
        }

        if ($date_from = $request->get_param('date_from')) {
            $where_clauses[] = 'a.created_at >= %s';
            $where_values[]  = sanitize_text_field($date_from);
        }

        if ($date_to = $request->get_param('date_to')) {
            $where_clauses[] = 'a.created_at <= %s';
            $where_values[]  = sanitize_text_field($date_to);
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM " . ICT_ACTIVITY_LOG_TABLE . " a WHERE " . $where_sql;
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, ...$where_values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Get activity with user info
        $query = "SELECT a.*, u.display_name as user_name, u.user_email
                  FROM " . ICT_ACTIVITY_LOG_TABLE . " a
                  LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                  WHERE " . $where_sql . "
                  ORDER BY a.created_at DESC
                  LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($query, ...$query_values));

        // Parse JSON fields
        foreach ($items as &$item) {
            if ($item->old_values) {
                $item->old_values = json_decode($item->old_values, true);
            }
            if ($item->new_values) {
                $item->new_values = json_decode($item->new_values, true);
            }
            $item->user_avatar = get_avatar_url($item->user_id, ['size' => 40]);
        }

        return $this->paginated($items ?: [], $total, $page, $per_page);
    }

    /**
     * Get a single activity entry.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $activity = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name
                 FROM " . ICT_ACTIVITY_LOG_TABLE . " a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE a.id = %d",
                $id
            )
        );

        if (!$activity) {
            return $this->error('not_found', 'Activity not found', 404);
        }

        // Parse JSON fields
        if ($activity->old_values) {
            $activity->old_values = json_decode($activity->old_values, true);
        }
        if ($activity->new_values) {
            $activity->new_values = json_decode($activity->new_values, true);
        }

        return $this->success($activity);
    }

    /**
     * Get activity for an entity.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getEntityActivity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $entity_type = sanitize_text_field($request->get_param('entity_type'));
        $entity_id = (int) $request->get_param('entity_id');
        $limit = min((int) ($request->get_param('limit') ?: 50), 200);

        $activity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name
                 FROM " . ICT_ACTIVITY_LOG_TABLE . " a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE a.entity_type = %s AND a.entity_id = %d
                 ORDER BY a.created_at DESC
                 LIMIT %d",
                $entity_type,
                $entity_id,
                $limit
            )
        );

        foreach ($activity as &$item) {
            if ($item->old_values) {
                $item->old_values = json_decode($item->old_values, true);
            }
            if ($item->new_values) {
                $item->new_values = json_decode($item->new_values, true);
            }
        }

        return $this->success($activity ?: []);
    }

    /**
     * Get activity for a user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getUserActivity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = (int) $request->get_param('user_id');
        $limit = min((int) ($request->get_param('limit') ?: 50), 200);

        $activity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_ACTIVITY_LOG_TABLE . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $limit
            )
        );

        return $this->success($activity ?: []);
    }

    /**
     * Get current user's activity.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getMyActivity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $limit = min((int) ($request->get_param('limit') ?: 50), 200);

        $activity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_ACTIVITY_LOG_TABLE . "
                 WHERE user_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $limit
            )
        );

        return $this->success($activity ?: []);
    }

    /**
     * Get activity summary.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getSummary(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $date_from = sanitize_text_field($request->get_param('date_from') ?: date('Y-m-d', strtotime('-7 days')));
        $date_to = sanitize_text_field($request->get_param('date_to') ?: date('Y-m-d'));

        // Activity by action
        $by_action = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, COUNT(*) as count
                 FROM " . ICT_ACTIVITY_LOG_TABLE . "
                 WHERE DATE(created_at) BETWEEN %s AND %s
                 GROUP BY action
                 ORDER BY count DESC",
                $date_from,
                $date_to
            )
        );

        // Activity by entity type
        $by_entity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT entity_type, COUNT(*) as count
                 FROM " . ICT_ACTIVITY_LOG_TABLE . "
                 WHERE DATE(created_at) BETWEEN %s AND %s
                 GROUP BY entity_type
                 ORDER BY count DESC",
                $date_from,
                $date_to
            )
        );

        // Activity by user
        $by_user = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.user_id, u.display_name, COUNT(*) as count
                 FROM " . ICT_ACTIVITY_LOG_TABLE . " a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE DATE(a.created_at) BETWEEN %s AND %s
                 GROUP BY a.user_id
                 ORDER BY count DESC
                 LIMIT 10",
                $date_from,
                $date_to
            )
        );

        // Activity by day
        $by_day = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM " . ICT_ACTIVITY_LOG_TABLE . "
                 WHERE DATE(created_at) BETWEEN %s AND %s
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                $date_from,
                $date_to
            )
        );

        // Total count
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . ICT_ACTIVITY_LOG_TABLE . "
                 WHERE DATE(created_at) BETWEEN %s AND %s",
                $date_from,
                $date_to
            )
        );

        return $this->success([
            'total'      => $total,
            'by_action'  => $by_action,
            'by_entity'  => $by_entity,
            'by_user'    => $by_user,
            'by_day'     => $by_day,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
        ]);
    }

    /**
     * Get timeline view.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getTimeline(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $date = sanitize_text_field($request->get_param('date') ?: date('Y-m-d'));

        $activity = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name
                 FROM " . ICT_ACTIVITY_LOG_TABLE . " a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE DATE(a.created_at) = %s
                 ORDER BY a.created_at DESC",
                $date
            )
        );

        // Group by hour
        $timeline = [];
        foreach ($activity as $item) {
            $hour = date('H:00', strtotime($item->created_at));
            if (!isset($timeline[$hour])) {
                $timeline[$hour] = [];
            }
            $item->user_avatar = get_avatar_url($item->user_id, ['size' => 32]);
            $timeline[$hour][] = $item;
        }

        return $this->success([
            'date'     => $date,
            'timeline' => $timeline,
            'total'    => count($activity),
        ]);
    }

    /**
     * Cleanup old activity entries.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function cleanupOldActivity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $days = (int) ($request->get_param('days') ?: 90);
        $cutoff_date = date('Y-m-d', strtotime('-' . $days . ' days'));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . ICT_ACTIVITY_LOG_TABLE . " WHERE DATE(created_at) < %s",
                $cutoff_date
            )
        );

        return $this->success([
            'deleted'     => $deleted,
            'cutoff_date' => $cutoff_date,
        ]);
    }

    /**
     * Check if user is admin.
     *
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }
}
