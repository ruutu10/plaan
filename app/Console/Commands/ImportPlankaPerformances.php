<?php

namespace App\Console\Commands;

use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Data\ImportSummary;
use App\Enums\ImportedShowStatus;
use App\Models\ClaudeReasoningLog;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
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
 * one troupe fills is a show named after the troupe, played once; an Õppelava
 * is one show played once with three or four different groups taking the stage
 * in turn, a performance apiece.
 *
 * The command is meant to run over and over: a show is matched by name, a night
 * by that show and its date, and an act by its name within the night, so a card
 * that has already been imported adds nothing the second time. A show the house
 * has never had is created; records an admin has put aside are left alone
 * rather than resurrected — a weekly job must not undo a deletion.
 */
#[Signature('planka:import {--dry-run : Report what would be imported without writing anything}')]
#[Description('Import new shows and performances from the cards of the configured Planka list.')]
class ImportPlankaPerformances extends Command
{
    /**
     * The shows the house has, by {@see showKey()}. Shows created during the
     * run join them, so the second card naming a show finds the first's.
     *
     * @var array<string, Show>
     */
    protected array $shows = [];

    /**
     * The names whose only shows were deleted here, by {@see showKey()}.
     *
     * @var array<string, true>
     */
    protected array $deletedShows = [];

    /**
     * The names a dry run has already reported as new, by {@see showKey()}.
     * A dry run writes nothing, so this is all it has to go on.
     *
     * @var array<string, true>
     */
    protected array $plannedShows = [];

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

        $this->primeShows();

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
            $this->line("  Passing over \"{$card['name']}\": labelled {$label}.");
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
     * are linked — a night added to a show the house already had explains the
     * night, not the show, and the show keeps whatever account it was made with.
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
     * Register one night the card announced: settle its show, then take the
     * acts in turn. An evening several groups share is one show played once,
     * with a performance apiece.
     */
    protected function importNight(ImportedNight $night, ImportSummary $summary, bool $dryRun): void
    {
        $status = $this->resolveShow($night->showName, $night->teamId, $dryRun);

        if ($status === ImportedShowStatus::Deleted) {
            $this->line("  Skipping {$night->showName}: the show was deleted here.");
            $summary->skipped += count($night->performances);

            return;
        }

        if ($status === ImportedShowStatus::Created) {
            $this->line(sprintf(
                '  %s show: %s%s',
                $dryRun ? 'Would create' : 'Creating',
                $night->showName,
                $this->teamNote($night->teamId),
            ));
            $summary->showsCreated++;
        }

        $show = $this->shows[$this->showKey($night->showName)] ?? null;

        if ($this->adoptShow($show, $night->teamId, $dryRun)) {
            $this->line(sprintf(
                '  %s show: %s%s',
                $dryRun ? 'Would hand over' : 'Handing over',
                $night->showName,
                $this->teamNote($night->teamId),
            ));
            $summary->showsAdopted++;
        }

        // Asked once for the whole night rather than once per act: a busy
        // evening is still one query, and the acts are told apart in PHP.
        $known = $this->actsAlreadyOn($show, $night);

        $seen = [];

        foreach ($night->performances as $index => $act) {
            $key = $act->key($index);

            // The same act named twice under one night is one performance.
            if (isset($seen[$key]) || isset($known[$key])) {
                $summary->skipped++;

                continue;
            }

            $seen[$key] = true;

            $this->importPerformance($show, $night, $act, $summary, $dryRun);
        }
    }

    /**
     * Register one act of a night.
     */
    protected function importPerformance(
        ?Show $show,
        ImportedNight $night,
        ImportedPerformance $act,
        ImportSummary $summary,
        bool $dryRun,
    ): void {
        $startsAt = Performance::momentFrom($night->date->toDateString(), $act->startTime);

        $this->line(sprintf(
            '  %s performance: %s%s on %s at %s%s%s',
            $dryRun ? 'Would create' : 'Creating',
            $night->showName,
            $act->title === null ? '' : " — {$act->title}",
            $night->date->toDateString(),
            $startsAt->copy()->setTimezone(Performance::venueTimezone())->format('H:i'),
            $act->startTime === null ? ' (the house\'s usual hour; the card named none)' : '',
            $this->teamNote($act->teamId, 'performed by'),
        ));
        $summary->performancesCreated++;

        if ($dryRun || $show === null) {
            return;
        }

        $created = $show->performances()->create([
            'date' => $startsAt,
            'duration' => $act->duration,
            // Empty unless the night was shared: the show's own name says who
            // is playing, and its own group is who that is.
            'title' => $act->title,
            'team_id' => $act->teamId,
            'planka_card_id' => $this->cardId,
            // What a card announces is a claim, not a booking: it waits as a
            // draft until an admin has looked it over.
            'is_draft' => true,
        ]);

        // The card explains the act, and — because a show like Õppelava is
        // built a night at a time by card after card — the show it went on too.
        // A run that creates nothing writes no reading at all, so a card read
        // again next week does not explain the same show a second time.
        $log = $this->logForCard($dryRun);
        $log?->link($created);
        $log?->link($show);

        Log::info('Registered a performance from a Planka card', [
            'performance_id' => $created->id,
            'show_id' => $show->id,
            'starts_at' => $created->startsAt()->toDateTimeString(),
            // Whether the hour is the card's own or the house's fallback: a
            // season imported entirely at the default hour means the cards
            // stopped carrying times, or the reading of them broke.
            'start_time_from_card' => $act->startTime !== null,
            'duration' => $act->duration,
            'title' => $act->title,
            'team_id' => $act->teamId,
            'is_draft' => true,
        ]);
    }

    /**
     * Hand an ownerless show to the group the AI matched it to. A show that
     * already has one is left as it is: a person put it there, and a weekly
     * job second-guessing that would be worse than leaving the gap.
     */
    protected function adoptShow(?Show $show, ?int $teamId, bool $dryRun): bool
    {
        if ($show === null || $teamId === null || $show->team_id !== null) {
            return false;
        }

        // Set in memory either way, so the next performance of this show in
        // the same run does not report the same hand-over twice.
        $show->team_id = $teamId;

        if (! $dryRun) {
            $show->save();

            Log::info('Handed an ownerless show to a group', [
                'show_id' => $show->id,
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
     * Settle which show the night belongs to, creating one the house has never
     * had. The answer is remembered for the rest of the run, so a name that
     * turns up on a second card — or a second night on the same card — lands on
     * the show settled the first time rather than making another.
     */
    protected function resolveShow(string $name, ?int $teamId, bool $dryRun): ImportedShowStatus
    {
        $key = $this->showKey($name);

        if (isset($this->deletedShows[$key])) {
            return ImportedShowStatus::Deleted;
        }

        if (isset($this->shows[$key]) || isset($this->plannedShows[$key])) {
            return ImportedShowStatus::Existing;
        }

        if ($dryRun) {
            // Nothing is written, so there would be no row to find next time.
            $this->plannedShows[$key] = true;
        } else {
            $show = Show::create([
                'name' => $name,
                'team_id' => $teamId,
            ]);

            $this->shows[$key] = $show;

            $this->logForCard($dryRun)?->link($show);

            Log::info('Created a show from a Planka card', [
                'show_id' => $show->id,
                'name' => $name,
                'team_id' => $teamId,
            ]);
        }

        return ImportedShowStatus::Created;
    }

    /**
     * Take stock of the shows the house already has, so the run can match
     * against them without asking the database once per performance.
     *
     * Matching happens here rather than in SQL because the names are Estonian:
     * SQLite's `LOWER()` leaves "Ä" alone, so "MÄRTU10" and "Märtu10" would be
     * read as two different shows. PHP folds them the same.
     */
    protected function primeShows(): void
    {
        $this->shows = [];
        $this->deletedShows = [];
        $this->plannedShows = [];

        foreach (Show::withTrashed()->get() as $show) {
            $key = $this->showKey($show->name);

            if ($show->trashed()) {
                $this->deletedShows[$key] = true;

                continue;
            }

            $this->shows[$key] = $show;
        }

        // A name the house still has a show for is not a deleted one, however
        // many older shows of that name were put aside.
        $this->deletedShows = array_diff_key($this->deletedShows, $this->shows);
    }

    /**
     * The name a show is matched by: its own, without regard to case, so
     * "JadaJada" and "Jadajada" stay one show.
     */
    protected function showKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * The acts of this night that are already on the books, by the key the
     * reading of the card gives them. A show that does not exist yet (or was
     * not created, in a dry run) has none.
     *
     * A night is matched by its day rather than its moment: the card may have
     * named an hour this time and not last time, and the same act on the same
     * evening is the same performance whatever the board now says about when it
     * starts. The day is the venue's — bracketing the stored UTC by the local
     * midnights, so a late-night show does not read as the day before.
     *
     * Within the night the acts are told apart by their names, folded in PHP
     * for the same reason {@see showKey()} folds there: SQLite's `LOWER()`
     * leaves "Ä" alone. An act the card left unnamed is matched by its place in
     * the running order, which is how a performance registered before the acts
     * were told apart at all — and every one already on the books is — keeps
     * being recognised.
     *
     * @return array<string, true>
     */
    protected function actsAlreadyOn(?Show $show, ImportedNight $night): array
    {
        if ($show === null) {
            return [];
        }

        $dayBegins = Carbon::parse($night->date->toDateString(), Performance::venueTimezone())
            ->startOfDay()
            ->utc();

        $performances = Performance::withTrashed()
            ->where('show_id', $show->id)
            ->where('date', '>=', $dayBegins)
            ->where('date', '<', $dayBegins->copy()->addDay())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $keys = [];
        $unnamed = 0;

        foreach ($performances as $performance) {
            $keys[$performance->title === null
                ? '#'.$unnamed++
                : mb_strtolower(trim($performance->title))] = true;
        }

        return $keys;
    }
}
