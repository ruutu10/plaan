<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\TechnicalPlanStatus;
use Database\Factories\TechnicalPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property string $token
 * @property TechnicalPlanStatus $status
 * @property int|null $user_id
 * @property int|null $performance_id
 * @property array<string, mixed> $sound
 * @property array<int, array<string, mixed>> $scenes
 * @property array<string, mixed> $equipment
 * @property array<string, mixed> $extra
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Performance|null $performance
 */
#[Fillable([
    'status',
    'user_id',
    'performance_id',
    'sound',
    'scenes',
    'equipment',
    'extra',
    'submitted_at',
])]
class TechnicalPlan extends Model implements HasMedia
{
    use HasAttachments;

    /** @use HasFactory<TechnicalPlanFactory> */
    use HasFactory;

    /**
     * The contact who owns this plan.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The performance this plan describes the technical needs of.
     *
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TechnicalPlan $plan) {
            if (empty($plan->token)) {
                $plan->token = static::generateUniqueToken();
            }
        });
    }

    /**
     * Generate a unique, shareable plan token (e.g. "R10-8F3K9QX2LM4P").
     */
    public static function generateUniqueToken(): string
    {
        do {
            $token = 'R10-'.Str::upper(Str::random(14));
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * Build the nested payload consumed by the frontend wizard.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'token' => $this->token,
            'status' => $this->status->value,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'meta' => [
                'performanceId' => $this->performance_id,
                'performer' => $this->performance->team->name ?? '',
                'showName' => $this->performance->show_name ?? '',
                'showDate' => $this->performance->show_date->format('Y-m-d'),
                'duration' => $this->performance->duration,
                'description' => $this->performance->description ?? '',
                'contactEmail' => $this->user->email ?? '',
            ],
            'sound' => $this->sound,
            'scenes' => $this->scenes,
            'equipment' => $this->equipment,
            'extra' => [
                'notes' => $this->extra['notes'] ?? '',
                'files' => $this->attachmentsPayload(),
            ],
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TechnicalPlanStatus::class,
            'sound' => 'array',
            'scenes' => 'array',
            'equipment' => 'array',
            'extra' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
