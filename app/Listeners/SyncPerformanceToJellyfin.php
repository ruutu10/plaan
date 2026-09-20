<?php

namespace App\Listeners;

use App\Events\PerformanceRecordingLinked;
use App\Services\JellyfinClient;
use App\Services\JellyfinEpisodeMetadata;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push what this app knows about a night onto the episode that recorded it.
 *
 * One way and one episode: nothing is read back out of Jellyfin, and nothing
 * but the episode the link names is written to — see
 * {@see JellyfinClient::applyToItem()}, which refuses anything else.
 *
 * Unlike {@see CommentPlanConfirmedOnPlankaCard}, which swallows a failed write
 * because there is nothing to try again, a failure here is thrown on: the media
 * server being down or restarting is the ordinary reason a push fails, and the
 * only useful answer is to wait and go again. What went wrong is written to the
 * recording first, so a crew member can see it without reading the queue.
 *
 * The audit entries are filed here rather than in a listener of their own: they
 * record how the push went, which nothing outside this class knows. The house
 * pattern of a separate Log* listener exists to keep a controller-fired event's
 * trail out of the controller, and there is no controller in this path.
 */
class SyncPerformanceToJellyfin implements ShouldQueue
{
    /**
     * A performance put aside between the link being written and this running
     * has nothing left worth describing — that is not a failure to keep.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The client already retries twice in the moment, which covers a blip.
     * These are for a server that is down, restarting or mid-upgrade, and they
     * spread the attempts over about an hour and a quarter.
     */
    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        protected JellyfinClient $jellyfin,
        protected JellyfinEpisodeMetadata $metadata,
    ) {}

    public function handle(PerformanceRecordingLinked $event): void
    {
        $recording = $event->recording;

        if (! JellyfinClient::isConfigured()) {
            Log::info('No Jellyfin configured; a linked recording was not synced', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
            ]);

            return;
        }

        $itemId = $recording->item_id;

        // Belt and braces behind App\Rules\JellyfinItemUrl: a link that named
        // no item should never have been stored at all.
        if (blank($itemId)) {
            Log::warning('A recording link names no Jellyfin item', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
            ]);

            return;
        }

        $performance = $recording->performance;

        // A night put aside since the link was written has nothing left to say
        // about itself, and the episode is better left as it is than rewritten
        // from a record on its way out.
        if ($performance === null) {
            Log::info('A linked recording has no performance left to describe', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
            ]);

            return;
        }

        $performance->load(['format', 'team', 'staff']);

        $patch = $this->metadata->for($performance);

        try {
            $this->jellyfin->applyToItem($itemId, $patch);
        } catch (Throwable $e) {
            $recording->forceFill(['sync_error' => $e->getMessage()])->save();

            report($e);

            Log::error('Could not push performance metadata to Jellyfin', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
                'item_id' => $itemId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $recording->forceFill([
            'synced_at' => now(),
            'sync_error' => null,
        ])->save();

        Log::info('Pushed performance metadata to Jellyfin', [
            'recording_id' => $recording->id,
            'performance_id' => $recording->performance_id,
            'item_id' => $itemId,
            'fields' => array_keys($patch),
        ]);

        activity()
            ->performedOn($recording)
            ->causedBy($event->linkedBy)
            ->event('jellyfin_synced')
            ->withProperties([
                'item_id' => $itemId,
                'fields' => array_keys($patch),
            ])
            ->log(sprintf('Performance metadata pushed to Jellyfin by %s', $event->linkedBy->name));
    }

    /**
     * Every attempt is spent. Said out loud in the trail as well as the log: a
     * push nobody can see failing is one nobody fixes.
     */
    public function failed(PerformanceRecordingLinked $event, Throwable $e): void
    {
        $recording = $event->recording;

        Log::error('Gave up pushing performance metadata to Jellyfin', [
            'recording_id' => $recording->id,
            'performance_id' => $recording->performance_id,
            'item_id' => $recording->item_id,
            'exception' => $e->getMessage(),
        ]);

        activity()
            ->performedOn($recording)
            ->causedBy($event->linkedBy)
            ->event('jellyfin_sync_failed')
            ->withProperties([
                'item_id' => $recording->item_id,
                'error' => $e->getMessage(),
            ])
            ->log(sprintf(
                'Performance metadata could not be pushed to Jellyfin for %s',
                $recording->performance?->displayName() ?? 'a performance since put aside',
            ));
    }
}
