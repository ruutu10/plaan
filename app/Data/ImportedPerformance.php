<?php

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * One show read off a Planka card: who performs, on what night, and for how
 * long. The name is the show's, the date and duration the performance's — the
 * importer splits them across the two models.
 */
readonly class ImportedPerformance
{
    public function __construct(
        public string $showName,
        public Carbon $date,
        public ?int $duration = null,
        public ?int $teamId = null,
    ) {
        //
    }

    /**
     * The key two readings of the same night share. Distinct performers on one
     * date are distinct performances; the same performer twice is one.
     */
    public function fingerprint(): string
    {
        return mb_strtolower($this->showName).'|'.$this->date->toDateString();
    }
}
