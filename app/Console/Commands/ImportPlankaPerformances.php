<?php

namespace App\Console\Commands;

use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Data\ImportSummary;
use App\Enums\CreatedBy;
use App\Enums\ImportedFormatStatus;
use App\Models\ClaudeReasoningLog;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Services\PerformanceStaffSync;
use App\Services\PlankaClient;
use App\Services\PlankaPerformanceExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read the season's Planka cards and register the performances they announce.
 *
 * A card announces one or more nights, and a night one or more acts: an evening
 * one troupe fills is a format named after the troupe, played once; an Õppelava
 * is one format played once with three or four different groups taking the stage
 * in turn, a performance apiece.
 *
 * The command is meant to run over and over: a format is matched by name, a night
 * by that format and its date, and an act by its name within the night, so a card
 * that has already been imported adds nothing the second time. A format the house
 * has never had is created; records an admin has put aside are left alone
 * rather than resurrected — a weekly job must not undo a deletion.
 */
#[Signature('planka:import {--dry-run : Report what would be imported without writing anything}')]
#[Description('Import new formats and performances from the cards of the configured Planka list.')]
class ImportPlankaPerformances extends Command
{
    /**
     * The formats the house has, by {@see formatKey()}. Formats created during the
     * run join them, so the second card naming a format finds the first's.
     *
     * @var array<string, Format>
     */
    protected array $formats = [];

    /**
     * The names whose only formats were deleted here, by {@see formatKey()}.
     *
     * @var array<string, true>
     */
    protected array $deletedFormats = [];

    /**
     * The names a dry run has already reported as new, by {@see formatKey()}.
     * A dry run writes nothing, so this is all it has to go on.
     *
     * @var array<string, true>
     */
    protected array $plannedFormats = [];

    /**
     * The card in hand, as Planka names it. Kept so the reasoning written for
     * the records it produces can be taken back to the board it came from.
     */
    protected ?string $cardId = null;

    protected ?string $cardName = null;

    /**
     * The kept reasoning for the card in hand, once something has been created
     * to hang it on. See {@see logForCard()}.
     */
    protected ?ClaudeReasoningLog $cardLog = null;

    public function __construct(
        protected PlankaClient $planka,
        protected PlankaPerformanceExtractor $extractor,
        protected PerformanceStaffSync $staffing,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! PlankaClient::isConfigured()) {
            $this->error('Planka is not configured. Set PLANKA_URL, PLANKA_LIST_IDS and PLANKA_ACCESS_TOKEN.');

            // A scheduled run that silently does nothing is worse than one that
            // fails loudly, so a missing configuration is logged, not just told
            // to whoever happens to be at the terminal.
            Log::error('Planka import aborted: the integration is not configured');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $listIds = PlankaClient::listIds();

        Log::info('Planka import started', [
            'dry_run' => $dryRun,
            'lists' => count($listIds),
        ]);

        try {
            $cards = $this->planka->cardsInLists($listIds);
        } catch (Throwable $e) {
            $this->error("Could not read the Planka lists: {$e->getMessage()}");

            Log::error('Planka import aborted: the lists could not be read', [
                'lists' => count($listIds),
                'exception' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->info(sprintf('Read %d card(s) from %d Planka list(s).', count($cards), count($listIds)));

        Log::info('Read the Planka cards', [
            'cards' => count($cards),
            'lists' => count($listIds),
        ]);

        $this->primeFormats();

        $summary = new ImportSummary;

        foreach ($cards as $card) {
            $this->importCard($card, $summary, $dryRun);
        }

        $this->info($summary->sentence($dryRun));

        // The one line a weekly run is read by: what it did, in full.
        Log::info('Planka import finished', [
            'dry_run' => $dryRun,
            'cards' => count($cards),
            ...$summary->context(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Read one card and register whatever it announces.
     *
     * @param  array{id: string, name: string, description: string|null, dueDate: string|null, labels: list<string>}  $card
     */
    protected function importCard(array $card, ImportSummary $summary, bool $dryRun): void
    {
        if (blank($card['description'])) {
            return;
        }

        if ($label = $this->excludedLabelOn($card['labels'])) {
            $this->comment("  Passing over \"{$card['name']}\": labelled {$label}.");
            Log::debug('Passing over a Planka card by label', [
                'card' => $card['id'],
                'label' => $label,
            ]);
            $summary->passedOver++;

            return;
        }

        try {
            $nights = $this->extractor->extract(
                $card['name'],
                $card['description'],
                $card['dueDate'],
                // The board's labels say what kind of evening this is in the
                // producers' own shorthand; the ones that rule a card out have
                // already been dealt with above.
                $card['labels'],
            );
        } catch (Throwable $e) {
            // One unreadable card must not cost us the rest of the season.
            $this->warn("Could not read the card \"{$card['name']}\": {$e->getMessage()}");
            Log::warning('Planka card extraction failed', [
                'card' => $card['id'],
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        // Whatever is created from here on was created by this reading of this
        // card, and the reasoning kept for it is this card's.
        $this->cardId = $card['id'];
        $this->cardName = $card['name'];
        $this->cardLog = null;

        $this->reportReasoning($card['name']);

        foreach ($nights as $night) {
            if ($summary->isNew($night)) {
                $this->importNight($night, $summary, $dryRun);
            }
        }
    }

    /**
     * Say how the AI read the card, before saying what came of it. A card that
     * imports nothing, or imports the wrong thing, is otherwise a silent
     * decision — this is the only place the reasoning behind it is visible
     * without going to the log.
     */
    protected function reportReasoning(string $cardName): void
    {
        $notes = $this->extractor->reasoningNotes();

        if ($notes === []) {
            return;
        }

        $this->line("  Read \"{$cardName}\" as follows:");

        foreach ($notes as $note) {
            $this->line("    - {$note}");
        }
    }

    /**
     * The kept reasoning for the card in hand, made on demand and made once.
     *
     * A card that reads to nothing writes no row: there would be no record to
     * reach it from, and the console and the log have already said what the
     * model made of it. For the same reason only the records this run *creates*
     * are linked — a night added to a format the house already had explains the
     * night, not the format, and the format keeps whatever account it was made with.
     */
    protected function logForCard(bool $dryRun): ?ClaudeReasoningLog
    {
        $notes = $this->extractor->reasoningNotes();

        if ($dryRun || $notes === []) {
            return null;
        }

        return $this->cardLog ??= ClaudeReasoningLog::create([
            'card_id' => $this->cardId,
            'card_name' => $this->cardName,
            'notes' => $notes,
        ]);
    }

    /**
     * Register one night the card announced: settle its format, then take the
     * acts in turn. An evening several groups share is one format played once,
     * with a performance apiece.
     */
    protected function importNight(ImportedNight $night, ImportSummary $summary, bool $dryRun): void
    {
        $status = $this->resolveFormat($night->formatName, $night->teamId, $dryRun);

        if ($status === ImportedFormatStatus::Deleted) {
            $this->comment("  Skipping {$night->formatName}: the format was deleted here.");
            $summary->skipped += count($night->performances);

            return;
        }

        if ($status === ImportedFormatStatus::Created) {
            $this->info(sprintf(
                '  %s format: %s%s',
                $dryRun ? 'Would create' : 'Creating',
                $night->formatName,
                $this->teamNote($night->teamId),
            ));
            $summary->formatsCreated++;
        }

        $format = $this->formats[$this->formatKey($night->formatName)] ?? null;

        if ($this->adoptFormat($format, $night->teamId, $dryRun)) {
            $this->output->writeln(sprintf(
                '  <fg=cyan>%s format: %s%s</>',
                $dryRun ? 'Would hand over' : 'Handing over',
                $night->formatName,
                $this->teamNote($night->teamId),
            ));
            $summary->formatsAdopted++;
        }

        // Asked once for the whole night rather than once per act: a busy
        // evening is still one query, and the acts are told apart in PHP.
        $known = $this->actsAlreadyOn($format, $night);

        $seen = [];

        foreach ($night->performances as $index => $act) {
            $key = $act->key($index);

            // The same act named twice under one night is one performance.
            if (isset($seen[$key])) {
                $summary->skipped++;

                continue;
            }

            $seen[$key] = true;

            if (isset($known[$key])) {
                $summary->skipped++;

                // Everything else about an act already on the books is left
                // exactly as it is — see importCard()'s doc comment — but the
                // staff table is the one thing nobody edits by hand, so a card
                // that changed its crew still overwrites it, even on a night
                // that adds nothing new.
                $this->syncStaff($known[$key], $act, $dryRun);

                continue;
            }

            $this->importPerformance($format, $night, $act, $summary, $dryRun);
        }
    }

    /**
     * Write this act's staff exactly as the card gives it, replacing whatever
     * was there before. A performance a technician has since put aside is left
     * alone — its staff table is not worth resurrecting along with it — and
     * nothing is written in a dry run, which reports what it would do without
     * touching the database.
     */
    protected function syncStaff(Performance $performance, ImportedPerformance $act, bool $dryRun): void
    {
        if ($dryRun || $performance->trashed()) {
            return;
        }

        $this->staffing->sync($performance, $act->staff);
    }

    /**
     * Register one act of a night.
     */
    protected function importPerformance(
        ?Format $format,
        ImportedNight $night,
        ImportedPerformance $act,
        ImportSummary $summary,
        bool $dryRun,
    ): void {
        $startsAt = Performance::momentFrom($night->date->toDateString(), $act->startTime);

        // A last, literal check against the database itself: two Planka cards
        // can describe one performance, and {@see actsAlreadyOn()} already
        // guards the whole night at once, but this asks again for the one act
        // about to be written, so a way that check was fooled still cannot
        // leave two rows behind. Asked only for a titled act — one the card
        // leaves unnamed has nothing but its place in the running order to
        // tell it from a sibling act the night also left unnamed, and a
        // literal title match would wrongly treat the second as the first's
        // duplicate.
        if ($format !== null && $act->title !== null && $this->performanceAlreadyRecorded($format, $act->title, $night->date)) {
            $summary->skipped++;

            // The batched check in importNight() should already have caught
            // this; reaching here means it did not, which is worth knowing
            // about even though the outcome — no duplicate written — is right.
            Log::warning('Skipped a performance already on the books, caught only by the final database check', [
                'card' => $this->cardId,
                'format_id' => $format->id,
                'title' => $act->title,
                'date' => $night->date->toDateString(),
            ]);

            return;
        }

        $this->info(sprintf(
            '  %s performance: %s%s on %s at %s%s%s',
            $dryRun ? 'Would create' : 'Creating',
            $night->formatName,
            $act->title === null ? '' : " — {$act->title}",
            $night->date->toDateString(),
            $startsAt->copy()->setTimezone(Performance::venueTimezone())->format('H:i'),
            $act->startTime === null ? ' (the house\'s usual hour; the card named none)' : '',
            $this->teamNote($act->teamId, 'performed by'),
        ));
        $summary->performancesCreated++;

        if ($dryRun || $format === null) {
            return;
        }

        $created = $format->performances()->create([
            'date' => $startsAt,
            'duration' => $act->duration,
            // Empty unless the night was shared: the format's own name says who
            // is playing, and its own group is who that is.
            'title' => $act->title,
            'team_id' => $act->teamId,
            'planka_card_id' => $this->cardId,
            // What a card announces is a claim, not a booking: it waits as a
            // draft until an admin has looked it over.
            'is_draft' => true,
            // Nobody chose this date; a card did. The screens say so, so a
            // performance that looks wrong is taken back to the board rather
            // than to whoever is assumed to have typed it.
            'created_by' => CreatedBy::PlankaImport,
        ]);

        // The card explains the act, and — because a format like Õppelava is
        // built a night at a time by card after card — the format it went on too.
        // A run that creates nothing writes no reading at all, so a card read
        // again next week does not explain the same format a second time.
        $log = $this->logForCard($dryRun);
        $log?->link($created);
        $log?->link($format);

        $this->staffing->sync($created, $act->staff);

        Log::info('Registered a performance from a Planka card', [
            'performance_id' => $created->id,
            'format_id' => $format->id,
            'starts_at' => $created->startsAt()->toDateTimeString(),
            // Whether the hour is the card's own or the house's fallback: a
            // season imported entirely at the default hour means the cards
            // stopped carrying times, or the reading of them broke.
            'start_time_from_card' => $act->startTime !== null,
            'duration' => $act->duration,
            'title' => $act->title,
            'team_id' => $act->teamId,
            'is_draft' => true,
            'created_by' => CreatedBy::PlankaImport->value,
        ]);
    }

    /**
     * Hand an ownerless format to the group the AI matched it to. A format that
     * already has one is left as it is: a person put it there, and a weekly
     * job second-guessing that would be worse than leaving the gap.
     */
    protected function adoptFormat(?Format $format, ?int $teamId, bool $dryRun): bool
    {
        if ($format === null || $teamId === null || $format->team_id !== null) {
            return false;
        }

        // Set in memory either way, so the next performance of this format in
        // the same run does not report the same hand-over twice.
        $format->team_id = $teamId;

        if (! $dryRun) {
            $format->save();

            Log::info('Handed an ownerless format to a group', [
                'format_id' => $format->id,
                'team_id' => $teamId,
            ]);
        }

        return true;
    }

    /**
     * How a matched group reads in the run's output, if one was matched.
     */
    protected function teamNote(?int $teamId, string $role = 'owner'): string
    {
        if ($teamId === null) {
            return '';
        }

        $name = Team::query()->whereKey($teamId)->value('name');

        return " ({$role}: {$name})";
    }

    /**
     * The excluded label the card carries, if any. Board labels are written in
     * capitals but matched without regard to case, so a renamed label keeps
     * working as long as the word does.
     *
     * @param  list<string>  $labels
     */
    protected function excludedLabelOn(array $labels): ?string
    {
        /** @var array<int, string> $excluded */
        $excluded = config('services.planka.excluded_labels', []);

        $excluded = array_map(mb_strtolower(...), $excluded);

        foreach ($labels as $label) {
            if (in_array(mb_strtolower($label), $excluded, true)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Settle which format the night belongs to, creating one the house has never
     * had. The answer is remembered for the rest of the run, so a name that
     * turns up on a second card — or a second night on the same card — lands on
     * the format settled the first time rather than making another.
     */
    protected function resolveFormat(string $name, ?int $teamId, bool $dryRun): ImportedFormatStatus
    {
        $key = $this->formatKey($name);

        if (isset($this->deletedFormats[$key])) {
            return ImportedFormatStatus::Deleted;
        }

        if (isset($this->formats[$key]) || isset($this->plannedFormats[$key])) {
            return ImportedFormatStatus::Existing;
        }

        if ($dryRun) {
            // Nothing is written, so there would be no row to find next time.
            $this->plannedFormats[$key] = true;
        } else {
            $format = Format::create([
                'name' => $name,
                'team_id' => $teamId,
                // Nobody entered this format; a card named it and the house had
                // never had it. See the performances, registered the same way.
                'created_by' => CreatedBy::PlankaImport,
            ]);

            $this->formats[$key] = $format;

            $this->logForCard($dryRun)?->link($format);

            Log::info('Created a format from a Planka card', [
                'format_id' => $format->id,
                'name' => $name,
                'team_id' => $teamId,
                'created_by' => CreatedBy::PlankaImport->value,
            ]);
        }

        return ImportedFormatStatus::Created;
    }

    /**
     * Take stock of the formats the house already has, so the run can match
     * against them without asking the database once per performance.
     *
     * Matching happens here rather than in SQL because the names are Estonian:
     * SQLite's `LOWER()` leaves "Ä" alone, so "MÄRTU10" and "Märtu10" would be
     * read as two different formats. PHP folds them the same.
     */
    protected function primeFormats(): void
    {
        $this->formats = [];
        $this->deletedFormats = [];
        $this->plannedFormats = [];

        foreach (Format::withTrashed()->get() as $format) {
            $key = $this->formatKey($format->name);

            if ($format->trashed()) {
                $this->deletedFormats[$key] = true;

                continue;
            }

            $this->formats[$key] = $format;
        }

        // A name the house still has a format for is not a deleted one, however
        // many older formats of that name were put aside.
        $this->deletedFormats = array_diff_key($this->deletedFormats, $this->formats);
    }

    /**
     * The name a format is matched by: its own, without regard to case, so
     * "JadaJada" and "Jadajada" stay one format.
     */
    protected function formatKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * The acts of this night that are already on the books, by the key the
     * reading of the card gives them. A format that does not exist yet (or was
     * not created, in a dry run) has none.
     *
     * A night is matched by its day rather than its moment: the card may have
     * named an hour this time and not last time, and the same act on the same
     * evening is the same performance whatever the board now says about when it
     * starts. The day is the venue's — bracketing the stored UTC by the local
     * midnights, so a late-night format does not read as the day before.
     *
     * Within the night the acts are told apart by their names, folded in PHP
     * for the same reason {@see formatKey()} folds there: SQLite's `LOWER()`
     * leaves "Ä" alone. An act the card left unnamed is matched by its place in
     * the running order, which is how a performance registered before the acts
     * were told apart at all — and every one already on the books is — keeps
     * being recognised.
     *
     * The performance itself rides along with its key rather than a bare
     * marker: an act the card still describes has its staff re-synced even
     * when nothing else about it is touched — see {@see importNight()}.
     *
     * @return array<string, Performance>
     */
    protected function actsAlreadyOn(?Format $format, ImportedNight $night): array
    {
        if ($format === null) {
            return [];
        }

        [$dayBegins, $dayEnds] = $this->venueDayBounds($night->date);

        $performances = Performance::withTrashed()
            ->where('format_id', $format->id)
            ->where('date', '>=', $dayBegins)
            ->where('date', '<', $dayEnds)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $keys = [];
        $unnamed = 0;

        foreach ($performances as $performance) {
            $keys[$performance->title === null
                ? '#'.$unnamed++
                : mb_strtolower(trim($performance->title))] = $performance;
        }

        return $keys;
    }

    /**
     * Whether the database already holds a performance for this exact act: the
     * same format, the same venue-local day, and the same title. A final,
     * literal check asked again right before a titled act is written — see
     * {@see importPerformance()} for why one more question is asked of the
     * database when {@see actsAlreadyOn()} has already answered for the night.
     *
     * Title is matched folded and trimmed in PHP rather than in SQL, for the
     * reason {@see primeFormats()} does the same: SQLite's `LOWER()` leaves
     * Estonian capitals alone.
     */
    protected function performanceAlreadyRecorded(Format $format, string $title, Carbon $date): bool
    {
        [$dayBegins, $dayEnds] = $this->venueDayBounds($date);
        $title = mb_strtolower(trim($title));

        return Performance::withTrashed()
            ->where('format_id', $format->id)
            ->where('date', '>=', $dayBegins)
            ->where('date', '<', $dayEnds)
            ->get(['title'])
            ->contains(fn (Performance $performance): bool => $performance->title !== null
                && mb_strtolower(trim($performance->title)) === $title);
    }

    /**
     * The UTC bounds of one venue-local day, for bracketing a stored UTC date
     * by the local midnights it falls between — see {@see actsAlreadyOn()} for
     * why a night is matched by its day rather than its moment.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function venueDayBounds(Carbon $date): array
    {
        $begins = Carbon::parse($date->toDateString(), Performance::venueTimezone())
            ->startOfDay()
            ->utc();

        return [$begins, $begins->copy()->addDay()];
    }
}
