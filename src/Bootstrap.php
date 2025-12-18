<?php

/**
 * Bootstrap NewoAI custom module
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author   Roman Morenko <morenko83@gmail.com>
 * @copyright Copyright (c) 2025 Roman Morenko <morenko83@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\NewoAI;

/**
 * Note the below use statements are importing classes from the OpenEMR core codebase
 */

use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Events\RestApiExtend\RestApiCreateEvent;
use OpenEMR\Events\RestApiExtend\RestApiResourceServiceEvent;
use OpenEMR\Events\RestApiExtend\RestApiScopeEvent;
use OpenEMR\Modules\NewoAI\RestControllers\NewoAIRestController;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class Bootstrap
{
    /**
     * @var EventDispatcherInterface The object responsible for sending
     * and subscribing to events through the OpenEMR system
     */
    private EventDispatcherInterface $eventDispatcher;


    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function subscribeToEvents(): void
    {
        /** @phpstan-ignore-next-line */
        EventAuditLogger::instance()->newEvent(
            'oe-module-newo-ai-bootstrap',
            $_SESSION['authUser'] ?? 'system',
            $_SESSION['authProvider'] ?? 'Default',
            1,
            'Subscribe to events',
            null,
            "oe-module-newo-ai",
        );
        $this->subscribeToApiEvents();
    }

    public function subscribeToApiEvents(): void
    {
        /** @phpstan-ignore-next-line */
        EventAuditLogger::instance()->newEvent(
            'oe-module-newo-ai-bootstrap',
            $_SESSION['authUser'] ?? 'system',
            $_SESSION['authProvider'] ?? 'Default',
            1,
            'Subscribe to API Events',
            null,
            "oe-module-newo-ai",
        );
        $this->eventDispatcher->addListener(RestApiCreateEvent::EVENT_HANDLE, [$this, 'addCustomApi']);
        $this->eventDispatcher->addListener(
            RestApiScopeEvent::EVENT_TYPE_GET_SUPPORTED_SCOPES,
            [$this, 'addApiScope']
        );
        $this->eventDispatcher->addListener(
            RestApiResourceServiceEvent::EVENT_HANDLE,
            [$this, 'addMetadataConformance']
        );
    }

    public function addCustomApi(RestApiCreateEvent $event): RestApiCreateEvent
    {
        /** @phpstan-ignore-next-line */
        EventAuditLogger::instance()->newEvent(
            'oe-module-newo-ai-bootstrap',
            $_SESSION['authUser'] ?? 'system',
            $_SESSION['authProvider'] ?? 'Default',
            1,
            'Add custom API',
            null,
            "oe-module-newo-ai",
        );
        $apiController = new NewoAIRestController();

        $event->addToRouteMap('GET /api/available_slots', [$apiController, 'getAvailableSlots']);
        $event->addToRouteMap('GET /api/patient_by_phone', [$apiController, 'patientByPhone']);
        return $event;
    }

    public function addApiScope(RestApiScopeEvent $event): RestApiScopeEvent
    {
        /** @phpstan-ignore-next-line */
        EventAuditLogger::instance()->newEvent(
            'oe-module-newo-ai-bootstrap',
            $_SESSION['authUser'] ?? 'system',
            $_SESSION['authProvider'] ?? 'Default',
            1,
            'Add API scopes',
            null,
            "oe-module-newo-ai",
        );
        $scopes = $event->getScopes();
        /** @phpstan-ignore-next-line */
        $scopes[] = 'user/available_slots.read';
        $scopes[] = 'user/patient_by_phone.read';
        $event->setScopes($scopes);
        return $event;
    }

    public function addMetadataConformance(RestApiResourceServiceEvent $event): RestApiResourceServiceEvent
    {
        /** @phpstan-ignore-next-line */
        EventAuditLogger::instance()->newEvent(
            'oe-module-newo-ai-bootstrap',
            $_SESSION['authUser'] ?? 'system',
            $_SESSION['authProvider'] ?? 'Default',
            1,
            'Add Metadata Conformance',
            null,
            "oe-module-newo-ai",
        );
        $event->setServiceClass(NewoAIAvailableSlotsService::class);
        return $event;
    }
}
