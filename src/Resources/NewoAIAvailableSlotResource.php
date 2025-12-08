<?php

namespace OpenEMR\Modules\NewoAI\Resources;

use DateTime;
use JsonSerializable;

class NewoAIAvailableSlotResource implements JsonSerializable
{
    private ?DateTime $startTime;
    private ?DateTime $endTime;

    /**
     * @param DateTime|null $startTime
     * @param DateTime|null $endTime
     */
    public function __construct(?DateTime $startTime, ?DateTime $endTime)
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public function getStartTime(): ?DateTime
    {
        return $this->startTime;
    }

    public function getEndTime(): ?DateTime
    {
        return $this->endTime;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'start_time' => $this->startTime?->format('H:i') ?? '',
            'end_time'   => $this->endTime?->format('H:i') ?? '',
        ];
    }
}
