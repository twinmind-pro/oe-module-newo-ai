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
    public function testValidParamsReturnsRequestObjectDefaultDuration()
    {
        $params = [
            'aid'       => '123',
            'fid'       => '456',
            'date_from' => '2025-12-08',
            'date_to'   => '2025-12-09',
            // duration_in_min not present
        ];

        $result = $this->validator->validate($params);
        $this->assertEquals('123', $result->aid);
        $this->assertEquals('456', $result->fid);
        $this->assertEquals('2025-12-08', $result->dateFrom->format('Y-m-d'));
        $this->assertEquals('2025-12-09', $result->dateTo->format('Y-m-d'));
        // маппинг в поле duration (как в сервисе)
        $this->assertSame(15, $result->duration);
    }

    /**
     *
     * @throws NewoAIValidationException
     */
    public function testValidParamsReturnsRequestObjectCustomDuration()
    {
        $params = [
            'aid'             => '123',
            'fid'             => '456',
            'date_from'       => '2025-12-08',
            'date_to'         => '2025-12-09',
            'duration_in_min' => '20',
        ];

        $result = $this->validator->validate($params);
        $this->assertEquals('123', $result->aid);
        $this->assertEquals('456', $result->fid);
        $this->assertEquals('2025-12-08', $result->dateFrom->format('Y-m-d'));
        $this->assertEquals('2025-12-09', $result->dateTo->format('Y-m-d'));
        $this->assertSame(20, $result->duration);
    }

    public function testMissingRequiredParamThrowsException()
    {
        $params = [
            'aid'       => '123',
            'date_from' => '2025-12-08',
            'date_to'   => '2025-12-09'
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
            'aid'       => '123',
            'fid'       => '456',
            'date_from' => '08-12-2025', // invalid
            'date_to'   => '09.12.2025', // invalid
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
            'aid'       => '123',
            'fid'       => '456',
            'date_from' => '2025-12-10',
            'date_to'   => '2025-12-09'
        ];

        try {
            $this->validator->validate($params);
            $this->fail("Expected NewoAIValidationException not thrown");
        } catch (NewoAIValidationException $e) {
            $this->assertEquals("Validation failed", $e->getMessage());
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertEquals("date_from must be earlier than date_to.", $errors[0]);
        }
    }

    /**
     * Негативные кейсы для duration_in_min.
     * Сообщения могут отличаться по точному тексту — проверяем по смысловым фрагментам.
     */
    public function testInvalidDurationCases()
    {
        $cases = [
            // not integer
            [
                ['duration_in_min' => '15.0'],
                'integer'
            ],
            [
                ['duration_in_min' => '-15'],
                'integer'
            ],
            [
                ['duration_in_min' => 'abc'],
                'integer'
            ],

            // less than 15
            [
                ['duration_in_min' => '14'],
                'at least 15'
            ],

            // not multiple of 5
            [
                ['duration_in_min' => '17'],
                'multiple of 5'
            ],
        ];

        foreach ($cases as [$extra, $expectedPhrase]) {
            $params = array_merge([
                'aid'       => '123',
                'fid'       => '456',
                'date_from' => '2025-12-08',
                'date_to'   => '2025-12-09',
            ], $extra);

            try {
                $this->validator->validate($params);
                $this->fail("Expected NewoAIValidationException not thrown");
            } catch (NewoAIValidationException $e) {
                $this->assertEquals("Validation failed", $e->getMessage());
                $errors = $e->getErrors();
                $this->assertNotEmpty($errors);
                $this->assertTrue(
                    array_reduce(
                        $errors,
                        fn ($carry, $msg) => $carry || str_contains($msg, $expectedPhrase),
                        false
                    ),
                    "Expected one of the error messages to contain '$expectedPhrase',
                     got: " . implode(' | ', $errors)
                );
            }
        }
    }
}
