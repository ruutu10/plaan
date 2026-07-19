<?php

namespace App\Models;

use Database\Factories\PerformanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $team_id
 * @property string $show_name
 * @property Carbon $show_date
 * @property int|null $duration
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read Collection<int, TechnicalPlan> $technicalPlans
 */
#[Fillable([
    'team_id',
    'show_name',
    'show_date',
    'duration',
    'description',
])]
class Performance extends Model
{
    /** @use HasFactory<PerformanceFactory> */
    use HasFactory;

    /**
     * The performing group (team) putting on this performance.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
            'show_date' => 'date',
            'duration' => 'integer',
        ];
    }
}
