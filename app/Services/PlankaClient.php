<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A thin client for the Planka REST API — enough of it to fetch the cards of
 * the lists we watch, hand their descriptions on, and write a comment back to
 * a card. Planka ships no PHP SDK worth pulling in for a handful of endpoints,
 * so this speaks HTTP to it directly.
 *
 * `GET /api/lists/{id}` is the endpoint used rather than the more obvious
 * `/api/lists/{id}/cards`: the latter caps a page at 50 cards and its `before`
 * cursor errors out on our board, while this one returns the whole list at once
 * with every card's description alongside.
 */
class PlankaClient
{
    /**
     * Label names by id, per board, so a run asks each board for its labels
     * once however many of its lists we watch.
     *
     * @var array<string, array<string, string>>
     */
    protected array $labelNames = [];

    /**
     * Determine whether enough is configured to reach the board at all.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.planka.url'))
            && filled(config('services.planka.token'))
            && self::listIds() !== [];
    }

    /**
     * Where a card lives on the board, for a person rather than for this
     * client: the same host serves the API and the interface, so the card a
     * record was read off is one link away from the screen showing it. Null
     * when there is no card, or nowhere configured to look for one.
     */
    public static function cardUrl(?string $cardId): ?string
    {
        $url = (string) config('services.planka.url');

        if (blank($cardId) || blank($url)) {
            return null;
        }

        return rtrim($url, '/').'/cards/'.$cardId;
    }

    /**
     * The lists to watch, as configured. Written as one comma-separated env
     * value because a board keeps its seasons in more than one column.
     *
     * @return list<string>
     */
    public static function listIds(): array
    {
        $configured = config('services.planka.list_ids');

        $ids = is_array($configured)
            ? $configured
            : explode(',', (string) $configured);

        return array_values(array_filter(array_map(
            fn (mixed $id): string => trim((string) $id),
            $ids,
        )));
    }

    /**
     * Every card of the given lists, in the order the lists were named and
     * without repeating a card that somehow appears in two of them.
     *
     * @param  list<string>  $listIds
     * @return list<array{id: string, name: string, description: string|null, dueDate: string|null, labels: list<string>}>
     */
    public function cardsInLists(array $listIds): array
    {
        $cards = [];

        foreach ($listIds as $listId) {
            foreach ($this->cardsInList($listId) as $card) {
                $cards[$card['id']] ??= $card;
            }
        }

        return array_values($cards);
    }

    /**
     * Every card of one list. Planka hands the cards back beside the list
     * itself, under `included`, rather than as the list's own payload. The
     * labels come back as bare ids, so their names are looked up on the board.
     *
     * @return list<array{id: string, name: string, description: string|null, dueDate: string|null, labels: list<string>}>
     */
    public function cardsInList(string $listId): array
    {
        $startedAt = microtime(true);

        $response = $this->request()->get("/api/lists/{$listId}")->throw();

        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json('included.cards') ?? [];
        /** @var array<int, array<string, mixed>> $cardLabels */
        $cardLabels = $response->json('included.cardLabels') ?? [];

        $names = $this->labelNames((string) $response->json('item.boardId'));

        $labelsByCard = [];

        foreach ($cardLabels as $cardLabel) {
            $name = $names[(string) ($cardLabel['labelId'] ?? '')] ?? null;

            if ($name !== null) {
                $labelsByCard[(string) ($cardLabel['cardId'] ?? '')][] = $name;
            }
        }

        $cards = [];

        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $cards[] = [
                'id' => $id,
                'name' => (string) ($item['name'] ?? ''),
                'description' => isset($item['description']) ? (string) $item['description'] : null,
                'dueDate' => isset($item['dueDate']) ? (string) $item['dueDate'] : null,
                'labels' => $labelsByCard[$id] ?? [],
            ];
        }

        Log::debug('Fetched a Planka list', [
            'list_id' => $listId,
            'cards' => count($cards),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        // An empty list is not an error, but it is the shape a mis-set list id
        // takes — a run that imports nothing starts here.
        if ($cards === []) {
            Log::warning('A watched Planka list holds no cards', ['list_id' => $listId]);
        }

        return $cards;
    }

    /**
     * What has already been said on a card, as plain text and nothing else —
     * the only thing anything here asks of a comment is whether we wrote it
     * before. Planka serves the comments under `items`, newest first.
     *
     * @return list<string>
     */
    public function commentTexts(string $cardId): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get("/api/cards/{$cardId}/comments")
            ->throw()
            ->json('items') ?? [];

        $texts = [];

        foreach ($items as $item) {
            if (isset($item['text'])) {
                $texts[] = (string) $item['text'];
            }
        }

        return $texts;
    }

    /**
     * Say something on a card, as whoever the API key belongs to. The one
     * write this client makes: everything else it does is reading the board.
     */
    public function comment(string $cardId, string $text): void
    {
        $this->request()
            ->post("/api/cards/{$cardId}/comments", ['text' => $text])
            ->throw();

        Log::info('Commented on a Planka card', ['card_id' => $cardId]);
    }

    /**
     * The board's label names by id. Only the board carries them; a list is
     * served with the joins alone, which name nothing on their own.
     *
     * @return array<string, string>
     */
    protected function labelNames(string $boardId): array
    {
        if ($boardId === '') {
            return [];
        }

        if (isset($this->labelNames[$boardId])) {
            return $this->labelNames[$boardId];
        }

        /** @var array<int, array<string, mixed>> $labels */
        $labels = $this->request()
            ->get("/api/boards/{$boardId}")
            ->throw()
            ->json('included.labels') ?? [];

        $names = [];

        foreach ($labels as $label) {
            $names[(string) ($label['id'] ?? '')] = (string) ($label['name'] ?? '');
        }

        return $this->labelNames[$boardId] = $names;
    }

    /**
     * A request pointed at the configured board and carrying the API key.
     * Planka takes the key in its own header — an `Authorization: Bearer` of
     * the same value is refused with a 401.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.planka.url'), '/'))
            ->withHeader('X-Api-Key', (string) config('services.planka.token'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
