<?php

namespace OpenEMR\Modules\NewoAI\Services;

use DateTime;

class NewoAIAvailableSlotsRequestValidator
{
    /**
     * @param array<string, string> $params
     * @return NewoAIAvailableSlotsRequest
     * @throws NewoAIValidationException
     */
    public function validate(array $params): NewoAIAvailableSlotsRequest
    {
        $errors = [];

        $requiredParams = ['aid', 'fid', 'date_from', 'date_to'];

        foreach ($requiredParams as $param) {
            if (empty($params[$param])) {
                $errors[] = "Missing required parameter: $param";
            }
        }

        if (!empty($errors)) {
            throw new NewoAIValidationException($errors);
        }

        $dateFrom = DateTime::createFromFormat('Y-m-d', $params['date_from']);
        $dateTo = DateTime::createFromFormat('Y-m-d', $params['date_to']);

        if (!$dateFrom) {
            $errors[] = "Invalid date format dateFrom: " . $params['date_from'] . " Expected 'YYYY-MM-DD'.";
        }

        if (!$dateTo) {
            $errors[] = "Invalid date format dateTo: " . $params['date_to'] . " Expected 'YYYY-MM-DD'.";
        }

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            $errors[] = "date_from must be earlier than date_to.";
        }

        if (empty($errors) && $dateFrom && $dateTo) {
            return new NewoAIAvailableSlotsRequest(
                $params['aid'],
                $params['fid'],
                $dateFrom,
                $dateTo
            );
        }
        throw new NewoAIValidationException($errors);
    }
}
