<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;

/**
 * @internal
 */
#[CoversClass(StockValidationResult::class)]
final class StockValidationResultTest extends TestCase
{
    public function testStockValidationResultCanBeInstantiated(): void
    {
        $result = new StockValidationResult(true);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals([], $result->getWarnings());
        $this->assertEquals([], $result->getDetails());
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
    }

    public function testStockValidationResultWithAllParameters(): void
    {
        $errors = ['SKU_001' => 'Out of stock'];
        $warnings = ['SKU_002' => 'Low stock'];
        $details = ['checked_at' => '2024-01-15', 'method' => 'real_time'];

        $result = new StockValidationResult(false, $errors, $warnings, $details);

        $this->assertFalse($result->isValid());
        $this->assertEquals($errors, $result->getErrors());
        $this->assertEquals($warnings, $result->getWarnings());
        $this->assertEquals($details, $result->getDetails());
        $this->assertTrue($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
    }

    public function testIsValid(): void
    {
        $validResult = new StockValidationResult(true);
        $invalidResult = new StockValidationResult(false);

        $this->assertTrue($validResult->isValid());
        $this->assertFalse($invalidResult->isValid());
    }

    public function testGetErrors(): void
    {
        $errors = [
            'SKU_001' => 'Insufficient stock',
            'SKU_002' => 'Product discontinued',
        ];
        $result = new StockValidationResult(false, $errors);

        $this->assertEquals($errors, $result->getErrors());
    }

    public function testGetErrorsWithDefaultValue(): void
    {
        $result = new StockValidationResult(true);

        $this->assertEquals([], $result->getErrors());
        $this->assertIsArray($result->getErrors());
    }

    public function testGetWarnings(): void
    {
        $warnings = [
            'SKU_003' => 'Only 2 items left',
            'SKU_004' => 'Delivery may be delayed',
        ];
        $result = new StockValidationResult(true, [], $warnings);

        $this->assertEquals($warnings, $result->getWarnings());
    }

    public function testGetWarningsWithDefaultValue(): void
    {
        $result = new StockValidationResult(true);

        $this->assertEquals([], $result->getWarnings());
        $this->assertIsArray($result->getWarnings());
    }

    public function testGetDetails(): void
    {
        $details = [
            'validation_timestamp' => '2024-01-15T10:30:00Z',
            'warehouse' => 'WH001',
            'method' => 'batch_check',
        ];
        $result = new StockValidationResult(true, [], [], $details);

        $this->assertEquals($details, $result->getDetails());
    }

    public function testGetDetailsWithDefaultValue(): void
    {
        $result = new StockValidationResult(true);

        $this->assertEquals([], $result->getDetails());
        $this->assertIsArray($result->getDetails());
    }

    public function testHasErrors(): void
    {
        $resultWithErrors = new StockValidationResult(false, ['SKU_001' => 'Error']);
        $resultWithoutErrors = new StockValidationResult(true);

        $this->assertTrue($resultWithErrors->hasErrors());
        $this->assertFalse($resultWithoutErrors->hasErrors());
    }

    public function testHasErrorsWithEmptyArray(): void
    {
        $result = new StockValidationResult(true, []);

        $this->assertFalse($result->hasErrors());
    }

    public function testHasWarnings(): void
    {
        $resultWithWarnings = new StockValidationResult(true, [], ['SKU_001' => 'Warning']);
        $resultWithoutWarnings = new StockValidationResult(true);

        $this->assertTrue($resultWithWarnings->hasWarnings());
        $this->assertFalse($resultWithoutWarnings->hasWarnings());
    }

    public function testHasWarningsWithEmptyArray(): void
    {
        $result = new StockValidationResult(true, [], []);

        $this->assertFalse($result->hasWarnings());
    }

    public function testSuccessStaticMethod(): void
    {
        $result = StockValidationResult::success();

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals([], $result->getWarnings());
        $this->assertEquals([], $result->getDetails());
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
    }

    public function testSuccessWithDetails(): void
    {
        $details = ['validated_items' => 5, 'validation_time' => '2024-01-15'];
        $result = StockValidationResult::success($details);

        $this->assertTrue($result->isValid());
        $this->assertEquals($details, $result->getDetails());
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
    }

    public function testFailureStaticMethod(): void
    {
        $errors = ['SKU_001' => 'Out of stock'];
        $result = StockValidationResult::failure($errors);

        $this->assertInstanceOf(StockValidationResult::class, $result);
        $this->assertFalse($result->isValid());
        $this->assertEquals($errors, $result->getErrors());
        $this->assertEquals([], $result->getWarnings());
        $this->assertEquals([], $result->getDetails());
        $this->assertTrue($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
    }

    public function testFailureWithAllParameters(): void
    {
        $errors = ['SKU_001' => 'Insufficient stock'];
        $warnings = ['SKU_002' => 'Low stock warning'];
        $details = ['check_method' => 'real_time', 'timestamp' => '2024-01-15'];

        $result = StockValidationResult::failure($errors, $warnings, $details);

        $this->assertFalse($result->isValid());
        $this->assertEquals($errors, $result->getErrors());
        $this->assertEquals($warnings, $result->getWarnings());
        $this->assertEquals($details, $result->getDetails());
        $this->assertTrue($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
    }

    public function testToArray(): void
    {
        $errors = ['SKU_001' => 'Stock error'];
        $warnings = ['SKU_002' => 'Stock warning'];
        $details = ['method' => 'api_check'];

        $result = new StockValidationResult(false, $errors, $warnings, $details);

        $array = $result->toArray();

        $expected = [
            'valid' => false,
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithSuccessResult(): void
    {
        $details = ['items_checked' => 3];
        $result = StockValidationResult::success($details);

        $array = $result->toArray();

        $expected = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'details' => $details,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithMinimalData(): void
    {
        $result = new StockValidationResult(true);

        $array = $result->toArray();

        $expected = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'details' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testComplexErrorsAndWarnings(): void
    {
        $errors = [
            'SKU_001' => [
                'message' => 'Out of stock',
                'available' => 0,
                'requested' => 5,
            ],
            'SKU_002' => [
                'message' => 'Product discontinued',
                'discontinued_date' => '2024-01-01',
            ],
        ];

        $warnings = [
            'SKU_003' => [
                'message' => 'Low stock',
                'available' => 2,
                'requested' => 1,
            ],
        ];

        $result = new StockValidationResult(false, $errors, $warnings);

        $this->assertEquals($errors, $result->getErrors());
        $this->assertEquals($warnings, $result->getWarnings());
        $this->assertTrue($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
    }

    public function testValidationWithOnlyWarnings(): void
    {
        $warnings = ['SKU_001' => 'Stock running low'];
        $result = new StockValidationResult(true, [], $warnings);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
        $this->assertEquals($warnings, $result->getWarnings());
    }

    public function testStaticMethodsReturnNewInstances(): void
    {
        $success1 = StockValidationResult::success();
        $success2 = StockValidationResult::success();
        $failure1 = StockValidationResult::failure(['key' => 'error']);
        $failure2 = StockValidationResult::failure(['key' => 'error']);

        $this->assertNotSame($success1, $success2);
        $this->assertNotSame($failure1, $failure2);
        $this->assertNotSame($success1, $failure1);
    }

    public function testEmptyErrorsAndWarningsArrays(): void
    {
        $result = new StockValidationResult(true, [], []);

        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
        $this->assertEquals([], $result->getErrors());
        $this->assertEquals([], $result->getWarnings());
    }

    public function testMixedDataTypes(): void
    {
        $errors = [
            'string_key' => 'string_error',
            'numeric_key' => 123,
            'array_key' => ['nested' => 'value'],
        ];

        $warnings = [
            'boolean_warning' => true,
            'float_warning' => 12.5,
        ];

        $details = [
            'timestamp' => new \DateTime('2024-01-15'),
            'count' => 42,
            'flag' => false,
        ];

        $result = new StockValidationResult(false, $errors, $warnings, $details);

        $this->assertEquals($errors, $result->getErrors());
        $this->assertEquals($warnings, $result->getWarnings());
        $this->assertEquals($details, $result->getDetails());
    }
}
