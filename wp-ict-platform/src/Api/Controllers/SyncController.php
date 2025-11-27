<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use ICT_Platform\Util\SyncLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Sync REST API Controller
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class SyncController extends AbstractController
{
    protected string $rest_base = 'sync';
    private SyncLogger $syncLogger;

    public function __construct(\ICT_Platform\Util\Helper $helper, \ICT_Platform\Util\Cache $cache, SyncLogger $syncLogger)
    {
        parent::__construct($helper, $cache);
        $this->syncLogger = $syncLogger;
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => [$this, 'permissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/trigger', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'triggerSync'],
            'permission_callback' => [$this, 'permissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/logs', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getLogs'],
            'permission_callback' => [$this, 'permissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/queue', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getQueue'],
            'permission_callback' => [$this, 'permissionsCheck'],
        ]);
    }

    public function getStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . ICT_SYNC_QUEUE_TABLE . " WHERE status = 'pending'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . ICT_SYNC_QUEUE_TABLE . " WHERE status = 'failed'");
        $lastSync = $wpdb->get_var("SELECT MAX(synced_at) FROM " . ICT_SYNC_LOG_TABLE);

        $services = [
            'crm'    => $this->getServiceStatus('crm'),
            'fsm'    => $this->getServiceStatus('fsm'),
            'books'  => $this->getServiceStatus('books'),
            'people' => $this->getServiceStatus('people'),
            'desk'   => $this->getServiceStatus('desk'),
        ];

        return $this->success([
            'pending_count' => $pending,
            'failed_count'  => $failed,
            'last_sync'     => $lastSync,
            'services'      => $services,
            'is_healthy'    => $failed < 10 && $pending < 100,
        ]);
    }

    public function triggerSync(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $entityType = $request->get_param('entity_type');
        $entityId = (int) $request->get_param('entity_id');
        $service = $request->get_param('service');

        if (!$entityType || !$entityId) {
            return $this->error('missing_params', __('Entity type and ID are required.', 'ict-platform'), 400);
        }

        $queueId = $this->syncLogger->queueSync([
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'action'       => 'sync',
            'zoho_service' => $service ?? 'crm',
            'priority'     => 1,
        ]);

        if (!$queueId) {
            return $this->error('queue_failed', __('Failed to queue sync.', 'ict-platform'), 500);
        }

        // Trigger immediate processing
        do_action('ict_platform_sync_job');

        return $this->success(['queued' => true, 'queue_id' => $queueId]);
    }

    public function getLogs(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $logs = $this->syncLogger->getLogs([
            'entity_type'  => $request->get_param('entity_type'),
            'zoho_service' => $request->get_param('service'),
            'status'       => $request->get_param('status'),
            'limit'        => (int) ($request->get_param('per_page') ?? 50),
            'offset'       => (int) ($request->get_param('offset') ?? 0),
        ]);

        return $this->success($logs);
    }

    public function getQueue(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $queue = $this->syncLogger->getPendingQueue((int) ($request->get_param('limit') ?? 50));

        return $this->success($queue);
    }

    public function permissionsCheck(): bool
    {
        return $this->canManageSync();
    }

    private function getServiceStatus(string $service): array
    {
        global $wpdb;

        $lastSuccess = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(synced_at) FROM " . ICT_SYNC_LOG_TABLE . " WHERE zoho_service = %s AND status = 'success'",
            $service
        ));

        $recentErrors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . ICT_SYNC_LOG_TABLE . "
            WHERE zoho_service = %s AND status = 'error' AND synced_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $service
        ));

        return [
            'connected'     => !empty(get_option("ict_zoho_{$service}_token")),
            'last_success'  => $lastSuccess,
            'recent_errors' => $recentErrors,
            'is_healthy'    => $recentErrors < 5,
        ];
    }
}
