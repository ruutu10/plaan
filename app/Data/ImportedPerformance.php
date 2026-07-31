<?php

namespace App\Data;

use App\Models\Performance;
use Illuminate\Support\Carbon;

/**
 * One show read off a Planka card: who performs, on what night, at what hour
 * and for how long. The name is the show's, the rest the performance's — the
 * importer splits them across the two models.
 *
 * The date and the start time are kept apart here because that is how a card
 * gives them, and because a card often gives no time at all; folding the two
 * into the stored moment is {@see Performance::momentFrom()}'s job,
 * and it is the one place the venue's clock is applied.
 */
readonly class ImportedPerformance
{
    public function __construct(
        public string $showName,
        public Carbon $date,
        public ?string $startTime = null,
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
