<?php

namespace App\Data;

/**
 * What a Planka import run did, tallied as it goes. The weekly run is read by
 * its closing line, so the counts are the point of the job rather than a
 * by-product of it.
 */
class ImportSummary
{
    public int $showsCreated = 0;

    public int $showsAdopted = 0;

    public int $performancesCreated = 0;

    /** Performances already on the books, or belonging to a deleted show. */
    public int $skipped = 0;

    /** Cards left alone because of a label. */
    public int $passedOver = 0;

    /**
     * The nights this run has already dealt with, by fingerprint. The same show
     * on the same date can be named on two cards, or twice on one; either way
     * it is one night, with the acts the first reading of it gave.
     *
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * Whether this night has already been dealt with, remembering it if not —
     * so the caller asks once rather than checking and then recording.
     */
    public function isNew(ImportedNight $night): bool
    {
        $fingerprint = $night->fingerprint();

        if (isset($this->seen[$fingerprint])) {
            return false;
        }

        $this->seen[$fingerprint] = true;

        return true;
    }

    /**
     * The run's closing line, for whoever is at the terminal.
     */
    public function sentence(bool $dryRun): string
    {
        return sprintf(
            '%s %d show(s) and %d performance(s); %d show(s) handed to a group, %d already known, %d card(s) passed over by label.',
            $dryRun ? 'Would import' : 'Imported',
            $this->showsCreated,
            $this->performancesCreated,
            $this->showsAdopted,
            $this->skipped,
            $this->passedOver,
        );
    }

    /**
     * The same, as log context.
     *
     * @return array<string, int>
     */
    public function context(): array
    {
        return [
            'shows_created' => $this->showsCreated,
            'shows_adopted' => $this->showsAdopted,
            'performances_created' => $this->performancesCreated,
            'skipped' => $this->skipped,
            'passed_over' => $this->passedOver,
        ];
    }
}
