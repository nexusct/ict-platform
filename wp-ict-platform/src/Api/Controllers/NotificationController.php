<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Push Notifications REST API Controller
 *
 * Handles notification management, push sending, and preferences.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class NotificationController extends AbstractController
{
    /**
     * REST base for this controller.
     *
     * @var string
     */
    protected string $rest_base = 'notifications';

    /**
     * Register routes for notifications.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // GET /notifications - Get user's notifications
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => '__return_true',
                'args'                => $this->getCollectionParams(),
            ]
        );

        // POST /notifications - Create notification (admin)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createItem'],
                'permission_callback' => [$this, 'canManageProjects'],
            ]
        );

        // GET /notifications/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => '__return_true',
            ]
        );

        // POST /notifications/{id}/read - Mark as read
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)/read',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'markAsRead'],
                'permission_callback' => '__return_true',
            ]
        );

        // POST /notifications/read-all - Mark all as read
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/read-all',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'markAllAsRead'],
                'permission_callback' => '__return_true',
            ]
        );

        // DELETE /notifications/{id} - Delete notification
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteItem'],
                'permission_callback' => '__return_true',
            ]
        );

        // GET /notifications/unread-count - Get unread count
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/unread-count',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getUnreadCount'],
                'permission_callback' => '__return_true',
            ]
        );

        // POST /notifications/send - Send notification to users
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/send',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'sendNotification'],
                'permission_callback' => [$this, 'canManageProjects'],
            ]
        );

        // POST /notifications/subscribe - Register push subscription
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/subscribe',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'subscribePush'],
                'permission_callback' => '__return_true',
            ]
        );

        // GET /notifications/preferences - Get notification preferences
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/preferences',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getPreferences'],
                'permission_callback' => '__return_true',
            ]
        );

        // PUT /notifications/preferences - Update notification preferences
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/preferences',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updatePreferences'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Get user's notifications.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $page     = (int) $request->get_param('page') ?: 1;
        $per_page = min((int) $request->get_param('per_page') ?: 20, 100);
        $offset   = ($page - 1) * $per_page;

        $where_clauses = ['user_id = %d'];
        $where_values  = [$user_id];

        $unread_only = $request->get_param('unread_only');
        if ($unread_only) {
            $where_clauses[] = 'is_read = 0';
        }

        if ($type = $request->get_param('type')) {
            $where_clauses[] = 'type = %s';
            $where_values[]  = sanitize_text_field($type);
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total count
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . ICT_NOTIFICATIONS_TABLE . " WHERE " . $where_sql,
                ...$where_values
            )
        );

        // Get notifications
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_NOTIFICATIONS_TABLE . "
                 WHERE " . $where_sql . "
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                ...$query_values
            )
        );

        // Parse JSON data
        foreach ($items as &$item) {
            if ($item->data) {
                $item->data = json_decode($item->data, true);
            }
        }

        return $this->paginated($items ?: [], $total, $page, $per_page);
    }

    /**
     * Get a single notification.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        $notification = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . ICT_NOTIFICATIONS_TABLE . " WHERE id = %d AND user_id = %d",
                $id,
                $user_id
            )
        );

        if (!$notification) {
            return $this->error('not_found', 'Notification not found', 404);
        }

        if ($notification->data) {
            $notification->data = json_decode($notification->data, true);
        }

        return $this->success($notification);
    }

    /**
     * Create a notification.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = (int) $request->get_param('user_id');
        if (!$user_id) {
            return $this->error('validation', 'User ID is required', 400);
        }

        $data = [
            'user_id'     => $user_id,
            'type'        => sanitize_text_field($request->get_param('type') ?: 'general'),
            'title'       => sanitize_text_field($request->get_param('title')),
            'message'     => sanitize_textarea_field($request->get_param('message')),
            'data'        => $request->get_param('data') ? wp_json_encode($request->get_param('data')) : null,
            'priority'    => sanitize_text_field($request->get_param('priority') ?: 'normal'),
            'action_url'  => esc_url_raw($request->get_param('action_url')),
            'entity_type' => sanitize_text_field($request->get_param('entity_type')),
            'entity_id'   => $request->get_param('entity_id') ? (int) $request->get_param('entity_id') : null,
        ];

        $result = $wpdb->insert(ICT_NOTIFICATIONS_TABLE, $data);

        if ($result === false) {
            return $this->error('insert_failed', 'Failed to create notification', 500);
        }

        $notification = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_NOTIFICATIONS_TABLE . " WHERE id = %d", $wpdb->insert_id)
        );

        return $this->success($notification, 201);
    }

    /**
     * Mark notification as read.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function markAsRead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        $result = $wpdb->update(
            ICT_NOTIFICATIONS_TABLE,
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            [
                'id'      => $id,
                'user_id' => $user_id,
            ]
        );

        if ($result === false) {
            return $this->error('update_failed', 'Failed to mark notification as read', 500);
        }

        return $this->success(['marked_read' => true]);
    }

    /**
     * Mark all notifications as read.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function markAllAsRead(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = get_current_user_id();

        $wpdb->update(
            ICT_NOTIFICATIONS_TABLE,
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            [
                'user_id' => $user_id,
                'is_read' => 0,
            ]
        );

        return $this->success(['marked_all_read' => true]);
    }

    /**
     * Delete a notification.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function deleteItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $user_id = get_current_user_id();

        $wpdb->delete(
            ICT_NOTIFICATIONS_TABLE,
            [
                'id'      => $id,
                'user_id' => $user_id,
            ]
        );

        return $this->success(['deleted' => true]);
    }

    /**
     * Get unread notification count.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getUnreadCount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_id = get_current_user_id();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . ICT_NOTIFICATIONS_TABLE . " WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );

        return $this->success(['unread_count' => $count]);
    }

    /**
     * Send notification to multiple users.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function sendNotification(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $user_ids = $request->get_param('user_ids');
        $role = $request->get_param('role');

        // Get target users
        $target_users = [];

        if ($user_ids && is_array($user_ids)) {
            $target_users = array_map('intval', $user_ids);
        } elseif ($role) {
            $users = get_users(['role' => sanitize_text_field($role)]);
            $target_users = wp_list_pluck($users, 'ID');
        } else {
            return $this->error('validation', 'Either user_ids or role is required', 400);
        }

        if (empty($target_users)) {
            return $this->error('no_users', 'No users found to notify', 400);
        }

        $notification_data = [
            'type'        => sanitize_text_field($request->get_param('type') ?: 'announcement'),
            'title'       => sanitize_text_field($request->get_param('title')),
            'message'     => sanitize_textarea_field($request->get_param('message')),
            'data'        => $request->get_param('data') ? wp_json_encode($request->get_param('data')) : null,
            'priority'    => sanitize_text_field($request->get_param('priority') ?: 'normal'),
            'action_url'  => esc_url_raw($request->get_param('action_url')),
            'entity_type' => sanitize_text_field($request->get_param('entity_type')),
            'entity_id'   => $request->get_param('entity_id') ? (int) $request->get_param('entity_id') : null,
        ];

        $created_count = 0;
        foreach ($target_users as $user_id) {
            $data = array_merge($notification_data, ['user_id' => $user_id]);
            if ($wpdb->insert(ICT_NOTIFICATIONS_TABLE, $data)) {
                $created_count++;
            }
        }

        return $this->success([
            'sent_count'   => $created_count,
            'target_count' => count($target_users),
        ]);
    }

    /**
     * Register push subscription.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function subscribePush(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();
        $subscription = $request->get_param('subscription');

        if (!$subscription) {
            return $this->error('validation', 'Push subscription data is required', 400);
        }

        // Store subscription in user meta
        $subscriptions = get_user_meta($user_id, 'ict_push_subscriptions', true) ?: [];

        // Check if subscription already exists
        $endpoint = $subscription['endpoint'] ?? '';
        $exists = false;
        foreach ($subscriptions as $sub) {
            if (($sub['endpoint'] ?? '') === $endpoint) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $subscriptions[] = $subscription;
            update_user_meta($user_id, 'ict_push_subscriptions', $subscriptions);
        }

        return $this->success(['subscribed' => true]);
    }

    /**
     * Get notification preferences.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function getPreferences(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();

        $defaults = [
            'email_notifications'    => true,
            'push_notifications'     => true,
            'time_approval'          => true,
            'expense_updates'        => true,
            'project_updates'        => true,
            'inventory_alerts'       => true,
            'sync_errors'            => true,
            'daily_digest'           => false,
            'quiet_hours_start'      => null,
            'quiet_hours_end'        => null,
        ];

        $preferences = get_user_meta($user_id, 'ict_notification_preferences', true);
        $preferences = $preferences ? array_merge($defaults, $preferences) : $defaults;

        return $this->success($preferences);
    }

    /**
     * Update notification preferences.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function updatePreferences(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user_id = get_current_user_id();

        $allowed_keys = [
            'email_notifications', 'push_notifications', 'time_approval',
            'expense_updates', 'project_updates', 'inventory_alerts',
            'sync_errors', 'daily_digest', 'quiet_hours_start', 'quiet_hours_end'
        ];

        $current = get_user_meta($user_id, 'ict_notification_preferences', true) ?: [];
        $params = $request->get_params();

        foreach ($allowed_keys as $key) {
            if (isset($params[$key])) {
                if (in_array($key, ['quiet_hours_start', 'quiet_hours_end'])) {
                    $current[$key] = sanitize_text_field($params[$key]);
                } else {
                    $current[$key] = (bool) $params[$key];
                }
            }
        }

        update_user_meta($user_id, 'ict_notification_preferences', $current);

        return $this->success($current);
    }
}
