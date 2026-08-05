<?php

namespace App\Data;

use App\Models\Performance;

/**
 * One act read off a Planka card: who takes the stage, under what name, when
 * and for how long. Several of these make up an {@see ImportedNight} — a card
 * announcing an Õppelava lists three or four, one after the other.
 *
 * The title is the act as the board writes it, and is empty when the format's own
 * name already says who is playing. The start time is kept apart from the
 * night's date because that is how a card gives them, and because most cards
 * give no time at all; folding the two into the stored moment is
 * {@see Performance::momentFrom()}'s job, and it is the one place the venue's
 * clock is applied.
 */
readonly class ImportedPerformance
{
    /**
     * @param  list<ImportedStaffMember>  $staff  who staffs this act, cast and crew alike
     */
    public function __construct(
        public ?string $title = null,
        public ?string $startTime = null,
        public ?int $duration = null,
        public ?int $teamId = null,
        public array $staff = [],
    ) {
        //
    }

    /**
     * The key two readings of the same act share. An act named on the card is
     * told apart by that name; one the card leaves unnamed has only its place
     * in the running order to go by.
     */
    public function key(int $index): string
    {
        return $this->title === null
            ? "#{$index}"
            : mb_strtolower(trim($this->title));
    }
}
