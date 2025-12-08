<?php

namespace OpenEMR\Modules\NewoAI\Resources;

use DateTime;
use JsonSerializable;

class NewoAIAvailableSlotsResource implements JsonSerializable
{
    private ?DateTime $date;

    /** @var NewoAIAvailableSlotsResource[]|null */
    private array|null $slots;

    /**
     * @param DateTime|null $date
     * @param NewoAIAvailableSlotsResource[]|null $slots
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
     * @return NewoAIAvailableSlotsResource[]|null
     */
    public function getSlots(): ?array
    {
        return $this->slots;
    }

    /**
     * @param NewoAIAvailableSlotsResource[]|null $slots
     * @return void
     */
    public function setSlots(?array $slots): void
    {
        $this->slots = $slots;
    }

    /**
     * @return array<string, string | NewoAIAvailableSlotsResource[]>
     */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date?->format('Y-m-d') ?? '',
            'slots' => $this->slots ?? [],
        ];
    }
}
