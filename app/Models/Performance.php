<?php

namespace App\Models;

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

/**
 * One dated performance of a {@see Show}. Everything the performances of a show
 * have in common — the name, the description, the performing group — belongs to
 * the show; a performance holds only what can differ between them.
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
    use HasFactory, SoftDeletes;

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
        if ($user->can(self::EDIT_ALL_PERMISSION)) {
            return;
        }

        $teamIds = $user->teams()->pluck('teams.id');

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
     * Determine whether the user may manage this performance — see
     * {@see editableBy()}.
     */
    public function isEditableBy(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->editableBy($user)
            ->exists();
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration' => 'integer',
            'is_draft' => 'boolean',
        ];
    }
}
