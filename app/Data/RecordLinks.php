<?php

namespace App\Data;

use App\Http\Controllers\DashboardController;
use App\Models\Format;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The pages behind the names a listing shows: the format's own screen and the
 * performance's. A name is only handed a link when the reader may actually open
 * what it points at — one that would land on a refusal is left as plain text
 * rather than offered and then taken away.
 *
 * Who may open which is settled once for the whole page rather than a row at a
 * time, for the same reason as {@see DashboardController::visiblePlanIds()}: two
 * queries, not two per row.
 */
final class RecordLinks
{
    /**
     * @param  Collection<int, int>  $openableFormatIds
     * @param  Collection<int, int>  $openablePerformanceIds
     */
    private function __construct(
        private Collection $openableFormatIds,
        private Collection $openablePerformanceIds,
    ) {}

    /**
     * Nothing is linked: for a reader nobody worked the reach out for, so a
     * resource used outside the screens that do still renders.
     */
    public static function none(): self
    {
        return new self(new Collection, new Collection);
    }

    /**
     * Which of the given performances the user may open, and which of the
     * formats behind them.
     *
     * @param  iterable<int, Performance|null>  $performances  nulls are ignored, so a plan without a night may be passed straight in
     */
    public static function for(?User $user, iterable $performances): self
    {
        $performances = Collection::make($performances)->filter();

        if ($user === null || $performances->isEmpty()) {
            return self::none();
        }

        return new self(
            Format::query()
                ->whereKey($performances->pluck('format_id')->unique()->all())
                ->visibleTo($user)
                ->pluck('id'),
            Performance::query()
                ->whereKey($performances->map->getKey()->all())
                ->editableBy($user)
                ->pluck('id'),
        );
    }

    /**
     * The format's own screen, or null when the reader may not open it.
     */
    public function formatUrl(?Performance $performance): ?string
    {
        if (! $this->isRealNight($performance) || ! $this->openableFormatIds->contains($performance->format_id)) {
            return null;
        }

        return route('formats.edit', $performance->format_id);
    }

    /**
     * The performance's own screen, reached through the format it belongs to —
     * a performance is corrected there, not on a page of its own.
     */
    public function performanceUrl(?Performance $performance): ?string
    {
        if (! $this->isRealNight($performance) || ! $this->openablePerformanceIds->contains($performance->getKey())) {
            return null;
        }

        return route('formats.performances.show', [$performance->format_id, $performance->getKey()]);
    }

    /**
     * Whether there is a night here worth linking to at all. The stand-in
     * performance is where the plans without a night of their own are filed,
     * not an evening anybody plays, so its screens are not what a reader
     * clicking its name is after — see {@see Performance::placeholder()}.
     *
     * @phpstan-assert-if-true !null $performance
     */
    private function isRealNight(?Performance $performance): bool
    {
        return $performance !== null && ! $performance->isPlaceholder();
    }
}
