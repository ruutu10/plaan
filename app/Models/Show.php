<?php

namespace App\Models;

use App\Concerns\HasClaudeReasoningLog;
use App\Concerns\ScopedByTeamAccess;
use App\Enums\CreatedBy;
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
 * @property CreatedBy $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team|null $team
 * @property-read Collection<int, Performance> $performances
 * @property-read int|null $performances_count
 * @property-read Collection<int, ClaudeReasoningLog> $reasoningLogs
 */
#[Fillable([
    'team_id',
    'name',
    'description',
    'created_by',
])]
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasClaudeReasoningLog, HasFactory, ScopedByTeamAccess, SoftDeletes;

    /**
     * A show nobody said otherwise about was entered by hand: only the Planka
     * import says where else it came from. Spelt out here as well as in the
     * column default so a show just created reads as manual rather than as an
     * attribute that has not come back from the database yet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'created_by' => CreatedBy::Manual->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_by' => CreatedBy::class,
        ];
    }

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
     * The name of the stand-in show, under which the plans for nights that are
     * not on the books are filed. Every plan names the performance it is for,
     * so a performer whose evening nobody has registered yet still needs one to
     * name; the crew move the plan onto the real performance once it exists.
     *
     * It belongs to no group, which is what keeps its plans to whoever wrote
     * them — see {@see TechnicalPlan::visibleTo()}.
     */
    public const PLACEHOLDER_NAME = 'Etendust pole nimekirjas';

    /**
     * The stand-in show itself, registered the first time it is asked for.
     * One brought back rather than a second one created: two shows under this
     * name would split the plans that belong to no performance between them.
     */
    public static function placeholder(): self
    {
        $show = static::withTrashed()->firstOrCreate(
            ['name' => self::PLACEHOLDER_NAME, 'team_id' => null],
            ['description' => 'Kohatäide plaanidele, mille etendust pole veel registreeritud. Tehnik tõstab plaani õige etenduse alla, kui see on kirjas.'],
        );

        if ($show->trashed()) {
            $show->restore();
        }

        return $show;
    }

    /**
     * Whether this is the stand-in show — see {@see PLACEHOLDER_NAME}.
     */
    public function isPlaceholder(): bool
    {
        return $this->team_id === null && $this->name === self::PLACEHOLDER_NAME;
    }

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
     * Limit the query to the shows the given user may open: the ones their
     * groups own, plus the ones their groups merely play a performance of.
     *
     * The two are deliberately not the same right. A guest troupe with a slot
     * on somebody else's evening has to be able to reach that evening to
     * correct its own performance, but the show is not theirs to rename, hand
     * over or put aside — that stays with {@see editableBy()}.
     *
     * @param  Builder<Show>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (self::seesEverything($user)) {
            return;
        }

        $teamIds = $user->teamIds();

        $query->where(fn (Builder $show) => $show
            ->whereIn('shows.team_id', $teamIds)
            ->orWhereHas('performances', fn (Builder $performance) => $performance->whereIn('performances.team_id', $teamIds)));
    }

    /**
     * Whether the user may open this show — see {@see visibleTo()}.
     */
    public function isVisibleTo(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->visibleTo($user)
            ->exists();
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
