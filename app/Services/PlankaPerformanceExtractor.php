<?php

namespace App\Services;

use Anthropic\Client;
use App\Data\ImportedPerformance;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Turns the free-form text of a Planka card into the performances it describes.
 *
 * A card is a producer's notebook — the date, the acts, the crew and the bar
 * rota all in one blob — so the reading is left to Claude, constrained to a
 * JSON schema so what comes back is always shaped like a list of performances.
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

    public function __construct(protected ?Client $client = null)
    {
        //
    }

    /**
     * Read every performance the card describes. Entries the model returns
     * without a usable name or date are dropped rather than guessed at.
     *
     * @return list<ImportedPerformance>
     */
    public function extract(string $cardName, string $cardDescription, ?string $dueDate = null): array
    {
        $userPrompt = $this->buildUserPrompt($cardName, $cardDescription, $dueDate);

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

        $performances = $this->parse($aiResponse);

        // One line per card, so a run that reads 40 cards can be told apart
        // from one that read 40 and understood none of them.
        Log::info('Read a Planka card', [
            'card' => $cardName,
            'performances' => count($performances),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $performances;
    }

    /**
     * Map the model's JSON onto {@see ImportedPerformance} objects.
     *
     * @return list<ImportedPerformance>
     */
    protected function parse(string $aiResponse): array
    {
        $decoded = json_decode($aiResponse, true);

        if (! is_array($decoded) || ! is_array($decoded['performances'] ?? null)) {
            Log::warning('AI returned no readable performances for a Planka card', [
                'aiOutput' => $aiResponse,
            ]);

            return [];
        }

        $performances = [];

        foreach ($decoded['performances'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['show_name'] ?? ''));
            $date = trim((string) ($entry['date'] ?? ''));

            if ($name === '' || ! Carbon::hasFormat($date, 'Y-m-d')) {
                continue;
            }

            $duration = $entry['duration_minutes'] ?? null;
            $teamId = $entry['team_id'] ?? null;

            $performances[] = new ImportedPerformance(
                showName: $name,
                date: Carbon::createFromFormat('Y-m-d', $date)->startOfDay(),
                // A card that names no hour — most of them — leaves this empty
                // and the performance takes the house's usual curtain-up.
                startTime: $this->readStartTime($entry['start_time'] ?? null),
                duration: is_numeric($duration) && (int) $duration > 0 ? (int) $duration : null,
                // A group the model invented owns nothing; only the ids it was
                // given are worth handing a show to.
                teamId: is_numeric($teamId) && isset($this->teams()[(int) $teamId]) ? (int) $teamId : null,
            );
        }

        return $performances;
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
     * The groups a show can be handed to. Personal teams are left out: they
     * stand for a person's own corner of the app, not a performing group.
     *
     * @return array<int, string>
     */
    protected function teams(): array
    {
        return $this->teams ??= Team::query()
            ->where('is_personal', false)
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
                'performances' => [
                    'type' => 'array',
                    'description' => 'Every distinct act performing on the night the card describes.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'show_name' => [
                                'type' => 'string',
                                'description' => 'The performing act as named on the card, without its members.',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'The date of the performance, as YYYY-MM-DD.',
                            ],
                            'start_time' => [
                                'anyOf' => [
                                    ['type' => 'string'],
                                    ['type' => 'null'],
                                ],
                                'description' => 'The time the performance starts, as 24-hour HH:MM, or null when the card does not say. Do not guess a usual hour.',
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
                                'description' => 'Id of the group that owns the show, from the list given, or null when no group is a clear match.',
                            ],
                        ],
                        'required' => ['show_name', 'date', 'start_time', 'duration_minutes', 'team_id'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['performances'],
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
