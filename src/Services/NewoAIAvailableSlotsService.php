<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace OpenEMR\Modules\NewoAI\Services;

use DateError;
use DateMalformedStringException;
use DateTime;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Database\SqlQueryException;
use OpenEMR\Modules\NewoAI\Resources\NewoAIAvailableSlotResource;
use OpenEMR\Modules\NewoAI\Resources\NewoAIAvailableSlotsResource;

class NewoAIAvailableSlotsService
{
    private const string TABLE_EVENTS = 'openemr_postcalendar_events';

    /**
     * Get available slots for a provider in a facility within a date range.
     *
     * @param NewoAIAvailableSlotsRequest $request
     * @return NewoAIAvailableSlotsResource[]
     * @throws DateMalformedStringException | DateError
     * @noinspection PhpMultipleClassDeclarationsInspection
     */
    public function getAvailableSlots(NewoAIAvailableSlotsRequest $request): array
    {

        $sql = sprintf(
            "SELECT pc_eventDate, pc_startTime, pc_endTime, pc_catid
             FROM %s
             WHERE pc_aid = ?
               AND pc_facility = ?
               AND pc_eventDate BETWEEN ? AND ?
             ORDER BY pc_eventDate, pc_startTime",
            self::TABLE_EVENTS
        );


        $binds = [
            $request->aid,
            $request->fid,
            $request->dateFrom->format('Y-m-d'),
            $request->dateTo->format('Y-m-d')
        ];

        $events = QueryUtils::fetchRecords($sql, $binds);

        $freeSlots = [];
        $busySlots = [];

        foreach ($events as $event) {
            $start = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $event['pc_eventDate'] . ' ' . $event['pc_startTime']
            );
            $end = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $event['pc_eventDate'] . ' ' . $event['pc_endTime']
            );

            if ((int)$event['pc_catid'] === 2) {
                $freeSlots[$event['pc_eventDate']][] = ['start' => $start, 'end' => $end];
            } else {
                $busySlots[$event['pc_eventDate']][] = ['start' => $start, 'end' => $end];
            }
        }

        $availableSlots = [];

        foreach ($freeSlots as $date => $slots) {
            $slot_date = DateTime::createFromFormat('Y-m-d', $date);
            if (!$slot_date) {
                throw new DateError("Invalid date format: " . $date);
            }
            $resource = new NewoAIAvailableSlotsResource($slot_date, []);
            foreach ($slots as $slot) {
                $start = $slot['start'];
                $end = $slot['end'];

                if (!empty($busySlots[$date])) {
                    foreach ($busySlots[$date] as $busy) {
                        // If busy interval overlaps with free interval
                        if ($busy['start'] < $end && $busy['end'] > $start) {
                            // Add part before busy interval
                            if ($start < $busy['start'] && $resource->getSlots() !== null) {
                                $resource->setSlots(
                                    /** @phpstan-ignore-next-line */
                                    array_merge(
                                        $resource->getSlots(),
                                        $this->splitIntoSlots($start, $busy['start'])
                                    )
                                );
                            }
                            // Move start after busy interval
                            $start = $busy['end'];
                        }
                    }
                }

                if ($start < $end) {
                    /** @phpstan-ignore-next-line */
                    $resource->setSlots(array_merge($resource->getSlots(), $this->splitIntoSlots($start, $end)));
                }
            }

            if (!empty($resource->getSlots())) {
                $availableSlots[] = $resource;
            }
        }

        return $availableSlots;
    }

    /**
     * Split a time interval into 15-minute slots.
     *
     * @param DateTime|false $start Start time
     * @param DateTime|false $end End time
     * @return NewoAIAvailableSlotResource[]
     * @throws SqlQueryException | DateMalformedStringException
     * @noinspection PhpMultipleClassDeclarationsInspection
     */
    private function splitIntoSlots(DateTime|false $start, DateTime|false $end): array
    {
        if (!$start) {
            throw new DateMalformedStringException("Invalid date format: " . $start);
        }
        if (!$end) {
            throw new DateMalformedStringException("Invalid date format: " . $end);
        }
        $slots = [];
        $current = clone $start;

        while ($current < $end) {
            $next = (clone $current)->modify("+15 minutes");
            if ($next > $end) {
                break;
            }
            $slot = new NewoAIAvailableSlotResource(clone $current, clone $next);
            $slots[] = $slot;
            $current = $next;
        }

        return $slots;
    }
}
