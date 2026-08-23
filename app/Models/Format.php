<?php

namespace App\Models;

use App\Concerns\HasClaudeReasoningLog;
use App\Concerns\LogsModelActivity;
use App\Concerns\ScopedByTeamAccess;
use App\Enums\CreatedBy;
use Database\Factories\FormatFactory;
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
 * The format as a concept: what it is called and what it is about. A format is
 * played one or more times, and every {@see Performance} is one of those times,
 * with its own date. The team owning the format owns its performances by
 * implication.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string|null $description
 * @property bool $technical_plan_mandatory
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
    'technical_plan_mandatory',
    'created_by',
])]
class Format extends Model
{
    /** @use HasFactory<FormatFactory> */
    use HasClaudeReasoningLog, HasFactory, LogsModelActivity, ScopedByTeamAccess, SoftDeletes;

    /**
     * A format nobody said otherwise about was entered by hand: only the Planka
     * import says where else it came from. The same goes for the technical plan
     * being expected, which holds for all but the handful of nights that run
     * themselves. Both are spelt out here as well as in the column defaults, so
     * a format just created reads right rather than as an attribute that has not
     * come back from the database yet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'created_by' => CreatedBy::Manual->value,
        'technical_plan_mandatory' => true,
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
            'technical_plan_mandatory' => 'boolean',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // A format put aside takes its performances with it, so nothing is left
        // pointing at a format the rest of the app no longer sees. A hard delete
        // needs no help — the database cascades that one itself.
        static::deleting(function (Format $format): void {
            if (! $format->isForceDeleting()) {
                $format->performances()->delete();
            }
        });
    }

    /**
     * The permission — held by the "technician" role — that opens every format in
     * the house to its holder, not just the ones their own groups staged.
     */
    public const EDIT_ALL_PERMISSION = 'formats.edit_all';

    /**
     * The name of the stand-in format, under which the plans for nights that are
     * not on the books are filed. Every plan names the performance it is for,
     * so a performer whose evening nobody has registered yet still needs one to
     * name; the crew move the plan onto the real performance once it exists.
     *
     * It belongs to no group, which is what keeps its plans to whoever wrote
     * them — see {@see TechnicalPlan::visibleTo()}.
     */
    public const PLACEHOLDER_NAME = 'Etendust pole nimekirjas';

    /**
     * The stand-in format itself, registered the first time it is asked for.
     * One brought back rather than a second one created: two formats under this
     * name would split the plans that belong to no performance between them.
     */
    public static function placeholder(): self
    {
        $format = static::withTrashed()->firstOrCreate(
            ['name' => self::PLACEHOLDER_NAME, 'team_id' => null],
            ['description' => 'Kohatäide plaanidele, mille etendust pole veel registreeritud. Tehnik tõstab plaani õige etenduse alla, kui see on kirjas.'],
        );

        if ($format->trashed()) {
            $format->restore();
        }

        return $format;
    }

    /**
     * Whether this is the stand-in format — see {@see PLACEHOLDER_NAME}.
     */
    public function isPlaceholder(): bool
    {
        return $this->team_id === null && $this->name === self::PLACEHOLDER_NAME;
    }

    /**
     * The name a format is matched by: its own, without regard to case or the
     * spaces around it, so "JadaJada" and "Jadajada" stay one format.
     *
     * Folded in PHP rather than in SQL because the names are Estonian:
     * SQLite's `LOWER()` leaves "Ä" alone, so "MÄRTU10" and "Märtu10" would
     * read as two different formats. PHP folds them the same.
     */
    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * The names of the formats the house has, by {@see nameKey()}. Formats put
     * aside are left out: a name the house no longer plays is not one a card
     * should be read onto, and the Planka import refuses such a night anyway.
     *
     * @return array<string, string>
     */
    public static function namesByKey(): array
    {
        return static::query()
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [self::nameKey($name) => $name])
            ->all();
    }

    /**
     * Limit the query to the formats the given user may see and edit: the ones
     * owned by a team they belong to. Holders of {@see EDIT_ALL_PERMISSION} are
     * not limited at all — formats without an owning team included, as those are
     * reachable no other way.
     *
     * @param  Builder<Format>  $query
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
     * Limit the query to the formats the given user may open: the ones their
     * groups own, plus the ones their groups merely play a performance of.
     *
     * The two are deliberately not the same right. A guest troupe with a slot
     * on somebody else's evening has to be able to reach that evening to
     * correct its own performance, but the format is not theirs to rename, hand
     * over or put aside — that stays with {@see editableBy()}.
     *
     * @param  Builder<Format>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (self::seesEverything($user)) {
            return;
        }

        $teamIds = $user->teamIds();

        $query->where(fn (Builder $format) => $format
            ->whereIn('formats.team_id', $teamIds)
            ->orWhereHas('performances', fn (Builder $performance) => $performance->whereIn('performances.team_id', $teamIds)));
    }

    /**
     * Whether the user may open this format — see {@see visibleTo()}.
     */
    public function isVisibleTo(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->visibleTo($user)
            ->exists();
    }

    /**
     * The teams the given user may hand a format to: the ones they belong to, or
     * every group in the house for the holders of {@see EDIT_ALL_PERMISSION}.
     * A format is never moved somewhere its editor cannot follow it.
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
     * The performing group (team) whose format this is.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The dated performances of this format.
     *
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }

    /**
     * The properties worth an audit trail.
     *
     * @return array<int, string>
     */
    protected function activityLogAttributes(): array
    {
        return ['team_id', 'name', 'description', 'technical_plan_mandatory', 'created_by'];
    }
}
