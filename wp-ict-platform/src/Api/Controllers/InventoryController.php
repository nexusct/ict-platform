<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Inventory REST API Controller
 *
 * Handles all inventory-related API endpoints.
 *
 * @package ICT_Platform\Api\Controllers
 * @since   2.0.0
 */
class InventoryController extends AbstractController
{
    protected string $rest_base = 'inventory';

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getItems'],
            'permission_callback' => [$this, 'getItemsPermissionsCheck'],
            'args'                => $this->getCollectionParams(),
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

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/adjust', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'adjustStock'],
            'permission_callback' => [$this, 'updateItemPermissionsCheck'],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/low-stock', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getLowStock'],
            'permission_callback' => [$this, 'getItemsPermissionsCheck'],
        ]);
    }

    public function getItems(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $pagination = $this->getPaginationParams($request);
        $category = $request->get_param('category');
        $search = $request->get_param('search');

        $where = ['is_active = 1'];
        $values = [];

        if ($category) {
            $where[] = 'category = %s';
            $values[] = $category;
        }

        if ($search) {
            $where[] = '(item_name LIKE %s OR sku LIKE %s)';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $countSql = "SELECT COUNT(*) FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE " . implode(' AND ', $where);
        $total = (int) $wpdb->get_var($wpdb->prepare($countSql, $values));

        $sql = "SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY item_name ASC
                LIMIT %d OFFSET %d";

        $values[] = $pagination['per_page'];
        $values[] = $pagination['offset'];

        $items = $wpdb->get_results($wpdb->prepare($sql, $values));

        return $this->paginated(
            array_map([$this, 'prepareItem'], $items),
            $total,
            $pagination['page'],
            $pagination['per_page']
        );
    }

    public function getItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE id = %d", (int) $request->get_param('id'))
        );

        if (!$item) {
            return $this->error('not_found', __('Item not found.', 'ict-platform'), 404);
        }

        return $this->success($this->prepareItem($item));
    }

    public function createItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $data = [
            'item_name'          => sanitize_text_field($request->get_param('item_name')),
            'sku'                => sanitize_text_field($request->get_param('sku') ?? ''),
            'category'           => sanitize_text_field($request->get_param('category') ?? ''),
            'quantity_available' => (int) ($request->get_param('quantity_available') ?? 0),
            'quantity_reserved'  => 0,
            'unit_cost'          => (float) ($request->get_param('unit_cost') ?? 0),
            'reorder_level'      => (int) ($request->get_param('reorder_level') ?? 10),
            'is_active'          => 1,
        ];

        $result = $wpdb->insert(ICT_INVENTORY_ITEMS_TABLE, $data);

        if (!$result) {
            return $this->error('create_failed', __('Failed to create item.', 'ict-platform'), 500);
        }

        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE id = %d", $wpdb->insert_id)
        );

        return $this->success($this->prepareItem($item), 201);
    }

    public function updateItem(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $data = [];

        foreach (['item_name', 'sku', 'category', 'unit_cost', 'reorder_level'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = in_array($field, ['unit_cost']) ? (float) $value : (in_array($field, ['reorder_level']) ? (int) $value : sanitize_text_field($value));
            }
        }

        if (empty($data)) {
            return $this->error('no_data', __('No data to update.', 'ict-platform'), 400);
        }

        $result = $wpdb->update(ICT_INVENTORY_ITEMS_TABLE, $data, ['id' => $id]);

        if ($result === false) {
            return $this->error('update_failed', __('Failed to update item.', 'ict-platform'), 500);
        }

        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE id = %d", $id)
        );

        return $this->success($this->prepareItem($item));
    }

    public function adjustStock(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $id = (int) $request->get_param('id');
        $adjustment = (int) $request->get_param('adjustment');
        $reason = sanitize_text_field($request->get_param('reason') ?? '');

        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE id = %d", $id)
        );

        if (!$item) {
            return $this->error('not_found', __('Item not found.', 'ict-platform'), 404);
        }

        $newQuantity = max(0, (int) $item->quantity_available + $adjustment);

        $wpdb->update(
            ICT_INVENTORY_ITEMS_TABLE,
            ['quantity_available' => $newQuantity],
            ['id' => $id]
        );

        // Log the adjustment
        do_action('ict_stock_adjusted', $id, $adjustment, $reason, $this->getCurrentUserId());

        $item = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . " WHERE id = %d", $id)
        );

        return $this->success($this->prepareItem($item));
    }

    public function getLowStock(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;

        $items = $wpdb->get_results(
            "SELECT * FROM " . ICT_INVENTORY_ITEMS_TABLE . "
            WHERE is_active = 1 AND quantity_available <= reorder_level
            ORDER BY quantity_available ASC"
        );

        return $this->success(array_map([$this, 'prepareItem'], $items));
    }

    public function getItemsPermissionsCheck(): bool { return $this->canManageInventory(); }
    public function createItemPermissionsCheck(): bool { return $this->canManageInventory(); }
    public function updateItemPermissionsCheck(): bool { return $this->canManageInventory(); }

    private function prepareItem(object $item): array
    {
        return [
            'id'                 => (int) $item->id,
            'item_name'          => $item->item_name,
            'sku'                => $item->sku ?? '',
            'category'           => $item->category ?? '',
            'quantity_available' => (int) $item->quantity_available,
            'quantity_reserved'  => (int) ($item->quantity_reserved ?? 0),
            'unit_cost'          => (float) ($item->unit_cost ?? 0),
            'reorder_level'      => (int) ($item->reorder_level ?? 10),
            'is_low_stock'       => (int) $item->quantity_available <= (int) ($item->reorder_level ?? 10),
            'is_active'          => (bool) $item->is_active,
        ];
    }

    private function getCollectionParams(): array
    {
        return [
            'page'     => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 20],
            'category' => ['type' => 'string'],
            'search'   => ['type' => 'string'],
        ];
    }
}
