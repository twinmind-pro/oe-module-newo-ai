<?php

namespace Services;

use PHPUnit\Framework\TestCase;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsRequestValidator;
use OpenEMR\Modules\NewoAI\Services\NewoAIValidationException;

class NewoAIAvailableSlotsRequestValidatorTest extends TestCase
{
    private NewoAIAvailableSlotsRequestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new NewoAIAvailableSlotsRequestValidator();
    }

    /**
     * @throws NewoAIValidationException
     */
    public function testValidParamsReturnsRequestObject()
    {
        $params = [
            'aid' => '123',
            'fid' => '456',
            'date_from' => '2025-12-08',
            'date_to' => '2025-12-09'
        ];

        $result = $this->validator->validate($params);
        $this->assertEquals('123', $result->aid);
        $this->assertEquals('456', $result->fid);
        $this->assertEquals('2025-12-08', $result->dateFrom->format('Y-m-d'));
        $this->assertEquals('2025-12-09', $result->dateTo->format('Y-m-d'));
    }

    public function testMissingRequiredParamThrowsException()
    {
        $params = [
            'aid' => '123',
            'date_from' => '2025-12-08',
            'date_to' => '2025-12-09'
        ];

        try {
            $this->validator->validate($params);
            $this->fail("Expected NewoAIValidationException not thrown");
        } catch (NewoAIValidationException $e) {
            $this->assertEquals("Validation failed", $e->getMessage());
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertEquals("Missing required parameter: fid", $errors[0]);
        }
    }

    public function testInvalidDateFormatThrowsException()
    {
        $params = [
            'aid' => '123',
            'fid' => '456',
            'date_from' => '08-12-2025', //invalid format
            'date_to' => '09.12.2025'
        ];

        try {
            $this->validator->validate($params);
            $this->fail("Expected NewoAIValidationException not thrown");
        } catch (NewoAIValidationException $e) {
            $this->assertEquals("Validation failed", $e->getMessage());
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);
            $this->assertEquals(
                "Invalid date format dateFrom: 08-12-2025 Expected 'YYYY-MM-DD'.",
                $errors[0]
            );
            $this->assertEquals(
                "Invalid date format dateTo: 09.12.2025 Expected 'YYYY-MM-DD'.",
                $errors[1]
            );
        }
    }

    public function testDateFromGreaterThanDateToThrowsException()
    {
        $params = [
            'aid' => '123',
            'fid' => '456',
            'date_from' => '2025-12-10',
            'date_to' => '2025-12-09'
        ];

        try {
            $this->validator->validate($params);
            $this->fail("Expected NewoAIValidationException not thrown");
        } catch (NewoAIValidationException $e) {
            $this->assertEquals("Validation failed", $e->getMessage());
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertEquals(
                "date_from must be earlier than date_to.",
                $errors[0]
            );
        }
    }
}
