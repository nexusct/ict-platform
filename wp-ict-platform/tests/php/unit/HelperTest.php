<?php
/**
 * Unit tests for ICT_Helper class
 *
 * @package ICT_Platform
 */

declare(strict_types=1);

namespace ICT_Platform\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test class for ICT_Helper utility functions
 */
class HelperTest extends TestCase
{
    /**
     * Test format_currency method
     */
    public function test_format_currency(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Test with default currency (USD from mock get_option)
        $result = \ICT_Helper::format_currency(1234.56);
        $this->assertIsString($result);
        $this->assertStringContainsString('1,234.56', $result);
        $this->assertStringContainsString('$', $result);

        // Test with explicit currency
        $result = \ICT_Helper::format_currency(1000, 'EUR');
        $this->assertStringContainsString('€', $result);
        $this->assertStringContainsString('1,000.00', $result);
    }

    /**
     * Test calculate_hours method
     */
    public function test_calculate_hours(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Test 1 hour difference
        $start = '2024-01-01 09:00:00';
        $end = '2024-01-01 10:00:00';
        $hours = \ICT_Helper::calculate_hours($start, $end);
        $this->assertEquals(1.0, $hours);

        // Test 2.5 hours difference
        $start = '2024-01-01 09:00:00';
        $end = '2024-01-01 11:30:00';
        $hours = \ICT_Helper::calculate_hours($start, $end);
        $this->assertEquals(2.5, $hours);

        // Test invalid input (end before start)
        $hours = \ICT_Helper::calculate_hours('2024-01-01 10:00:00', '2024-01-01 09:00:00');
        $this->assertEquals(0, $hours);
    }

    /**
     * Test round_time method - takes a datetime string, returns rounded datetime string
     */
    public function test_round_time(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Test rounding to 15 minutes - 9:07 should round to 9:00
        $result = \ICT_Helper::round_time('2024-01-01 09:07:00', 15);
        $this->assertIsString($result);
        $this->assertStringContainsString('09:00:00', $result);

        // 9:08 should round to 9:15
        $result = \ICT_Helper::round_time('2024-01-01 09:08:00', 15);
        $this->assertStringContainsString('09:15:00', $result);

        // 9:23 should round to 9:30
        $result = \ICT_Helper::round_time('2024-01-01 09:23:00', 15);
        $this->assertStringContainsString('09:30:00', $result);

        // Test rounding near hour boundary - 9:53 should round to 10:00
        $result = \ICT_Helper::round_time('2024-01-01 09:53:00', 15);
        $this->assertStringContainsString('10:00:00', $result);
    }

    /**
     * Test sanitize_coordinates method - takes string or array, returns string or null
     */
    public function test_sanitize_coordinates(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Valid coordinates as string
        $coords = \ICT_Helper::sanitize_coordinates('40.7128, -74.0060');
        $this->assertIsString($coords);
        $this->assertStringContainsString('40.7128', $coords);
        $this->assertStringContainsString('-74.006', $coords);

        // Valid coordinates as array
        $coords = \ICT_Helper::sanitize_coordinates([40.7128, -74.0060]);
        $this->assertIsString($coords);

        // Invalid latitude (out of range)
        $coords = \ICT_Helper::sanitize_coordinates('91.0, -74.0060');
        $this->assertNull($coords);

        // Invalid longitude (out of range)
        $coords = \ICT_Helper::sanitize_coordinates('40.7128, -181.0');
        $this->assertNull($coords);

        // Invalid format
        $coords = \ICT_Helper::sanitize_coordinates('invalid');
        $this->assertNull($coords);
    }

    /**
     * Test sanitize_sync_status method
     */
    public function test_sanitize_sync_status(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Valid statuses
        $this->assertEquals('pending', \ICT_Helper::sanitize_sync_status('pending'));
        $this->assertEquals('syncing', \ICT_Helper::sanitize_sync_status('syncing'));
        $this->assertEquals('synced', \ICT_Helper::sanitize_sync_status('synced'));
        $this->assertEquals('error', \ICT_Helper::sanitize_sync_status('error'));
        $this->assertEquals('conflict', \ICT_Helper::sanitize_sync_status('conflict'));

        // Invalid status should return 'pending'
        $this->assertEquals('pending', \ICT_Helper::sanitize_sync_status('invalid'));
        $this->assertEquals('pending', \ICT_Helper::sanitize_sync_status(''));
    }
}
