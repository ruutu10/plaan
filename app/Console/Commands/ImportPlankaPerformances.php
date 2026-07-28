<?php

namespace App\Console\Commands;

use App\Data\ImportedPerformance;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Services\PlankaClient;
use App\Services\PlankaPerformanceExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read the season's Planka cards and register the performances they announce.
 *
 * The command is meant to run over and over: a show is matched by name and a
 * performance by that show and its date, so a card that has already been
 * imported adds nothing the second time. A show the house has never had is
 * created; records an admin has put aside are left alone rather than
 * resurrected — a weekly job must not undo a deletion.
 */
#[Signature('planka:import-performances {--dry-run : Report what would be imported without writing anything}')]
#[Description('Import new shows and performances from the cards of the configured Planka list.')]
class ImportPlankaPerformances extends Command
{
    /**
     * The shows the house has, by {@see showKey()}. Shows created during the
     * run join them, so the second card naming an act finds the first's show.
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

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $listIds = PlankaClient::listIds();

        try {
            $cards = $this->planka->cardsInLists($listIds);
        } catch (Throwable $e) {
            $this->error("Could not read the Planka lists: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf('Read %d card(s) from %d Planka list(s).', count($cards), count($listIds)));

        $this->primeShows();

        $showsCreated = 0;
        $showsAdopted = 0;
        $performancesCreated = 0;
        $skipped = 0;
        $passedOver = 0;
        $seen = [];

        foreach ($cards as $card) {
            if (blank($card['description'])) {
                continue;
            }

            if ($label = $this->excludedLabelOn($card['labels'])) {
                $this->line("  Passing over \"{$card['name']}\": labelled {$label}.");
                $passedOver++;

                continue;
            }

            try {
                $performances = $this->extractor->extract($card['name'], $card['description'], $card['dueDate']);
            } catch (Throwable $e) {
                // One unreadable card must not cost us the rest of the season.
                $this->warn("Could not read the card \"{$card['name']}\": {$e->getMessage()}");
                Log::warning('Planka card extraction failed', [
                    'card' => $card['id'],
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($performances as $performance) {
                // The same act can be named twice on one card, or spread over
                // two cards for the same night; either way it is one staging.
                if (isset($seen[$performance->fingerprint()])) {
                    continue;
                }

                $seen[$performance->fingerprint()] = true;

                $status = $this->resolveShow($performance->showName, $performance->teamId, $dryRun);

                if ($status === 'deleted') {
                    $this->line("  Skipping {$performance->showName}: the show was deleted here.");
                    $skipped++;

                    continue;
                }

                if ($status === 'created') {
                    $this->line(sprintf(
                        '  %s show: %s%s',
                        $dryRun ? 'Would create' : 'Creating',
                        $performance->showName,
                        $this->teamNote($performance->teamId),
                    ));
                    $showsCreated++;
                }

                $show = $this->shows[$this->showKey($performance->showName)] ?? null;

                if ($this->adoptShow($show, $performance->teamId, $dryRun)) {
                    $this->line(sprintf(
                        '  %s show: %s%s',
                        $dryRun ? 'Would hand over' : 'Handing over',
                        $performance->showName,
                        $this->teamNote($performance->teamId),
                    ));
                    $showsAdopted++;
                }

                if ($this->performanceExists($show, $performance)) {
                    $skipped++;

                    continue;
                }

                $this->line(sprintf(
                    '  %s performance: %s on %s',
                    $dryRun ? 'Would create' : 'Creating',
                    $performance->showName,
                    $performance->date->toDateString(),
                ));
                $performancesCreated++;

                if (! $dryRun && $show !== null) {
                    $show->performances()->create([
                        'date' => $performance->date,
                        'duration' => $performance->duration,
                    ]);
                }
            }
        }

        $this->info(sprintf(
            '%s %d show(s) and %d performance(s); %d show(s) handed to a group, %d already known, %d card(s) passed over by label.',
            $dryRun ? 'Would import' : 'Imported',
            $showsCreated,
            $performancesCreated,
            $showsAdopted,
            $skipped,
            $passedOver,
        ));

        return self::SUCCESS;
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
        }

        return true;
    }

    /**
     * How the owning group reads in the run's output, if one was matched.
     */
    protected function teamNote(?int $teamId): string
    {
        if ($teamId === null) {
            return '';
        }

        $name = Team::query()->whereKey($teamId)->value('name');

        return " (owner: {$name})";
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
     * Settle which show the named act belongs to, creating one the house has
     * never had. The answer is remembered for the rest of the run, so a name
     * that turns up on a second card — or a second night on the same card —
     * lands on the show settled the first time rather than making another.
     *
     * @return 'existing'|'created'|'deleted'
     */
    protected function resolveShow(string $name, ?int $teamId, bool $dryRun): string
    {
        $key = $this->showKey($name);

        if (isset($this->deletedShows[$key])) {
            return 'deleted';
        }

        if (isset($this->shows[$key]) || isset($this->plannedShows[$key])) {
            return 'existing';
        }

        if ($dryRun) {
            // Nothing is written, so there would be no row to find next time.
            $this->plannedShows[$key] = true;
        } else {
            $this->shows[$key] = Show::create(['name' => $name, 'team_id' => $teamId]);
        }

        return 'created';
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
     * Determine whether this staging is already on the books. A show that does
     * not exist yet (or was not created, in a dry run) has none.
     */
    protected function performanceExists(?Show $show, ImportedPerformance $performance): bool
    {
        if ($show === null) {
            return false;
        }

        return Performance::withTrashed()
            ->where('show_id', $show->id)
            ->whereDate('date', $performance->date)
            ->exists();
    }
}
