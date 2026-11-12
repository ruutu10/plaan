<?php

namespace App\Services;

use App\Data\RecordingFile;
use App\Rules\JellyfinItemUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use stdClass;

/**
 * A thin client for the Jellyfin REST API — enough of it to read one library
 * item and write a changed version back. Jellyfin ships no PHP SDK worth
 * pulling in for two endpoints, so this speaks HTTP to it directly.
 *
 * Writing an item is `POST /Items/{id}`, and it takes the whole item, not the
 * part being changed: whatever the request leaves out is cleared. So every
 * write here is a read first — {@see applyToItem()} fetches the item, lays the
 * new values over it and posts the result back. That is not caution, it is the
 * only correct way to use the endpoint.
 *
 * It also means the read has to ask for what it intends to keep. A bare read
 * leaves several fields out of its answer — on a real library, the cast, the
 * studios, the tags and the provider ids among them — and a field left out of
 * the answer is a field the write would clear, so {@see FIELDS} names them and
 * {@see item()} always sends it.
 *
 * Both lines of Jellyfin are spoken here: 10.x and 12.x differ over how a
 * single item is fetched, so {@see item()} uses the query form that has not
 * changed rather than the route that has.
 *
 * The key this authenticates with is an administrator's: writing an item needs
 * elevation, and a user's own token is refused.
 */
class JellyfinClient
{
    /**
     * The fields a bare read leaves out and a write would therefore clear.
     * Asked for by name on every read — see the class docblock.
     */
    private const FIELDS = 'Overview,People,Studios,Genres,Tags,ProviderIds,Taglines,ProductionLocations,DateCreated';

    /**
     * The properties Jellyfin holds as maps rather than lists.
     *
     * PHP cannot tell an empty map from an empty list — both are `[]` — and
     * JSON can, so an empty one of these goes out as `[]` and is refused with a
     * binding error naming the field. An item with no provider ids and no
     * artwork is the ordinary case for a freshly scanned recording, so this is
     * not an edge: it is most of them. See {@see asJsonMaps()}.
     */
    private const MAPS = ['ProviderIds', 'ImageTags', 'ImageBlurHashes'];

    /**
     * The only kind of item this app ever writes to.
     *
     * The library files a format as a series, a season of it as a season, and
     * one night as an episode. Only the night is ours to describe — and a
     * series or a season is a folder, whose tags Jellyfin hands down to every
     * child, so a link pointing at one by mistake would rewrite a format's
     * whole run rather than one evening of it.
     */
    private const EPISODE = 'Episode';

    /**
     * How Jellyfin spells Estonian on a stream: the ISO 639-2 code a scan
     * writes, or the two-letter one a file named `.et.srt` is read as.
     */
    private const ESTONIAN = ['est', 'et'];

    /**
     * Determine whether enough is configured to reach the library at all.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.jellyfin.url'))
            && filled(config('services.jellyfin.api_key'));
    }

    /**
     * The item a pasted address names, or null when it names none.
     *
     * Jellyfin's interface writes an item's address in more than one shape
     * depending on how it is reached — `/web/#/details?id=...` and
     * `/web/index.html#!/details?id=...` are both current — and somebody
     * copying an id out of a URL bar by hand hands over the id alone. All of
     * them are read, and the answer is always the dash-less lowercase hex the
     * API itself takes, so two spellings of one item are one item here.
     *
     * Never throws: a link that names nothing is a thing a person typed, which
     * is {@see JellyfinItemUrl}'s to complain about in Estonian.
     */
    public static function itemIdFromUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $url = trim($url);

        $id = preg_match('/[?&#\/]id=([0-9a-fA-F-]{32,36})/', $url, $matches) === 1
            ? $matches[1]
            : $url;

        $id = strtolower(str_replace('-', '', $id));

        return preg_match('/^[0-9a-f]{32}$/', $id) === 1 ? $id : null;
    }

    /**
     * Where an item lives on the server, for a person rather than for this
     * client: the same host serves the API and the interface, so the episode a
     * link was accepted as is one click away from the screen showing it. Null
     * when there is no item, or no server configured to look on.
     *
     * Always the public address, never {@see apiBaseUrl()}: a person cannot
     * reach the cluster-internal one.
     */
    public static function itemUrl(?string $itemId): ?string
    {
        $url = (string) config('services.jellyfin.url');

        if (blank($itemId) || blank($url)) {
            return null;
        }

        return rtrim($url, '/').'/web/#/details?id='.$itemId;
    }

    /**
     * One library item, whole — see the class docblock on why the fields are
     * asked for by name.
     *
     * Read through the item *query* rather than `GET /Items/{id}`, which looks
     * like the obvious way to fetch one item and is not: that route only exists
     * from Jellyfin 12, and a 10.x server answers it with a bare 400 that says
     * nothing about why. The query form is the one both lines have spoken all
     * along, so this asks for a list of exactly one id and takes what comes
     * back.
     *
     * @return array<string, mixed>
     */
    public function item(string $itemId): array
    {
        $startedAt = microtime(true);

        $items = $this->request()
            ->get('/Items', ['ids' => $itemId, 'fields' => self::FIELDS])
            ->throw()
            ->json('Items');

        $item = is_array($items) ? ($items[0] ?? null) : null;

        // A library that has never heard of the item answers with an empty list
        // rather than a 404, so "no such episode" arrives here as a success.
        if (! is_array($item)) {
            throw new RuntimeException(sprintf(
                'Jellyfin holds no item %s.',
                $itemId,
            ));
        }

        Log::debug('Fetched a Jellyfin item', [
            'item_id' => $itemId,
            'type' => $item['Type'] ?? null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $item;
    }

    /**
     * Write an item back, whole. Anything left out of `$item` is cleared, so
     * callers want {@see applyToItem()} rather than this.
     *
     * @param  array<string, mixed>  $item
     */
    public function updateItem(string $itemId, array $item): void
    {
        $this->request()->post("/Items/{$itemId}", $this->asJsonMaps($item))->throw();

        Log::info('Updated a Jellyfin item', [
            'item_id' => $itemId,
            'fields' => count($item),
        ]);
    }

    /**
     * Lay the given values over an item and write it back, leaving everything
     * else as the library holds it.
     *
     * Refuses anything that is not an episode: see {@see EPISODE}.
     *
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed> the item as it was written
     */
    public function applyToItem(string $itemId, array $patch): array
    {
        $item = $this->item($itemId);

        $type = $item['Type'] ?? null;

        if ($type !== self::EPISODE) {
            throw new RuntimeException(sprintf(
                'Refusing to write to Jellyfin item %s: it is a %s, not an episode.',
                $itemId,
                $type === null ? 'record of no stated kind' : (string) $type,
            ));
        }

        $payload = array_replace($item, $patch);

        $this->updateItem($itemId, $payload);

        return $payload;
    }

    /**
     * The file behind an item: its name, size and when it was added to the
     * library. Null when the library has no such item or no file for it.
     *
     * Asked with a short timeout and no retries, because it is only ever shown
     * beside a download and must not hold up the page or letter showing it.
     */
    public function file(string $itemId): ?RecordingFile
    {
        $item = $this->connection()
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(5)
            ->get('/Items', ['ids' => $itemId, 'fields' => 'MediaSources,DateCreated'])
            ->throw()
            ->json('Items.0');

        $source = is_array($item) ? ($item['MediaSources'][0] ?? null) : null;

        if (! is_array($source) || blank($source['Path'] ?? null) || ! is_numeric($source['Size'] ?? null)) {
            return null;
        }

        // The server's own path to the file stays on the server: only its
        // last segment is a name worth showing.
        $name = basename(str_replace('\\', '/', (string) $source['Path']));
        $addedAt = $item['DateCreated'] ?? null;

        return new RecordingFile(
            $name,
            (int) $source['Size'],
            is_string($addedAt) ? CarbonImmutable::parse($addedAt) : null,
        );
    }

    /**
     * The Estonian text subtitles on an item, or null while it has none.
     *
     * The library's speech-to-text writes them some while after a video is
     * added, so "none yet" is the ordinary answer for a fresh recording, not a
     * fault. Only text streams count: a picture-based track has no words in it
     * to read a plot out of.
     *
     * A stream's index counts within its media source, so the source's id
     * travels with it — {@see subtitleText()} needs both, from the same source.
     *
     * @return array{mediaSourceId: string, index: int}|null
     */
    public function estonianSubtitle(string $itemId): ?array
    {
        $item = $this->request()
            ->get('/Items', ['ids' => $itemId, 'fields' => 'MediaSources'])
            ->throw()
            ->json('Items.0');

        return is_array($item) ? self::estonianSubtitleOf($item) : null;
    }

    /**
     * The Estonian text subtitles on an item already read with its
     * `MediaSources`, or null when it has none — see {@see estonianSubtitle()}.
     *
     * @param  array<string, mixed>  $item
     * @return array{mediaSourceId: string, index: int}|null
     */
    public static function estonianSubtitleOf(array $item): ?array
    {
        $source = $item['MediaSources'][0] ?? null;

        if (! is_array($source) || blank($source['Id'] ?? null)) {
            return null;
        }

        foreach ($source['MediaStreams'] ?? [] as $stream) {
            if (! is_array($stream)
                || ($stream['Type'] ?? null) !== 'Subtitle'
                || ($stream['IsTextSubtitleStream'] ?? false) !== true
                || ! in_array(strtolower((string) ($stream['Language'] ?? '')), self::ESTONIAN, true)
                || ! is_int($stream['Index'] ?? null)) {
                continue;
            }

            return ['mediaSourceId' => (string) $source['Id'], 'index' => $stream['Index']];
        }

        return null;
    }

    /**
     * Every series in the library, across all of its libraries, a page at a
     * time.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function allSeries(): LazyCollection
    {
        return $this->paged(['includeItemTypes' => 'Series', 'recursive' => 'true', 'sortBy' => 'SortName']);
    }

    /**
     * Every episode of a series, with what a backfill needs to judge it: its
     * overview, tags, people and media streams.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function episodesOf(string $seriesId): LazyCollection
    {
        return $this->paged([
            'parentId' => $seriesId,
            'includeItemTypes' => self::EPISODE,
            'recursive' => 'true',
            'sortBy' => 'ParentIndexNumber,IndexNumber,SortName',
            'fields' => 'Overview,Tags,People,MediaSources',
        ]);
    }

    /**
     * An item query read through to its end, one page at a time, so a large
     * library is never held in memory whole.
     *
     * @param  array<string, string>  $query
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function paged(array $query, int $pageSize = 100): LazyCollection
    {
        return LazyCollection::make(function () use ($query, $pageSize) {
            $start = 0;

            do {
                $page = $this->request()
                    ->get('/Items', [...$query, 'startIndex' => $start, 'limit' => $pageSize])
                    ->throw()
                    ->json();

                $items = is_array($page['Items'] ?? null) ? $page['Items'] : [];

                foreach ($items as $item) {
                    if (is_array($item)) {
                        yield $item;
                    }
                }

                $start += count($items);
                $total = (int) ($page['TotalRecordCount'] ?? 0);
            } while ($items !== [] && $start < $total);
        });
    }

    /**
     * One subtitle stream of an item, as SRT text. The server converts
     * whatever the track is stored as.
     */
    public function subtitleText(string $itemId, string $mediaSourceId, int $index): string
    {
        return $this->connection()
            ->timeout(60)
            ->retry(2, 500)
            ->get("/Videos/{$itemId}/{$mediaSourceId}/Subtitles/{$index}/Stream.srt")
            ->throw()
            ->body();
    }

    /**
     * Open an episode's original file for reading, without fetching any of it.
     *
     * The answer's body is a live stream the caller reads and closes: these
     * files run to several gigabytes, so nothing may buffer them, and a request
     * retried or cut off by a total timeout would be a download thrown away.
     * Only a stalled connection gives up.
     *
     * Refuses anything that is not an episode, for the reason {@see EPISODE}
     * gives: a link pointing at a folder names no one video.
     *
     * @throws RuntimeException when the item is missing or is not an episode
     */
    public function download(string $itemId, ?string $range = null, ?string $ifRange = null): Response
    {
        $type = $this->item($itemId)['Type'] ?? null;

        if ($type !== self::EPISODE) {
            throw new RuntimeException(sprintf(
                'Refusing to download Jellyfin item %s: it is a %s, not an episode.',
                $itemId,
                $type === null ? 'record of no stated kind' : (string) $type,
            ));
        }

        return $this->connection()
            ->withHeaders(array_filter(['Range' => $range, 'If-Range' => $ifRange]))
            ->connectTimeout(10)
            ->timeout(0)
            ->withOptions(['stream' => true, 'read_timeout' => 60])
            ->get("/Items/{$itemId}/Download");
    }

    /**
     * Spell the empty maps as maps — see {@see MAPS} for why they would
     * otherwise go out as lists and have the whole write refused.
     *
     * Only the empty ones need it: one with anything in it is an associative
     * array already, and encodes as an object of its own accord.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function asJsonMaps(array $item): array
    {
        foreach (self::MAPS as $key) {
            if (is_array($item[$key] ?? null)) {
                $item[$key] = $this->asJsonMap($item[$key]);
            }
        }

        // A person read off the library carries maps of its own — notably
        // `ImageBlurHashes: {"Primary": {}}` — and has them refused the same way.
        if (is_array($item['People'] ?? null)) {
            $item['People'] = array_map(
                fn (mixed $person): mixed => is_array($person) ? $this->asJsonMaps($person) : $person,
                $item['People'],
            );
        }

        return $item;
    }

    /**
     * One map, and any map nested in it (as `ImageBlurHashes` nests one per
     * image kind), spelled as an object even when empty.
     *
     * @param  array<array-key, mixed>  $map
     * @return array<array-key, mixed>|stdClass
     */
    private function asJsonMap(array $map): array|stdClass
    {
        if ($map === []) {
            return new stdClass;
        }

        foreach ($map as $key => $value) {
            if (is_array($value)) {
                $map[$key] = $this->asJsonMap($value);
            }
        }

        return $map;
    }

    /**
     * A request pointed at the configured server and carrying the API key.
     * Jellyfin takes the key in an `Authorization` header of its own devising;
     * a plain `Bearer` of the same value is refused with a 401.
     */
    private function connection(): PendingRequest
    {
        return Http::baseUrl(self::apiBaseUrl())
            ->withHeader('Authorization', sprintf(
                'MediaBrowser Token="%s"',
                (string) config('services.jellyfin.api_key'),
            ));
    }

    /**
     * Where this app talks to the server: the cluster-internal address when
     * one is set, so API calls and downloads stay off the internet, otherwise
     * the public one.
     */
    private static function apiBaseUrl(): string
    {
        $internal = (string) config('services.jellyfin.internal_url');

        return rtrim(filled($internal) ? $internal : (string) config('services.jellyfin.url'), '/');
    }

    /**
     * A JSON request to the API, patient enough for a busy server.
     */
    protected function request(): PendingRequest
    {
        return $this->connection()
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
