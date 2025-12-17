<?php
/**
 * Unit tests for ICT_Data_Validator class
 *
 * @package ICT_Platform
 */

declare(strict_types=1);

namespace ICT_Platform\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test class for ICT_Data_Validator
 */
class DataValidatorTest extends TestCase
{
    /**
     * Test email validation
     */
    public function test_validate_email(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $validator = new \ICT_Data_Validator();

        // Valid email
        $this->assertTrue($validator->is_valid_email('test@example.com'));

        // Invalid emails
        $this->assertFalse($validator->is_valid_email('invalid-email'));
        $this->assertFalse($validator->is_valid_email(''));
    }

    /**
     * Test phone number validation
     */
    public function test_validate_phone(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $validator = new \ICT_Data_Validator();

        // Various phone formats should be accepted
        $this->assertTrue($validator->is_valid_phone('1234567890'));
        $this->assertTrue($validator->is_valid_phone('123-456-7890'));
        $this->assertTrue($validator->is_valid_phone('(123) 456-7890'));
    }

    /**
     * Test positive number validation
     */
    public function test_validate_positive_number(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $validator = new \ICT_Data_Validator();

        $this->assertTrue($validator->is_positive_number(1));
        $this->assertTrue($validator->is_positive_number(100.5));
        $this->assertFalse($validator->is_positive_number(-1));
        $this->assertFalse($validator->is_positive_number(0));
    }

    /**
     * Test date format validation
     */
    public function test_validate_date_format(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $validator = new \ICT_Data_Validator();

        // Valid dates
        $this->assertTrue($validator->is_valid_date('2024-01-01'));
        $this->assertTrue($validator->is_valid_date('2024-12-31'));

        // Invalid dates
        $this->assertFalse($validator->is_valid_date('invalid'));
        $this->assertFalse($validator->is_valid_date('01-01-2024')); // Wrong format
    }

    /**
     * Test required fields validation
     */
    public function test_validate_required_fields(): void
    {
        if (!class_exists('ICT_Data_Validator')) {
            $this->markTestSkipped('ICT_Data_Validator class not loaded');
        }

        $validator = new \ICT_Data_Validator();

        $data = [
            'name' => 'Test Project',
            'email' => 'test@example.com',
        ];

        $required = ['name', 'email'];
        $this->assertTrue($validator->has_required_fields($data, $required));

        $required_missing = ['name', 'email', 'phone'];
        $this->assertFalse($validator->has_required_fields($data, $required_missing));
    }
}
