<?php

namespace App\Services;

use Anthropic\Client;
use App\Data\ImportedNight;
use App\Data\ImportedPerformance;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Turns the free-form text of a Planka card into the nights it describes.
 *
 * A card is a producer's notebook — the date, the acts, the crew and the bar
 * rota all in one blob — so the reading is left to Claude, constrained to a
 * JSON schema so what comes back is always shaped like a list of nights, each
 * with the acts that take its stage.
 */
class PlankaPerformanceExtractor
{
    /**
     * The groups a show can be handed to, by id, read once per run rather than
     * once per card.
     *
     * @var array<int, string>|null
     */
    protected ?array $teams = null;

    /**
     * How the model said it read the card it was last given, kept here so a run
     * can show its reasoning without the nights themselves having to carry it.
     *
     * @var list<string>
     */
    protected array $reasoningNotes = [];

    public function __construct(protected ?Client $client = null)
    {
        //
    }

    /**
     * Read every night the card describes. Entries the model returns without a
     * usable name or date are dropped rather than guessed at.
     *
     * @return list<ImportedNight>
     */
    public function extract(string $cardName, string $cardDescription, ?string $dueDate = null): array
    {
        $userPrompt = $this->buildUserPrompt($cardName, $cardDescription, $dueDate);

        // Whatever the last card was reasoned to, it is not this one's.
        $this->reasoningNotes = [];

        $startedAt = microtime(true);

        $message = $this->client()->messages->create(
            maxTokens: config('services.anthropic.max_tokens'),
            messages: [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            model: config('services.anthropic.model'),
            outputConfig: [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => $this->responseSchema(),
                ],
            ],
            system: $this->buildSystemPrompt(),
            thinking: ['type' => 'disabled'],
        );

        // @phpstan-ignore-next-line property.notFound
        $aiResponse = trim((string) $message->content[0]->text);

        Log::debug('AI extraction for Planka card', [
            'card' => $cardName,
            'aiOutput' => $aiResponse,
        ]);

        $this->reasoningNotes = $this->readReasoningNotes($aiResponse);

        $nights = $this->parse($aiResponse);

        // One line per card, so a run that reads 40 cards can be told apart
        // from one that read 40 and understood none of them.
        Log::info('Read a Planka card', [
            'card' => $cardName,
            'nights' => count($nights),
            'performances' => array_sum(array_map(
                fn (ImportedNight $night): int => count($night->performances),
                $nights,
            )),
            'reasoningNotes' => $this->reasoningNotes,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $nights;
    }

    /**
     * Map the model's JSON onto {@see ImportedNight} objects.
     *
     * @return list<ImportedNight>
     */
    protected function parse(string $aiResponse): array
    {
        $decoded = json_decode($aiResponse, true);

        if (! is_array($decoded) || ! is_array($decoded['shows'] ?? null)) {
            Log::warning('AI returned no readable performances for a Planka card', [
                'aiOutput' => $aiResponse,
            ]);

            return [];
        }

        $nights = [];

        foreach ($decoded['shows'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['show_name'] ?? ''));
            $date = trim((string) ($entry['date'] ?? ''));

            if ($name === '' || ! Carbon::hasFormat($date, 'Y-m-d')) {
                continue;
            }

            $teamId = $this->readTeamId($entry['team_id'] ?? null);

            $nights[] = new ImportedNight(
                showName: $name,
                date: Carbon::createFromFormat('Y-m-d', $date)->startOfDay(),
                teamId: $teamId,
                performances: $this->readPerformances($entry['performances'] ?? null, $name, $teamId),
            );
        }

        return $nights;
    }

    /**
     * The acts of one night, in the order the model listed them.
     *
     * A night the model gave no acts for is still a night: the card names an
     * evening without breaking it down, so it gets the one performance the
     * import has always made of such a card. And an act that is the night's
     * only one is stripped of its title when that title is the show's name
     * again — the show already says who is playing, and every performance
     * already on the books was registered that way.
     *
     * @return list<ImportedPerformance>
     */
    protected function readPerformances(mixed $entries, string $showName, ?int $nightTeamId): array
    {
        $performances = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $title = trim((string) ($entry['title'] ?? ''));
            $duration = $entry['duration_minutes'] ?? null;

            $performances[] = new ImportedPerformance(
                title: $title === '' ? null : $title,
                // A card that names no hour — most of them — leaves this empty
                // and the performance takes the house's usual curtain-up.
                startTime: $this->readStartTime($entry['start_time'] ?? null),
                duration: is_numeric($duration) && (int) $duration > 0 ? (int) $duration : null,
                teamId: $this->readTeamId($entry['team_id'] ?? null),
            );
        }

        if ($performances === []) {
            return [new ImportedPerformance(teamId: $nightTeamId)];
        }

        if (count($performances) === 1 && mb_strtolower((string) $performances[0]->title) === mb_strtolower($showName)) {
            $performances[0] = new ImportedPerformance(
                title: null,
                startTime: $performances[0]->startTime,
                duration: $performances[0]->duration,
                teamId: $performances[0]->teamId,
            );
        }

        return $performances;
    }

    /**
     * Why the last card read the way it did, in the model's own words, for
     * whoever is watching the run. Empty until {@see extract()} has been called,
     * and empty again for a card the model gave no account of.
     *
     * @return list<string>
     */
    public function reasoningNotes(): array
    {
        return $this->reasoningNotes;
    }

    /**
     * The model's own account of how it read the card, logged for whoever has
     * to work out later why a card came out the way it did. Nothing in it is
     * shown to anyone using the app, so anything that is not a list of lines is
     * simply dropped.
     *
     * @return list<string>
     */
    protected function readReasoningNotes(string $aiResponse): array
    {
        $decoded = json_decode($aiResponse, true);
        $notes = is_array($decoded) ? ($decoded['reasoningNotes'] ?? null) : null;

        if (! is_array($notes)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $note): string => trim((string) (is_scalar($note) ? $note : '')), $notes),
            fn (string $note): bool => $note !== '',
        ));
    }

    /**
     * The group the model matched, or null. A group it invented owns nothing;
     * only the ids it was given are worth handing a performance to.
     */
    protected function readTeamId(mixed $teamId): ?int
    {
        return is_numeric($teamId) && isset($this->teams()[(int) $teamId]) ? (int) $teamId : null;
    }

    /**
     * The curtain-up the model read off the card, as "19:00", or null when it
     * found none — or returned something that is not a time of day. A board is
     * written by hand, so "kell 7" and "õhtul" both turn up; anything the
     * format does not cover is better left to the house's usual hour than
     * guessed at.
     */
    protected function readStartTime(mixed $startTime): ?string
    {
        $time = trim((string) ($startTime ?? ''));

        return $time !== '' && Carbon::hasFormat($time, 'H:i') ? $time : null;
    }

    /**
     * The groups a show can be handed to.
     *
     * @return array<int, string>
     */
    protected function teams(): array
    {
        return $this->teams ??= Team::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * The shape the answer must take, enforced by the API rather than hoped for.
     *
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'shows' => [
                    'type' => 'array',
                    'description' => 'Every night the card announces: one entry per show played on one date.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'show_name' => [
                                'type' => 'string',
                                'description' => 'The show played that night: the act\'s name when one act fills the evening, otherwise the name of the evening itself.',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'The date of the night, as YYYY-MM-DD.',
                            ],
                            'team_id' => [
                                'anyOf' => [
                                    ['type' => 'integer'],
                                    ['type' => 'null'],
                                ],
                                'description' => 'Id of the group that owns the show, from the list given, or null when no group is a clear match.',
                            ],
                            'performances' => [
                                'type' => 'array',
                                'description' => 'The acts taking the stage that night, in running order. One entry when a single act fills the evening.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => [
                                            'anyOf' => [
                                                ['type' => 'string'],
                                                ['type' => 'null'],
                                            ],
                                            'description' => 'The act as the card names it, without its members and without any duration note, or null when the show\'s own name already names it.',
                                        ],
                                        'start_time' => [
                                            'anyOf' => [
                                                ['type' => 'string'],
                                                ['type' => 'null'],
                                            ],
                                            'description' => 'The time this act starts, as 24-hour HH:MM, worked out from the evening\'s start and the acts before it, or null when the card gives nothing to work it out from. Do not guess a usual hour.',
                                        ],
                                        'duration_minutes' => [
                                            'anyOf' => [
                                                ['type' => 'integer'],
                                                ['type' => 'null'],
                                            ],
                                            'description' => 'Length of the act in minutes, or null when the card does not say.',
                                        ],
                                        'team_id' => [
                                            'anyOf' => [
                                                ['type' => 'integer'],
                                                ['type' => 'null'],
                                            ],
                                            'description' => 'Id of the group performing this act, from the list given, or null when no group is a clear match.',
                                        ],
                                    ],
                                    'required' => ['title', 'start_time', 'duration_minutes', 'team_id'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['show_name', 'date', 'team_id', 'performances'],
                        'additionalProperties' => false,
                    ],
                ],
                'reasoningNotes' => [
                    'type' => 'array',
                    'description' => 'Why the card was read this way, one short note per decision, for the person debugging an import later: where a date or an hour came from, why a night was split or kept whole, why a group was matched or left null, who was left out as crew, and why a card yielded nothing. Not shown to anyone using the app.',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['shows', 'reasoningNotes'],
            'additionalProperties' => false,
        ];
    }

    /**
     * What to look for on a card, and what to leave alone.
     */
    protected function buildSystemPrompt(): string
    {
        return view('planka.ai-system-prompt')->render();
    }

    /**
     * The card itself. The title carries the show's name when the description
     * names no acts, and the due date supplies the year for the many dates
     * written on the board as a bare day and month. The groups are listed here
     * rather than in the system prompt because they are the app's own, and
     * change as groups come and go.
     */
    protected function buildUserPrompt(string $cardName, string $cardDescription, ?string $dueDate): string
    {
        $due = $dueDate === null
            ? 'puudub'
            : Carbon::parse($dueDate)->toDateString();

        $teams = $this->teams() === []
            ? 'Tiime pole registreeritud — jäta `team_id` alati tühjaks.'
            : collect($this->teams())
                ->map(fn (string $name, int $id): string => "- {$id} — {$name}")
                ->implode("\n");

        return "# Registreeritud tiimid\n\n{$teams}\n\n# Kaardi pealkiri\n\n{$cardName}"
            ."\n\n# Planka tähtaeg\n\n{$due}\n\n# Kaardi kirjeldus\n\n{$cardDescription}";
    }

    /**
     * The API client, built on first use so an unconfigured key only bites the
     * code paths that actually call out.
     */
    protected function client(): Client
    {
        return $this->client ??= new Client(config('services.anthropic.key'));
    }
}
