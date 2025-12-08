<?php

namespace OpenEMR\Modules\NewoAI\Services;

use DateTime;

class NewoAIAvailableSlotsRequest
{
    public string $aid;
    public string $fid;
    public DateTime $dateFrom;
    public DateTime $dateTo;

    public function __construct(string $aid, string $fid, DateTime $dateFrom, DateTime $dateTo)
    {
        $this->aid = $aid;
        $this->fid = $fid;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }
}
