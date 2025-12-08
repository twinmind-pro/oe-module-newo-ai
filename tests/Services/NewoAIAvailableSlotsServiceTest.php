<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Services;

use DateMalformedStringException;
use DateTime;
use Mockery;
use PHPUnit\Framework\TestCase;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsService;
use OpenEMR\Modules\NewoAI\Services\NewoAIAvailableSlotsRequest;

class NewoAIAvailableSlotsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testGetAvailableSlotsReturnsCorrectSlots()
    {
        // 1. Prepare request
        $request = new NewoAIAvailableSlotsRequest(
            'aid123',
            'fid456',
            new DateTime('2025-12-08'),
            new DateTime('2025-12-08')
        );

        // 2. Mock static method QueryUtils::fetchRecords
        $mockData = [
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2 // free slot
            ],
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:30:00',
                'pc_endTime'   => '09:45:00',
                'pc_catid'     => 1 // booked slot
            ]
        ];

        //3. Mockery alias for static class
        $queryUtilsMock = Mockery::mock('alias:OpenEMR\Common\Database\QueryUtils');
        $queryUtilsMock->shouldReceive('fetchRecords')
            ->once()
            ->andReturn($mockData);

        // 3. Call service
        $service = new NewoAIAvailableSlotsService();
        $result = $service->getAvailableSlots($request);

        // 4. Asser result
        $this->assertNotEmpty($result);
        $this->assertCount(1, $result); // один ресурс
        $slots = $result[0]->getSlots();
        $this->assertNotEmpty($slots);
        $this->assertGreaterThan(0, count($slots));
    }


    /**
     * @throws DateMalformedStringException
     */
    public function testSlotsWithPartialBusyOverlap()
    {
        $request = new NewoAIAvailableSlotsRequest(
            'aid123',
            'fid456',
            new DateTime('2025-12-08'),
            new DateTime('2025-12-08')
        );

        $mockData = [
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2 // свободный слот
            ],
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:30:00',
                'pc_endTime'   => '09:45:00',
                'pc_catid'     => 1 // занятый слот
            ]
        ];

        $queryUtilsMock = Mockery::mock('alias:OpenEMR\Common\Database\QueryUtils');
        $queryUtilsMock->shouldReceive('fetchRecords')->once()->andReturn($mockData);

        $service = new NewoAIAvailableSlotsService();
        $result = $service->getAvailableSlots($request);

        $this->assertCount(1, $result);
        $slots = $result[0]->getSlots();

        // Check that slots available
        $this->assertNotEmpty($slots);

        // First slot should start at 09:00
        $this->assertEquals('09:00', $slots[0]->getStartTime()->format('H:i'));
        // Second slot should start a в 09:15
        $this->assertEquals('09:15', $slots[1]->getStartTime()->format('H:i'));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSlotsWithoutBusyIntervals()
    {
        $request = new NewoAIAvailableSlotsRequest(
            'aid123',
            'fid456',
            new DateTime('2025-12-08'),
            new DateTime('2025-12-08')
        );

        $mockData = [
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2
            ]
        ];

        $queryUtilsMock = Mockery::mock('alias:OpenEMR\Common\Database\QueryUtils');
        $queryUtilsMock->shouldReceive('fetchRecords')->once()->andReturn($mockData);

        $service = new NewoAIAvailableSlotsService();
        $result = $service->getAvailableSlots($request);

        $slots = $result[0]->getSlots();
        $this->assertCount(4, $slots); // 09:00-09:15, 09:15-09:30, 09:30-09:45, 09:45-10:00
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testSlotsFullyBusy()
    {
        $request = new NewoAIAvailableSlotsRequest(
            'aid123',
            'fid456',
            new DateTime('2025-12-08'),
            new DateTime('2025-12-08')
        );

        $mockData = [
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 2
            ],
            [
                'pc_eventDate' => '2025-12-08',
                'pc_startTime' => '09:00:00',
                'pc_endTime'   => '10:00:00',
                'pc_catid'     => 1
            ]
        ];

        $queryUtilsMock = Mockery::mock('alias:OpenEMR\Common\Database\QueryUtils');
        $queryUtilsMock->shouldReceive('fetchRecords')->once()->andReturn($mockData);

        $service = new NewoAIAvailableSlotsService();
        $result = $service->getAvailableSlots($request);

        $this->assertEmpty($result); // All slots busy
    }
}
