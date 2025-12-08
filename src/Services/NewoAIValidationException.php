<?php

namespace OpenEMR\Modules\NewoAI\Services;

use Exception;

/**
 * AvailableSlots validation exception
 */
class NewoAIValidationException extends Exception
{
    /**
     * @var string[] $errors
     */
    private array $errors;

    /**
     * @param array<string> $errors - errors list
     */
    public function __construct(array $errors)
    {
        parent::__construct("Validation failed");
        $this->errors = $errors;
    }

    /**
     * @return array<string> - list of errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
