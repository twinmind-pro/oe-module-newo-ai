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

        $duration = 15;
        if (isset($params['duration_in_min']) && $params['duration_in_min'] !== '') {
            $durationRaw = $params['duration_in_min'];
            if (!ctype_digit($durationRaw)) {
                $errors[] = "duration_in_min must be an integer.";
            } else {
                $duration = (int)$durationRaw;

                if ($duration < 15) {
                    $errors[] = "duration_in_min must be at least 15.";
                }
                if ($duration % 5 !== 0) {
                    $errors[] = "duration_in_min must be a multiple of 5.";
                }
            }
        }

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
                $dateTo,
                $duration,
            );
        }
        throw new NewoAIValidationException($errors);
    }
}
