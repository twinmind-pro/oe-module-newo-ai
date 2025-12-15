<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Services;

use DateMalformedStringException;
use DateTime;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsService;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsRequest;

final class NewoAIAvailableSlotsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Create a request with a 3-day range (2025-12-08 .. 2025-12-10, inclusive).
     * If $duration is provided, it will be assigned to the public field.
     */
    private function makeRequest(?int $duration = null): NewoAIAvailableSlotsRequest
    {
        $req = new NewoAIAvailableSlotsRequest(
            'aid123',
            'fid456',
            new DateTime('2025-12-08'),
            new DateTime('2025-12-10')
        );
        if ($duration !== null) {
            $req->duration = $duration; // if duration is a public field
        }
        return $req;
    }

    /**
     * Mock QueryUtils::fetchRecords to return provided rows.
     */
    private function mockFetch(array $rows): void
    {
        $queryUtilsMock = Mockery::mock('alias:OpenEMR\Common\Database\QueryUtils');
        $queryUtilsMock->shouldReceive('fetchRecords')->once()->andReturn($rows);
    }

    public static function provideFlatHourDurations(): array
    {
        return [
            '15-min' => [15, 4, ['09:00', '09:15', '09:30', '09:45']],
            '20-min' => [20, 3, ['09:00', '09:20', '09:40']],
            '30-min' => [30, 2, ['09:00', '09:30']],
            '60-min' => [60, 1, ['09:00']],
        ];
    }

    /**
     * Single-day, no busy — various durations, original check kept.
     *
     * @throws DateMalformedStringException
     */
    #[DataProvider('provideFlatHourDurations')]
    public function testNoBusyVariousDurations(int $duration, int $expectedCount, array $expectedStarts): void
    {
        $this->mockFetch([
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2
            ],
        ]);

        $service = new NewoAIAvailableSlotsService();
        // Request has 3-day range, but only one day has data in mock.
        $result  = $service->getAvailableSlots($this->makeRequest($duration));

        $this->assertCount(1, $result);
        $slots = $result[0]->getSlots();

        $this->assertCount($expectedCount, $slots);
        $starts = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slots);
        $this->assertSame($expectedStarts, $starts);
    }

    /**
     * Same as above — attribute-based data provider (kept as in original).
     *
     * @throws DateMalformedStringException
     */
    #[DataProvider('provideFlatHourDurations')]
    public function testNoBusyVariousDurationsAttr(int $duration, int $expectedCount, array $expectedStarts): void
    {
        $this->mockFetch([
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2
            ],
        ]);

        $service = new NewoAIAvailableSlotsService();
        $result  = $service->getAvailableSlots($this->makeRequest($duration));

        $this->assertCount(1, $result);
        $slots = $result[0]->getSlots();

        $this->assertCount($expectedCount, $slots);
        $starts = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slots);
        $this->assertSame($expectedStarts, $starts);
    }

    /**
     * Multiple dates, no busy intervals.
     * - 2025-12-08: free 09:00-10:00
     * - 2025-12-09: free 13:00-14:00
     * Expect two days in result with simple sliced slots for the given duration.
     * @throws DateMalformedStringException
     */
    public function testMultipleDaysNoBusy(): void
    {
        $duration = 30;

        $this->mockFetch([
            // Day 1
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2, // free
            ],
            // Day 2
            [
                'pc_eventDate' => '2025-12-09',
                'pc_startTime' => '13:00:00',
                'pc_endTime'   => '14:00:00',
                'pc_catid'     => 2, // free
            ],
        ]);

        $service = new NewoAIAvailableSlotsService();
        $result  = $service->getAvailableSlots($this->makeRequest($duration));

        // Expect two date buckets
        $this->assertCount(2, $result);

        // Sort by date to make assertions deterministic
        usort($result, fn($a, $b) => $a->getDate() <=> $b->getDate());

        // Day 1 assertions
        $this->assertSame('2025-12-08', $result[0]->getDate()->format('Y-m-d'));
        $slotsDay1 = $result[0]->getSlots();
        $this->assertCount(2, $slotsDay1);
        $startsDay1 = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slotsDay1);
        $this->assertSame(['09:00', '09:30'], $startsDay1);

        // Day 2 assertions
        $this->assertSame('2025-12-09', $result[1]->getDate()->format('Y-m-d'));
        $slotsDay2 = $result[1]->getSlots();
        $this->assertCount(2, $slotsDay2);
        $startsDay2 = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slotsDay2);
        $this->assertSame(['13:00', '13:30'], $startsDay2);
    }

    /**
     * Multiple dates with a busy interval on the middle day.
     * - 2025-12-08: free 09:00-10:00 (no busy)
     * - 2025-12-09: free 09:00-12:00, busy 10:15-10:45 => slots should be split around busy
     * - 2025-12-10: free 16:00-17:00 (no busy)
     * Check that each date returns correct slots and busy subtraction is applied only for that date.
     * @throws DateMalformedStringException
     */
    public function testMultipleDaysWithBusyOnMiddleDay(): void
    {
        $duration = 15;

        $this->mockFetch([
            // Day 1: free only
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2,
            ],
            // Day 2: free long interval
            [
                'pc_eventDate' => '2025-12-09',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '12:00:00',
                'pc_catid'     => 2,
            ],
            // Day 2: busy in the middle (10:15-10:45)
            [
                'pc_eventDate' => '2025-12-09',
                'pc_startTime' => '10:15:00',
                'pc_endTime'   => '10:45:00',
                'pc_catid'     => 1, // not free
            ],
            // Day 3: free only
            [
                'pc_eventDate' => '2025-12-10',
                'pc_startTime' => '16:00:00',
                'pc_endTime'   => '17:00:00',
                'pc_catid'     => 2,
            ],
        ]);

        $service = new NewoAIAvailableSlotsService();
        $result  = $service->getAvailableSlots($this->makeRequest($duration));

        // Expect three dates
        $this->assertCount(3, $result);

        // Sort by date to make assertions deterministic
        usort($result, fn($a, $b) => $a->getDate() <=> $b->getDate());

        // Day 1 (2025-12-08): 09:00-10:00, 15-min slots: 09:00, 09:15, 09:30, 09:45
        $this->assertSame('2025-12-08', $result[0]->getDate()->format('Y-m-d'));
        $slotsDay1 = $result[0]->getSlots();
        $this->assertCount(4, $slotsDay1);
        $startsDay1 = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slotsDay1);
        $this->assertSame(['09:00', '09:15', '09:30', '09:45'], $startsDay1);

        // Day 2 (2025-12-09): free 09:00-12:00 minus busy 10:15-10:45
        // Resulting free parts: 09:00-10:15 and 10:45-12:00
        // 15-min slots:
        // 09:00, 09:15, 09:30, 09:45, 10:00  (from 09:00 to 10:15)
        // 10:45, 11:00, 11:15, 11:30, 11:45 (from 10:45 to 12:00)
        $this->assertSame('2025-12-09', $result[1]->getDate()->format('Y-m-d'));
        $slotsDay2 = $result[1]->getSlots();
        $this->assertCount(10, $slotsDay2);
        $startsDay2 = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slotsDay2);
        $this->assertSame(
            ['09:00', '09:15', '09:30', '09:45', '10:00', '10:45', '11:00', '11:15', '11:30', '11:45'],
            $startsDay2
        );

        // Day 3 (2025-12-10): 16:00-17:00, 15-min slots: 16:00, 16:15, 16:30, 16:45
        $this->assertSame('2025-12-10', $result[2]->getDate()->format('Y-m-d'));
        $slotsDay3 = $result[2]->getSlots();
        $this->assertCount(4, $slotsDay3);
        $startsDay3 = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slotsDay3);
        $this->assertSame(['16:00', '16:15', '16:30', '16:45'], $startsDay3);
    }
}
