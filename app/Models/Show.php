<?php

namespace App\Models;

use App\Concerns\ScopedByTeamAccess;
use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The show as a concept: what it is called and what it is about. A show is
 * played one or more times, and every {@see Performance} is one of those times,
 * with its own date. The team owning the show owns its performances by
 * implication.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team|null $team
 * @property-read Collection<int, Performance> $performances
 * @property-read int|null $performances_count
 */
#[Fillable([
    'team_id',
    'name',
    'description',
])]
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory, ScopedByTeamAccess, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // A show put aside takes its performances with it, so nothing is left
        // pointing at a show the rest of the app no longer sees. A hard delete
        // needs no help — the database cascades that one itself.
        static::deleting(function (Show $show): void {
            if (! $show->isForceDeleting()) {
                $show->performances()->delete();
            }
        });
    }

    /**
     * The permission — held by the "technician" role — that opens every show in
     * the house to its holder, not just the ones their own groups staged.
     */
    public const EDIT_ALL_PERMISSION = 'shows.edit_all';

    /**
     * Limit the query to the shows the given user may see and edit: the ones
     * owned by a team they belong to. Holders of {@see EDIT_ALL_PERMISSION} are
     * not limited at all — shows without an owning team included, as those are
     * reachable no other way.
     *
     * @param  Builder<Show>  $query
     */
    #[Scope]
    protected function editableBy(Builder $query, User $user): void
    {
        if (self::seesEverything($user)) {
            return;
        }

        $query->whereIn('team_id', $user->teamIds());
    }

    /**
     * The teams the given user may hand a show to: the ones they belong to, or
     * every group in the house for the holders of {@see EDIT_ALL_PERMISSION}.
     * A show is never moved somewhere its editor cannot follow it.
     *
     * @return Collection<int, Team>
     */
    public static function assignableTeams(User $user): Collection
    {
        $teams = $user->can(self::EDIT_ALL_PERMISSION)
            ? Team::query()
            : $user->teams();

        return $teams->orderByRaw('LOWER(teams.name)')->get();
    }

    /**
     * The performing group (team) whose show this is.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The dated performances of this show.
     *
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }
}
