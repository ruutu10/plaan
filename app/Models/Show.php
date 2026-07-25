<?php

namespace App\Models;

use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The show as a concept: what it is called and what it is about. A show is
 * staged one or more times, and every staging is a {@see Performance} with its
 * own date. The team owning the show owns its performances by implication.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read Collection<int, Performance> $performances
 */
#[Fillable([
    'team_id',
    'name',
    'description',
])]
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory;

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
     * The dated stagings of this show.
     *
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }
}
