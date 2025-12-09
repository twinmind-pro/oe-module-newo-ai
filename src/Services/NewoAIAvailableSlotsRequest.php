<?php

namespace OpenEMR\Modules\NewoAI\Services;

use DateTime;

class NewoAIAvailableSlotsRequest
{
    public string $aid;
    public string $fid;
    public DateTime $dateFrom;
    public DateTime $dateTo;
    public int $duration;

    public function __construct(string $aid, string $fid, DateTime $dateFrom, DateTime $dateTo, int $duration = 15)
    {
        $this->aid = $aid;
        $this->fid = $fid;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->duration = $duration;
    }
}
