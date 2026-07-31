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
 * have in common — the name, the description, the performing group — belongs to
 * the show; a performance holds only what can differ between them.
 *
 * `date` is the full moment the performance starts, stored in UTC like every
 * other timestamp here. The house does not think in UTC, though, so the hour is
 * only ever written and read through {@see venueTimezone()} — see
 * {@see startsAt()} and {@see momentFrom()}, which are the two ends of that
 * conversion and the only places it should happen.
 *
 * @property int $id
 * @property int $show_id
 * @property Carbon $date
 * @property int|null $duration
 * @property bool $is_draft
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Show $show
 * @property-read Collection<int, TechnicalPlan> $technicalPlans
 * @property-read int|null $technical_plans_count
 * @property-read Collection<int, PerformanceReminder> $reminders
 * @property-read int|null $reminders_count
 */
#[Fillable([
    'show_id',
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

        // A performance is the group's through the show it belongs to.
        $query->whereHas('show', fn (Builder $show) => $show->whereIn('team_id', $teamIds));
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
     * The show this is a performance of.
     *
     * @return BelongsTo<Show, $this>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
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
}
