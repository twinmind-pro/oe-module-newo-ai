<?php

namespace OpenEMR\Modules\NewoAI\Resources;

use DateTime;
use JsonSerializable;

class NewoAIAvailableSlotsResource implements JsonSerializable
{
    private ?DateTime $date;

    /** @var NewoAIAvailableSlotResource[]|null */
    private array|null $slots;

    /**
     * @param DateTime|null $date
     * @param NewoAIAvailableSlotResource[]|null $slots
     */
    public function __construct(?DateTime $date, ?array $slots)
    {
        $this->date = $date;
        $this->slots = $slots;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(?DateTime $date): void
    {
        $this->date = $date;
    }

    /**
     * @return NewoAIAvailableSlotResource[]|null
     */
    public function getSlots(): ?array
    {
        return $this->slots;
    }

    /**
     * @param NewoAIAvailableSlotResource[]|null $slots
     * @return void
     */
    public function setSlots(?array $slots): void
    {
        $this->slots = $slots;
    }

    /**
     * @return array<string, string | NewoAIAvailableSlotResource[]>
     */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date?->format('Y-m-d') ?? '',
            'slots' => $this->slots ?? [],
        ];
    }
}
