<?php
/**
 * Unit tests for ICT_Data_Validator class
 *
 * @package ICT_Platform
 */

declare(strict_types=1);

namespace ICT_Platform\Tests\Unit;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Test class for ICT_Data_Validator
 */
class DataValidatorTest extends TestCase
{
    /**
     * Test required_string validation
     */
    public function test_required_string(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        // Valid string
        $result = \ICT_Data_Validator::required_string('Hello World');
        $this->assertEquals('Hello World', $result);

        // String with HTML tags should be stripped
        $result = \ICT_Data_Validator::required_string('<p>Hello</p>');
        $this->assertEquals('Hello', $result);

        // Empty string should throw exception
        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::required_string('');
    }

    /**
     * Test required_string max length
     */
    public function test_required_string_max_length(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        // String within max length
        $result = \ICT_Data_Validator::required_string('Short', 10);
        $this->assertEquals('Short', $result);

        // String exceeding max length should throw
        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::required_string('This is a very long string', 10);
    }

    /**
     * Test optional_string validation
     */
    public function test_optional_string(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        // Valid string
        $result = \ICT_Data_Validator::optional_string('Hello World');
        $this->assertEquals('Hello World', $result);

        // Empty string should return null
        $result = \ICT_Data_Validator::optional_string('');
        $this->assertNull($result);

        // Null should return null
        $result = \ICT_Data_Validator::optional_string(null);
        $this->assertNull($result);
    }

    /**
     * Test non_negative_decimal validation
     */
    public function test_non_negative_decimal(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        // Valid positive number
        $result = \ICT_Data_Validator::non_negative_decimal(100.55);
        $this->assertEquals(100.55, $result);

        // Zero is valid
        $result = \ICT_Data_Validator::non_negative_decimal(0);
        $this->assertEquals(0.0, $result);

        // Empty value returns 0
        $result = \ICT_Data_Validator::non_negative_decimal('');
        $this->assertEquals(0.0, $result);

        // Negative number should throw exception
        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::non_negative_decimal(-1);
    }

    /**
     * Test id validation
     */
    public function test_id(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        // Valid positive integer
        $result = \ICT_Data_Validator::id(123);
        $this->assertEquals(123, $result);

        // String number should be converted
        $result = \ICT_Data_Validator::id('456');
        $this->assertEquals(456, $result);

        // Zero should throw exception
        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::id(0);
    }

    /**
     * Test enum validation
     */
    public function test_enum(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $allowed = ['active', 'inactive', 'pending'];

        // Valid value
        $result = \ICT_Data_Validator::enum('active', $allowed);
        $this->assertEquals('active', $result);

        // Case insensitive - should be lowercased
        $result = \ICT_Data_Validator::enum('ACTIVE', $allowed);
        $this->assertEquals('active', $result);

        // Invalid value should throw exception
        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::enum('invalid', $allowed);
    }

    /**
     * Test iso_datetime validation
     */
    public function test_iso_datetime(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        // Valid datetime
        $result = \ICT_Data_Validator::iso_datetime('2024-01-01 12:00:00');
        $this->assertEquals('2024-01-01 12:00:00', $result);

        // ISO format
        $result = \ICT_Data_Validator::iso_datetime('2024-01-01T12:00:00');
        $this->assertStringContainsString('2024-01-01', $result);

        // Empty non-required returns null
        $result = \ICT_Data_Validator::iso_datetime('', false);
        $this->assertNull($result);

        // Empty required throws exception
        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::iso_datetime('', true);
    }

    /**
     * Test iso_datetime with invalid format
     */
    public function test_iso_datetime_invalid(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $this->expectException(InvalidArgumentException::class);
        \ICT_Data_Validator::iso_datetime('not-a-date');
    }
}
