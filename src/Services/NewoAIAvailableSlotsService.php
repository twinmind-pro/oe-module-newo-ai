<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace OpenEMR\Modules\NewoAI\Services;

use DateError;
use DateMalformedStringException;
use DateTime;
use InvalidArgumentException;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Database\SqlQueryException;
use OpenEMR\Modules\NewoAI\Resources\NewoAIAvailableSlotResource;
use OpenEMR\Modules\NewoAI\Resources\NewoAIAvailableSlotsResource;

class NewoAIAvailableSlotsService
{
    // If your PHP version is < 8.3, remove the types from constants (use plain `const`).
    private const string TABLE_EVENTS = 'openemr_postcalendar_events';
    private const int CATEGORY_FREE   = 2;

    /**
     * Get available appointment slots for a provider in a facility within a date range.
     *
     * Expected fields in $request (NewoAIAvailableSlotsRequest):
     *  - int|string $aid       Provider identifier
     *  - int|string $fid       Facility identifier
     *  - DateTime   $dateFrom  Inclusive
     *  - DateTime   $dateTo    Inclusive
     *  - int        $duration  Slot duration in minutes (>= 15, multiple of 5)
     *
     * @param NewoAIAvailableSlotsRequest $request
     * @return NewoAIAvailableSlotsResource[]
     * @throws DateMalformedStringException|DateError|SqlQueryException|InvalidArgumentException
     */
    public function getAvailableSlots(NewoAIAvailableSlotsRequest $request): array
    {
        // Validate slot duration.
        if ($request->duration < 15 || ($request->duration % 5) !== 0) {
            throw new InvalidArgumentException('Duration must be >= 15 and a multiple of 5 minutes.');
        }

        // Fetch events within the date range for a specific provider and facility.
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
            $request->dateTo->format('Y-m-d'),
        ];

        /** @var array<int,array{pc_eventDate:string,pc_startTime:string,pc_endTime:string,pc_catid:int|string}> $events */
        $events = QueryUtils::fetchRecords($sql, $binds);

        /** @var array<string, array<int, array{start:DateTime,end:DateTime}>> $freeSlots */
        $freeSlots = [];
        /** @var array<string, array<int, array{start:DateTime,end:DateTime}>> $busySlots */
        $busySlots = [];

        // Split events by date into "free" and "busy" buckets.
        foreach ($events as $event) {
            $dateStr  = $event['pc_eventDate'];
            $startStr = $event['pc_startTime'];
            $endStr   = $event['pc_endTime'];

            // Parse start/end; allow both H:i:s and H:i.
            $start = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $startStr)
                ?: DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $startStr);

            $end   = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $endStr)
                ?: DateTime::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $endStr);

            if (!$start || !$end) {
                throw new DateMalformedStringException(
                    "Invalid event time format for date: $dateStr (start: $startStr, end: $endStr)"
                );
            }

            // Sanity check: start < end.
            if ($start->getTimestamp() >= $end->getTimestamp()) {
                throw new DateMalformedStringException(
                    "Event has non-positive duration: $dateStr (start >= end)"
                );
            }

            if ((int)$event['pc_catid'] === self::CATEGORY_FREE) {
                $freeSlots[$dateStr][] = ['start' => $start, 'end' => $end];
            } else {
                $busySlots[$dateStr][] = ['start' => $start, 'end' => $end];
            }
        }

        if (empty($freeSlots)) {
            return [];
        }

        $availableByDate = [];

        // Process each date that has at least one free interval.
        foreach ($freeSlots as $date => $slots) {
            $slotDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$slotDate) {
                throw new DateError("Invalid date format: $date");
            }

            // Prepare busy intervals for the date: sort + merge overlaps/touches.
            $dayBusy = $busySlots[$date] ?? [];
            if (!empty($dayBusy)) {
                $dayBusy = $this->mergeOverlaps($dayBusy);
            }

            // Accumulate all resulting slots for the day locally.
            /** @var NewoAIAvailableSlotResource[] $daySlots */
            $daySlots = [];

            // For each free interval: subtract all busy intervals and split the remaining parts into slots.
            foreach ($slots as $slot) {
                $start = clone $slot['start'];
                $end   = clone $slot['end'];

                if (!empty($dayBusy)) {
                    foreach ($dayBusy as $busy) {
                        // Skip if no overlap.
                        if (
                            $busy['start']->getTimestamp() >= $end->getTimestamp()
                            || $busy['end']->getTimestamp() <= $start->getTimestamp()
                        ) {
                            continue;
                        }

                        // Left free part before the busy interval.
                        if ($start->getTimestamp() < $busy['start']->getTimestamp()) {
                            $daySlots = array_merge(
                                $daySlots,
                                $this->splitIntoSlots($start, $busy['start'], $request->duration)
                            );
                        }

                        // Move start past the busy interval.
                        if ($busy['end']->getTimestamp() > $start->getTimestamp()) {
                            $start = clone $busy['end'];
                        }

                        // If nothing left in this free interval, stop processing it.
                        if ($start->getTimestamp() >= $end->getTimestamp()) {
                            break;
                        }
                    }
                }

                // Tail after the last busy interval.
                if ($start->getTimestamp() < $end->getTimestamp()) {
                    $daySlots = array_merge(
                        $daySlots,
                        $this->splitIntoSlots($start, $end, $request->duration)
                    );
                }
            }

            if (!empty($daySlots)) {
                $availableByDate[] = new NewoAIAvailableSlotsResource($slotDate, $daySlots);
            }
        }

        return $availableByDate;
    }

    /**
     * Merge overlapping or touching busy intervals.
     *
     * @param array<int,array{start:DateTime,end:DateTime}> $intervals
     * @return array<int,array{start:DateTime,end:DateTime}>
     */
    private function mergeOverlaps(array $intervals): array
    {
        if (empty($intervals)) {
            return [];
        }

        usort(
            $intervals,
            static fn ($a, $b) => $a['start']->getTimestamp() <=> $b['start']->getTimestamp()
        );

        $merged  = [];
        $current = $intervals[0];

        for ($i = 1, $n = count($intervals); $i < $n; $i++) {
            $next = $intervals[$i];

            // Overlap or touch?
            if ($next['start']->getTimestamp() <= $current['end']->getTimestamp()) {
                // Extend the current interval if the next one goes further.
                if ($next['end']->getTimestamp() > $current['end']->getTimestamp()) {
                    $current['end'] = $next['end'];
                }
            } else {
                $merged[] = $current;
                $current  = $next;
            }
        }

        $merged[] = $current;
        return $merged;
    }

    /**
     * Split a continuous interval into fixed-size slots.
     * A slot is added only if it fully fits within the interval.
     *
     * @param DateTime $start
     * @param DateTime $end
     * @param int $duration Minutes
     * @return NewoAIAvailableSlotResource[]
     * @throws DateMalformedStringException
     */
    private function splitIntoSlots(DateTime $start, DateTime $end, int $duration): array
    {
        if ($start->getTimestamp() >= $end->getTimestamp()) {
            return [];
        }

        $slots   = [];
        $current = clone $start;

        while ($current->getTimestamp() < $end->getTimestamp()) {
            $next = (clone $current)->modify("+$duration minutes");
            if ($next->getTimestamp() > $end->getTimestamp()) {
                break; // do not add a partial trailing slot
            }

            $slots[] = new NewoAIAvailableSlotResource(clone $current, clone $next);
            $current = $next;
        }

        return $slots;
    }
}
