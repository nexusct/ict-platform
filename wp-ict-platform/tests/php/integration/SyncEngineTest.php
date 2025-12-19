<?php
/**
 * Integration tests for ICT_Sync_Engine class
 *
 * Note: These tests focus on testing the sync engine logic that can be tested
 * without a real database connection. Full integration tests would require
 * the WordPress test suite with a test database.
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
     * Test that ICT_Sync_Engine class exists and can be instantiated
     */
    public function test_sync_engine_exists(): void
    {
        if (!class_exists('ICT_Sync_Engine')) {
            $this->markTestSkipped('ICT_Sync_Engine class not loaded');
        }

        $engine = new \ICT_Sync_Engine();
        $this->assertInstanceOf(\ICT_Sync_Engine::class, $engine);
    }

    /**
     * Test that ICT_Helper queue_sync validates data structure
     */
    public function test_queue_sync_data_validation(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // This test verifies the queue_sync method accepts the expected data structure
        // Without a database, we can't fully test the insert, but we can verify
        // the method exists and accepts our parameters
        $this->assertTrue(method_exists(\ICT_Helper::class, 'queue_sync'));
        $this->assertTrue(method_exists(\ICT_Helper::class, 'log_sync'));
    }

    /**
     * Test that trigger_sync method exists on sync engine
     */
    public function test_trigger_sync_method_exists(): void
    {
        if (!class_exists('ICT_Sync_Engine')) {
            $this->markTestSkipped('ICT_Sync_Engine class not loaded');
        }

        $engine = new \ICT_Sync_Engine();
        $this->assertTrue(method_exists($engine, 'trigger_sync'));
        $this->assertTrue(method_exists($engine, 'process_sync_queue'));
    }

    /**
     * Test wp_parse_args mock works correctly for log_sync
     */
    public function test_wp_parse_args_integration(): void
    {
        // This tests that our bootstrap mocks work correctly
        $defaults = [
            'entity_type' => '',
            'entity_id' => null,
            'status' => 'pending',
        ];

        $args = [
            'entity_type' => 'project',
            'entity_id' => 123,
        ];

        $result = wp_parse_args($args, $defaults);

        $this->assertEquals('project', $result['entity_type']);
        $this->assertEquals(123, $result['entity_id']);
        $this->assertEquals('pending', $result['status']);
    }

    /**
     * Test get_option mock returns expected defaults
     */
    public function test_get_option_mock(): void
    {
        // Verify our mocks return expected values
        $this->assertEquals('USD', get_option('ict_currency', 'USD'));
        $this->assertEquals(8, get_option('ict_overtime_threshold', 8));
        $this->assertEquals('PRJ', get_option('ict_project_number_prefix', 'PRJ'));

        // Test fallback to default
        $this->assertEquals('fallback', get_option('nonexistent_option', 'fallback'));
    }

    /**
     * Test table constants are defined
     */
    public function test_table_constants_defined(): void
    {
        $this->assertTrue(defined('ICT_PROJECTS_TABLE'));
        $this->assertTrue(defined('ICT_TIME_ENTRIES_TABLE'));
        $this->assertTrue(defined('ICT_SYNC_QUEUE_TABLE'));
        $this->assertTrue(defined('ICT_SYNC_LOG_TABLE'));

        $this->assertEquals('wp_ict_projects', ICT_PROJECTS_TABLE);
        $this->assertEquals('wp_ict_sync_queue', ICT_SYNC_QUEUE_TABLE);
    }
}
