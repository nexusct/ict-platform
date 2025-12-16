<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Purchase Order REST API Controller
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class PurchaseOrderController extends AbstractController
{
    protected string $rest_base = 'purchase-orders';

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getItems'],
            'permission_callback' => [$this, 'getItemsPermissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'createItem'],
            'permission_callback' => [$this, 'createItemPermissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getItem'],
            'permission_callback' => [$this, 'getItemsPermissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'updateItem'],
            'permission_callback' => [$this, 'updateItemPermissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/approve', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'approveOrder'],
            'permission_callback' => [$this, 'approvePermissionsCheck'],
        ]);
    }

    public function getItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $pagination = $this->getPaginationParams($request);
        $status = $request->get_param('status');

        $where = ['1=1'];
        $values = [];

        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . ICT_PURCHASE_ORDERS_TABLE . " WHERE " . implode(' AND ', $where),
            $values
        ));

        $values[] = $pagination['per_page'];
        $values[] = $pagination['offset'];

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . ICT_PURCHASE_ORDERS_TABLE . "
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $values
        ));

        return $this->paginated(array_map([$this, 'prepareItem'], $orders), $total, $pagination['page'], $pagination['per_page']);
    }

    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . ICT_PURCHASE_ORDERS_TABLE . " WHERE id = %d",
            (int) $request->get_param('id')
        ));

        if (!$order) {
            return $this->error('not_found', __('Purchase order not found.', 'ict-platform'), 404);
        }

        return $this->success($this->prepareItem($order));
    }

    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $data = [
            'po_number'     => $this->helper->generatePoNumber(),
            'vendor_name'   => sanitize_text_field($request->get_param('vendor_name')),
            'project_id'    => (int) ($request->get_param('project_id') ?? 0),
            'total_amount'  => (float) ($request->get_param('total_amount') ?? 0),
            'status'        => 'draft',
            'created_by'    => $this->getCurrentUserId(),
            'notes'         => sanitize_textarea_field($request->get_param('notes') ?? ''),
        ];

        $result = $wpdb->insert(ICT_PURCHASE_ORDERS_TABLE, $data);

        if (!$result) {
            return $this->error('create_failed', __('Failed to create purchase order.', 'ict-platform'), 500);
        }

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . ICT_PURCHASE_ORDERS_TABLE . " WHERE id = %d",
            $wpdb->insert_id
        ));

        return $this->success($this->prepareItem($order), 201);
    }

    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $data = [];

        foreach (['vendor_name', 'total_amount', 'status', 'notes'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $field === 'total_amount' ? (float) $value : sanitize_text_field($value);
            }
        }

        if (empty($data)) {
            return $this->error('no_data', __('No data to update.', 'ict-platform'), 400);
        }

        $wpdb->update(ICT_PURCHASE_ORDERS_TABLE, $data, ['id' => $id]);

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . ICT_PURCHASE_ORDERS_TABLE . " WHERE id = %d", $id));

        return $this->success($this->prepareItem($order));
    }

    public function approveOrder(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');

        $wpdb->update(
            ICT_PURCHASE_ORDERS_TABLE,
            ['status' => 'approved', 'approved_by' => $this->getCurrentUserId(), 'approved_at' => current_time('mysql')],
            ['id' => $id]
        );

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . ICT_PURCHASE_ORDERS_TABLE . " WHERE id = %d", $id));

        return $this->success($this->prepareItem($order));
    }

    public function getItemsPermissionsCheck(): bool { return current_user_can('manage_ict_purchase_orders'); }
    public function createItemPermissionsCheck(): bool { return current_user_can('manage_ict_purchase_orders'); }
    public function updateItemPermissionsCheck(): bool { return current_user_can('manage_ict_purchase_orders'); }
    public function approvePermissionsCheck(): bool { return current_user_can('approve_ict_purchase_orders'); }

    private function prepareItem(object $order): array
    {
        return [
            'id'           => (int) $order->id,
            'po_number'    => $order->po_number,
            'vendor_name'  => $order->vendor_name,
            'project_id'   => (int) ($order->project_id ?? 0),
            'total_amount' => (float) ($order->total_amount ?? 0),
            'status'       => $order->status,
            'notes'        => $order->notes ?? '',
            'created_by'   => (int) $order->created_by,
            'approved_by'  => $order->approved_by ? (int) $order->approved_by : null,
            'created_at'   => $order->created_at,
            'approved_at'  => $order->approved_at,
        ];
    }
}
