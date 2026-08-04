<?php

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * One night a Planka card announces: the format that is played, the date it is
 * played on, the group whose format it is, and the acts that take the stage in
 * running order.
 *
 * A card describing an evening one troupe fills yields a night with a single
 * act, and the format is named after the troupe — the way the import has always
 * read them. A card describing an evening several groups share yields a night
 * with an act each, and the format is named after the evening. Either way it is
 * one format and one date; the importer splits the night across the two models.
 */
readonly class ImportedNight
{
    /**
     * @param  list<ImportedPerformance>  $performances  the acts, in the order they take the stage
     */
    public function __construct(
        public string $formatName,
        public Carbon $date,
        public ?int $teamId = null,
        public array $performances = [],
    ) {
        //
    }

    /**
     * The key two readings of the same night share. The same format on one date
     * is one night, however many cards mention it; a different format on that
     * date is a night of its own.
     */
    public function fingerprint(): string
    {
        return mb_strtolower($this->formatName).'|'.$this->date->toDateString();
    }
}
