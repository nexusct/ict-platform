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
        // Skip if ICT_Helper doesn't exist
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        $result = \ICT_Helper::format_currency(1234.56);
        $this->assertIsString($result);
        $this->assertStringContainsString('1234', $result);
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
    }

    /**
     * Test round_time method
     */
    public function test_round_time(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Test rounding to 15 minutes
        $result = \ICT_Helper::round_time(0.33, 15);
        $this->assertIsFloat($result);
    }

    /**
     * Test sanitize_coordinates method
     */
    public function test_sanitize_coordinates(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // Valid coordinates
        $coords = \ICT_Helper::sanitize_coordinates(40.7128, -74.0060);
        $this->assertIsArray($coords);
        $this->assertArrayHasKey('latitude', $coords);
        $this->assertArrayHasKey('longitude', $coords);
    }

    /**
     * Test generate_project_number method
     */
    public function test_generate_project_number(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        $number = \ICT_Helper::generate_project_number();
        $this->assertIsString($number);
        $this->assertNotEmpty($number);
    }

    /**
     * Test is_overtime method
     */
    public function test_is_overtime(): void
    {
        if (!class_exists('ICT_Helper')) {
            $this->markTestSkipped('ICT_Helper class not loaded');
        }

        // 10 hours should be overtime (assuming 8-hour day)
        $overtime = \ICT_Helper::is_overtime(10);
        $this->assertTrue($overtime);

        // 7 hours should not be overtime
        $regular = \ICT_Helper::is_overtime(7);
        $this->assertFalse($regular);
    }
}
