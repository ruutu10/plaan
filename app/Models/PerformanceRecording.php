<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use App\Listeners\SummarisePerformancePlot;
use App\Listeners\SyncPerformanceToJellyfin;
use App\Services\JellyfinClient;
use Carbon\CarbonImmutable;
use Database\Factories\PerformanceRecordingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The video of a played night, as it sits in the house's Jellyfin library.
 *
 * A crew member pastes the episode's address off Jellyfin's own interface and
 * this holds it, along with the item it names: `item_id` is the episode's GUID,
 * which is what the API is written against, and it is derived here from the URL
 * rather than typed, so the two can never drift apart. See
 * {@see JellyfinClient::itemIdFromUrl()} for the addresses that are understood.
 *
 * What travels the other way — this app's account of the night pushed onto that
 * episode — is {@see SyncPerformanceToJellyfin}'s job, and
 * `synced_at` and `sync_error` are its record of how it went. Nothing is ever
 * read back out of Jellyfin.
 *
 * A corrected link is pushed again but announced only once: `announced_at` says
 * the performers have been told a video of this night exists, and that goes on
 * being true however many times the address is fixed. It is why clearing a link
 * only puts the row aside — a cleared and re-entered link finds the same row,
 * and the same answer to whether anybody has been written to.
 *
 * The plot summary is read off this video's own subtitles, once — see
 * {@see SummarisePerformancePlot} and docs/adr/0001. `linked_at` is when the
 * current link was written, which the wait for subtitles is counted from.
 *
 * @property int $id
 * @property int $performance_id
 * @property string $url
 * @property string|null $item_id
 * @property Carbon|null $synced_at
 * @property string|null $sync_error
 * @property Carbon|null $announced_at
 * @property CarbonImmutable|null $linked_at
 * @property string|null $plot_summary
 * @property list<string>|null $plot_tags
 * @property CarbonImmutable|null $plot_summary_generated_at
 * @property CarbonImmutable|null $plot_summary_abandoned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Performance|null $performance
 */
#[Fillable([
    'performance_id',
    'url',
])]
class PerformanceRecording extends Model
{
    /** @use HasFactory<PerformanceRecordingFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    /**
     * The performance this is the recording of.
     *
     * Reads as null once that night has been put aside — a recording is
     * cascaded away with a performance wiped outright, but a soft-deleted one
     * leaves it pointing at a night the rest of the app no longer sees, which
     * is why every reader here asks for it safely.
     *
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * Whether this link has been pushed to Jellyfin since it was last written.
     */
    public function isSynced(): bool
    {
        return $this->synced_at !== null;
    }

    /**
     * How the last push went, for a screen wanting to say so in a word:
     * "pending" while nothing has been written yet and nothing has gone wrong,
     * "failed" when the last attempt did, "synced" when it got through.
     */
    public function syncState(): string
    {
        return match (true) {
            $this->synced_at !== null => 'synced',
            filled($this->sync_error) => 'failed',
            default => 'pending',
        };
    }

    /**
     * Whether a plot summary is still to be written for this video: none has
     * been, by the model or by hand, and nobody has given up waiting for one.
     */
    public function awaitsPlotSummary(): bool
    {
        return $this->plot_summary === null
            && $this->plot_summary_generated_at === null
            && $this->plot_summary_abandoned_at === null;
    }

    /**
     * Start waiting for this video's subtitles again, from now — what a crew
     * member asking for a fresh summary means. The summary already there is
     * left standing until a new one replaces it.
     */
    public function restartPlotSummaryWait(): void
    {
        $this->forceFill([
            'plot_summary_generated_at' => null,
            'plot_summary_abandoned_at' => null,
            'linked_at' => now(),
        ])->save();
    }

    /**
     * Recordings still waiting on a plot summary, on a night still kept.
     *
     * @param  Builder<PerformanceRecording>  $query
     */
    #[Scope]
    protected function awaitingPlotSummary(Builder $query): void
    {
        $query->whereNotNull('item_id')
            ->whereNull('plot_summary')
            ->whereNull('plot_summary_generated_at')
            ->whereNull('plot_summary_abandoned_at')
            ->whereHas('performance');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'announced_at' => 'datetime',
            'linked_at' => 'datetime',
            'plot_tags' => 'array',
            'plot_summary_generated_at' => 'datetime',
            'plot_summary_abandoned_at' => 'datetime',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saving(function (PerformanceRecording $recording): void {
            if (! $recording->isDirty('url')) {
                return;
            }

            // The item is the link's to name, whoever wrote the link and by
            // whatever route, so it is worked out here rather than anywhere a
            // URL happens to be set.
            $recording->item_id = JellyfinClient::itemIdFromUrl($recording->url);

            $recording->linked_at = now();

            // A link *changed* is a push not yet made: the old timestamp would
            // say this video's metadata is level when nothing has been written
            // for it, and the old error would blame it for a failure that was
            // the previous link's. A link written for the first time has
            // neither to undo, and saying so here rather than blanking them
            // anyway is what lets a row be created with a state already in it.
            if ($recording->exists) {
                $recording->synced_at = null;
                $recording->sync_error = null;

                // Another link is another video, with subtitles — and so a
                // plot — of its own: what was read off the last one is not
                // this one's, and its wait starts over.
                $recording->plot_summary = null;
                $recording->plot_tags = null;
                $recording->plot_summary_generated_at = null;
                $recording->plot_summary_abandoned_at = null;
            }

            // `announced_at` is deliberately left alone — see the class
            // docblock.
        });
    }

    /**
     * The properties worth an audit trail. The rest is the push's own
     * bookkeeping, written by a queued job with nobody signed in, and every
     * retry of a failing push would file another entry nobody can act on.
     *
     * @return array<int, string>
     */
    protected function activityLogAttributes(): array
    {
        return ['url'];
    }
}
