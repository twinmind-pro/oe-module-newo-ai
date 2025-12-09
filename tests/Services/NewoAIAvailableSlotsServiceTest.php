<?php

namespace Services;

use DateMalformedStringException;
use DateTime;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider; // если хочешь использовать атрибуты
use PHPUnit\Framework\TestCase;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsService;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsRequest;

final class NewoAIAvailableSlotsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function makeRequest(?int $duration = null): NewoAIAvailableSlotsRequest
    {
        $req = new NewoAIAvailableSlotsRequest(
            'aid123',
            'fid456',
            new DateTime('2025-12-08'),
            new DateTime('2025-12-08')
        );
        if ($duration !== null) {
            $req->duration = $duration; // если duration — публичное поле
        }
        return $req;
    }

    private function mockFetch(array $rows): void
    {
        $queryUtilsMock = \Mockery::mock('alias:OpenEMR\Common\Database\QueryUtils');
        $queryUtilsMock->shouldReceive('fetchRecords')->once()->andReturn($rows);
    }

    /**
     * Провайдер данных для «ровного часа» без занятости.
     * Возвращает: [duration, expectedCount, expectedStarts[]]
     */
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
     * Вариант с аннотацией:
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
        $result  = $service->getAvailableSlots($this->makeRequest($duration));

        $this->assertCount(1, $result);
        $slots = $result[0]->getSlots();

        $this->assertCount($expectedCount, $slots);
        $starts = array_map(fn($s) => $s->getStartTime()->format('H:i'), $slots);
        $this->assertSame($expectedStarts, $starts);
    }

    /**
     * Если хочешь использовать атрибут вместо аннотации — вот так:
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
}
