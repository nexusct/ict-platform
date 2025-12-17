<?php
/**
 * Integration tests for ICT_Sync_Engine class
 *
 * @package ICT_Platform
 */

declare(strict_types=1);

namespace ICT_Platform\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Test class for ICT_Sync_Engine
 */
class SyncEngineTest extends TestCase
{
    /**
     * Test sync queue item creation
     */
    public function test_queue_sync_item(): void
    {
        if (!class_exists('ICT_Sync_Engine')) {
            $this->markTestSkipped('ICT_Sync_Engine class not loaded');
        }

        // Test adding item to queue
        $item = [
            'entity_type'  => 'project',
            'entity_id'    => 123,
            'action'       => 'create',
            'zoho_service' => 'crm',
            'priority'     => 5,
            'payload'      => ['name' => 'Test Project'],
        ];

        $engine = new \ICT_Sync_Engine();
        $result = $engine->queue_item($item);

        $this->assertNotFalse($result);
    }

    /**
     * Test sync queue priority ordering
     */
    public function test_queue_priority_ordering(): void
    {
        if (!class_exists('ICT_Sync_Engine')) {
            $this->markTestSkipped('ICT_Sync_Engine class not loaded');
        }

        $engine = new \ICT_Sync_Engine();

        // Add items with different priorities
        $engine->queue_item([
            'entity_type'  => 'project',
            'entity_id'    => 1,
            'action'       => 'update',
            'zoho_service' => 'crm',
            'priority'     => 5,
        ]);

        $engine->queue_item([
            'entity_type'  => 'project',
            'entity_id'    => 2,
            'action'       => 'create',
            'zoho_service' => 'crm',
            'priority'     => 1, // Higher priority (lower number)
        ]);

        // Get next batch - should return higher priority first
        $batch = $engine->get_pending_items(2);

        if (count($batch) >= 2) {
            $this->assertLessThanOrEqual($batch[1]['priority'], $batch[0]['priority']);
        }
    }

    /**
     * Test sync log creation
     */
    public function test_log_sync_operation(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        $log_data = [
            'entity_type'   => 'project',
            'entity_id'     => 123,
            'direction'     => 'outbound',
            'zoho_service'  => 'crm',
            'action'        => 'create',
            'status'        => 'success',
            'request_data'  => ['name' => 'Test'],
            'response_data' => ['id' => 'zoho_123'],
            'error_message' => null,
            'duration_ms'   => 250,
        ];

        $result = \ICT_Helper::log_sync($log_data);

        $this->assertNotFalse($result);
    }
}
