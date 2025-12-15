<?php

/** @noinspection PhpUnused */

/**
 * Available Slots Controller
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Stephen Nielson <stephen@nielson.org>
 * @copyright Copyright (c) 2022 Stephen Nielson <stephen@nielson.org>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\NewoAI\RestControllers;

use OpenEMR\Common\Http\HttpRestRequest;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsRequestValidator;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsService;
use OpenEMR\Modules\NewoAI\Services\NewoAIValidationException;
use OpenEMR\RestControllers\RestControllerHelper;
use OpenEMR\Validators\ProcessingResult;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class NewoAIRestController
{
    /**
     * @var NewoAIAvailableSlotsService
     */
    private NewoAIAvailableSlotsService $newoAIAvailableSlotsService;
    private NewoAIAvailableSlotsRequestValidator $validator;




    public function __construct()
    {
        $this->newoAIAvailableSlotsService = new NewoAIAvailableSlotsService();
        $this->validator = new NewoAIAvailableSlotsRequestValidator();
    }


    /**
     * Handle GET /api/available_slots request.
     *
     * @param HttpRestRequest $request - HTTP request
     * @return array[] - response
     */
    /** @phpstan-ignore-next-line */
    public function getAvailableSlots(HttpRestRequest $request): array
    {
        $result = new ProcessingResult();
        try {
            /** @phpstan-ignore-next-line */
            EventAuditLogger::instance()->newEvent(
                'api',
                $_SESSION['authUser'] ?? 'system',
                $_SESSION['authProvider'] ?? 'Default',
                1,
                '/api/available_slots request: ' . $request,
                null,
                "oe-module-newo-ai",
            );
            $queryParams = $request->getQueryParams();

            // Validate request parameters
            try {
                $availableSlotsRequest = $this->validator->validate($queryParams);
                $slots = $this->newoAIAvailableSlotsService->getAvailableSlots($availableSlotsRequest);
                $result->setData($slots);
                /** @phpstan-ignore-next-line */
                EventAuditLogger::instance()->newEvent(
                    'api',
                    $_SESSION['authUser'] ?? 'system',
                    $_SESSION['authProvider'] ?? '',
                    1,
                    '/api/available_slots response: ' . json_encode($slots),
                    null,
                    "oe-module-newo-ai",
                );

                return RestControllerHelper::handleProcessingResult(
                    $result,
                    /** @phpstan-ignore-next-line */
                    Response::HTTP_OK,
                    true
                );
            } catch (NewoAIValidationException $e) {
                $result->setValidationMessages($e->getErrors());
                return RestControllerHelper::handleProcessingResult(
                    $result,
                    /** @phpstan-ignore-next-line */
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (Throwable $e) {
            // Log the error event
            /** @phpstan-ignore-next-line */
            EventAuditLogger::instance()->newEvent(
                'api',
                $_SESSION['authUser'] ?? 'system',
                $_SESSION['authProvider'] ?? '',
                0,
                '/api/available_slots error:  ' . $e->getMessage() . ':' . $e->getTraceAsString(),
                null,
                "oe-module-newo-ai",
            );
            $errors = [];
            $errors[] = $e->getMessage();
            $result->setInternalErrors($errors);
            // Catch any exception or error and return 500 response
            return RestControllerHelper::handleProcessingResult(
                $result,
                /** @phpstan-ignore-next-line */
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
