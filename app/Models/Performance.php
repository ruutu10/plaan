<?php

namespace App\Models;

use App\Concerns\ScopedByTeamAccess;
use App\Enums\ReminderSchedule;
use Carbon\CarbonInterface;
use Database\Factories\PerformanceFactory;
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
use Illuminate\Support\Facades\Date;

/**
 * One dated performance of a {@see Show}. Everything the performances of a show
 * have in common — the name, the description — belongs to the show; a
 * performance holds only what can differ between them.
 *
 * Who plays it is one of those things. A show one troupe fills has its group on
 * the show, and its performances inherit it; an evening several groups share —
 * an Õppelava, a gala — is one show played once, with a performance per act,
 * each naming its own group and carrying the act's name off the board. {@see
 * performerName()} is the one place that distinction is resolved.
 *
 * `date` is the full moment the performance starts, stored in UTC like every
 * other timestamp here. The house does not think in UTC, though, so the hour is
 * only ever written and read through {@see venueTimezone()} — see
 * {@see startsAt()} and {@see momentFrom()}, which are the two ends of that
 * conversion and the only places it should happen.
 *
 * @property int $id
 * @property int $show_id
 * @property int|null $team_id
 * @property string|null $title
 * @property Carbon $date
 * @property int|null $duration
 * @property bool $is_draft
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Show $show
 * @property-read Team|null $team
 * @property-read Collection<int, TechnicalPlan> $technicalPlans
 * @property-read int|null $technical_plans_count
 * @property-read Collection<int, PerformanceReminder> $reminders
 * @property-read int|null $reminders_count
 */
#[Fillable([
    'show_id',
    'team_id',
    'title',
    'date',
    'duration',
    'is_draft',
])]
class Performance extends Model
{
    /** @use HasFactory<PerformanceFactory> */
    use HasFactory, ScopedByTeamAccess, SoftDeletes;

    /**
     * The permission — held by the "technician" role — that opens the performances
     * of every show in the house, not just those of the holder's own groups.
     */
    public const EDIT_ALL_PERMISSION = 'performances.edit_all';

    /**
     * A performance nobody said anything about is one the house stands behind:
     * only the Planka import asks for a draft. Spelt out here as well as in the
     * column default so a performance just created reads as false rather than as
     * an attribute that has not come back from the database yet.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_draft' => false,
    ];

    /**
     * Limit the query to the performances the given user may manage: those of the
     * shows their groups own. Holders of {@see EDIT_ALL_PERMISSION} are not
     * limited at all.
     *
     * @param  Builder<Performance>  $query
     */
    #[Scope]
    protected function editableBy(Builder $query, User $user): void
    {
        if (self::seesEverything($user)) {
            return;
        }

        $teamIds = $user->teamIds();

        // A performance is the group's through the show it belongs to, or by
        // being the group's own slot on an evening somebody else stages. The
        // second does not carry over to the show: a guest may correct their own
        // performance without touching the show it sits in.
        $query->where(fn (Builder $performance) => $performance
            ->whereHas('show', fn (Builder $show) => $show->whereIn('team_id', $teamIds))
            ->orWhereIn('performances.team_id', $teamIds));
    }

    /**
     * Limit the query to the performances the house has vouched for. A draft is
     * one the Planka import registered and nobody has reviewed yet: its date may
     * be wrong or the night may not be happening at all, so it is kept out of
     * every listing a plan is written from until an admin clears it.
     *
     * @param  Builder<Performance>  $query
     */
    #[Scope]
    protected function vouchedFor(Builder $query): void
    {
        $query->where('is_draft', false);
    }

    /**
     * Determine whether the user may manage the performances of the given show at
     * all — the question {@see editableBy()} asks, for a show that has none yet.
     */
    public static function manageableFor(User $user, Show $show): bool
    {
        if ($user->can(self::EDIT_ALL_PERMISSION)) {
            return true;
        }

        return $show->team_id !== null
            && $user->teams()->where('teams.id', $show->team_id)->exists();
    }

    /**
     * The groups a performance may be handed to: the ones the user belongs to,
     * or every group in the house for the holders of {@see EDIT_ALL_PERMISSION}.
     * Mirrors {@see Show::assignableTeams()}, on the performances' own right.
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
     * The show this is a performance of.
     *
     * @return BelongsTo<Show, $this>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /**
     * The group playing this performance, when it is not simply the show's own.
     * Set for the acts of an evening several groups share; empty otherwise.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Who is playing this performance: its own group, or the show's when it has
     * none of its own. Every screen, mail and listing asks the question here
     * rather than reaching for `show->team`, so an act on a shared evening is
     * never announced under the name of whoever happens to own the show.
     */
    public function performerName(): ?string
    {
        return $this->performedBy()?->name;
    }

    /**
     * The same group as an id — see {@see performerName()}.
     */
    public function performingTeamId(): ?int
    {
        return $this->team_id ?? $this->show->team_id;
    }

    /**
     * The group playing this performance as a model — its own, or the show's.
     * The one to chase about a missing plan, and the one whose members may read
     * the plans written for it.
     */
    public function performedBy(): ?Team
    {
        return $this->team ?? $this->show->team;
    }

    /**
     * The technical plans prepared for this performance.
     *
     * @return HasMany<TechnicalPlan, $this>
     */
    public function technicalPlans(): HasMany
    {
        return $this->hasMany(TechnicalPlan::class);
    }

    /**
     * The {@see ReminderSchedule} moments of this performance that have already
     * been dealt with.
     *
     * @return HasMany<PerformanceReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(PerformanceReminder::class);
    }

    /**
     * The wall clock the house runs on. Stored moments mean nothing to a
     * performer until they are read through this.
     */
    public static function venueTimezone(): string
    {
        return (string) config('performance.timezone', 'Europe/Tallinn');
    }

    /**
     * The moment this performance starts, on the venue's clock. This is the
     * form every screen and every mail shows; the stored UTC is an
     * implementation detail nothing outside the model should have to know.
     */
    public function startsAt(): CarbonInterface
    {
        return $this->date->setTimezone(self::venueTimezone());
    }

    /**
     * The curtain-up as the house says it: "19:00".
     */
    public function startTime(): string
    {
        return $this->startsAt()->format('H:i');
    }

    /**
     * The venue-local date the performance falls on: "2026-09-01". Not the same
     * as the stored date once a late night crosses midnight in UTC, which is
     * why this goes through {@see startsAt()} rather than reading `date`.
     */
    public function startDate(): string
    {
        return $this->startsAt()->toDateString();
    }

    /**
     * Turn a venue-local date and time — as a person typed them, or as the
     * Planka import read them off a card — into the UTC moment to store. A
     * missing time falls back to the venue's usual curtain-up rather than to
     * midnight, which would read as a real start time everywhere it was shown.
     */
    public static function momentFrom(string $date, ?string $startTime = null): CarbonInterface
    {
        $time = blank($startTime)
            ? (string) config('performance.default_start_time', '19:00')
            : $startTime;

        return Date::parse($date, self::venueTimezone())
            ->setTimeFromTimeString($time)
            ->utc();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'duration' => 'integer',
            'is_draft' => 'boolean',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // An act carrying no name of its own is one the show's name already
        // names, so an empty string is stored as an absence rather than as a
        // title nobody can see.
        static::saving(function (Performance $performance): void {
            if ($performance->title !== null && trim($performance->title) === '') {
                $performance->title = null;
            }
        });
    }
}
