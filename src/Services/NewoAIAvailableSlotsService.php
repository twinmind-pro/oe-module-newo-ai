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
    private const int CATEGORY_FREE  = 2;

    /**
     * Get available slots for a provider in a facility within a date range.
     *
     * @param NewoAIAvailableSlotsRequest $request
     * @return NewoAIAvailableSlotsResource[]
     * @throws DateMalformedStringException|DateError|SqlQueryException
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

            // Если парсинг не удался — считаем это ошибкой формата данных
            if (!$start || !$end) {
                throw new DateMalformedStringException("Invalid event time format for date: {$event['pc_eventDate']}");
            }

            if ((int)$event['pc_catid'] === self::CATEGORY_FREE) {
                $freeSlots[$event['pc_eventDate']][] = ['start' => $start, 'end' => $end];
            } else {
                $busySlots[$event['pc_eventDate']][] = ['start' => $start, 'end' => $end];
            }
        }

        $availableSlots = [];

        // Обрабатываем каждый день свободных интервалов
        foreach ($freeSlots as $date => $slots) {
            $slotDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$slotDate) {
                throw new DateError("Invalid date format: $date");
            }

            // Отсортируем busy-интервалы по времени начала
            if (!empty($busySlots[$date])) {
                usort($busySlots[$date], function ($a, $b) {
                    return $a['start']->getTimestamp() <=> $b['start']->getTimestamp();
                });
            }

            $resource = new NewoAIAvailableSlotsResource($slotDate, []);

            foreach ($slots as $slot) {
                $start = $slot['start'];
                $end   = $slot['end'];

                if (!empty($busySlots[$date])) {
                    foreach ($busySlots[$date] as $busy) {
                        $busyStartTs = $busy['start']->getTimestamp();
                        $busyEndTs   = $busy['end']->getTimestamp();
                        $startTs     = $start->getTimestamp();
                        $endTs       = $end->getTimestamp();

                        if ($busyStartTs < $endTs && $busyEndTs > $startTs) {
                            if ($startTs < $busyStartTs && $resource->getSlots() !== null) {
                                $resource->setSlots(
                                /** @phpstan-ignore-next-line */
                                    array_merge(
                                        $resource->getSlots(),
                                        $this->splitIntoSlots($start, $busy['start'], $request->duration)
                                    )
                                );
                            }
                            $start = $busy['end'];
                        }
                    }
                }


                if ($start->getTimestamp() < $end->getTimestamp()) {
                    $resource->setSlots(
                        array_merge(
                            /** @phpstan-ignore-next-line */
                            $resource->getSlots(),
                            $this->splitIntoSlots($start, $end, $request->duration)
                        )
                    );
                }
            }

            if (!empty($resource->getSlots())) {
                $availableSlots[] = $resource;
            }
        }

        return $availableSlots;
    }

    /**
     * Split a time interval into slots of given duration (minutes).
     *
     * @param DateTime|false $start Start time
     * @param DateTime|false $end   End time
     * @param int            $duration Slot size in minutes (>=15, multiple of 5)
     * @return NewoAIAvailableSlotResource[]
     * @throws DateMalformedStringException
     * @noinspection PhpMultipleClassDeclarationsInspection
     */
    private function splitIntoSlots(DateTime|false $start, DateTime|false $end, int $duration): array
    {
        if (!$start) {
            throw new DateMalformedStringException("Invalid date format in 'start' argument.");
        }
        if (!$end) {
            throw new DateMalformedStringException("Invalid date format in 'end' argument.");
        }

        $slots   = [];
        $current = clone $start;

        while ($current->getTimestamp() < $end->getTimestamp()) {
            $next = (clone $current)->modify("+$duration minutes");
            if ($next->getTimestamp() > $end->getTimestamp()) {
                break;
            }
            $slots[] = new NewoAIAvailableSlotResource(clone $current, clone $next);
            $current = $next;
        }

        return $slots;
    }
}
