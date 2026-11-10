<?php

namespace App\Services;

use App\Data\RecordingFile;
use App\Models\PerformanceRecording;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The file details of a recording, read from Jellyfin for showing beside the
 * download button.
 *
 * Only ever a nicety: a library that is slow or down leaves the details out
 * rather than holding up a page or a letter. Answers are cached so a page
 * load does not cost a call to the library, and a failure is cached briefly so
 * a library that is down is not asked again on every load.
 */
class RecordingFileDetails
{
    private const CACHE_SECONDS = 10 * 60;

    private const FAILURE_CACHE_SECONDS = 60;

    public function __construct(private JellyfinClient $jellyfin) {}

    public function for(PerformanceRecording $recording): ?RecordingFile
    {
        if ($recording->item_id === null || ! JellyfinClient::isConfigured()) {
            return null;
        }

        $key = 'jellyfin-file:'.$recording->item_id;
        $cached = Cache::get($key);

        if (is_array($cached)) {
            if ($cached === []) {
                return null;
            }

            /** @var array{name: string, sizeBytes: int, addedAt: string|null} $cached written by this class from RecordingFile::toArray() */
            return RecordingFile::fromArray($cached);
        }

        try {
            $file = $this->jellyfin->file($recording->item_id);
        } catch (HttpClientException|RuntimeException $exception) {
            Log::warning('Could not read a recording\'s file details from Jellyfin', [
                'recording_id' => $recording->id,
                'item_id' => $recording->item_id,
                'error' => $exception->getMessage(),
            ]);

            Cache::put($key, [], self::FAILURE_CACHE_SECONDS);

            return null;
        }

        Cache::put($key, $file?->toArray() ?? [], $file === null ? self::FAILURE_CACHE_SECONDS : self::CACHE_SECONDS);

        return $file;
    }
}
