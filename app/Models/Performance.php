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
 * One dated staging of a {@see Show}. Everything the stagings have in common —
 * the name, the description, the performing group — belongs to the show; a
 * performance holds only what can differ between them.
 *
 * @property int $id
 * @property int $show_id
 * @property Carbon $date
 * @property int|null $duration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Show $show
 * @property-read Collection<int, TechnicalPlan> $technicalPlans
 */
#[Fillable([
    'show_id',
    'date',
    'duration',
])]
class Performance extends Model
{
    /** @use HasFactory<PerformanceFactory> */
    use HasFactory;

    /**
     * The show this is a staging of.
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
        ];
    }
}
