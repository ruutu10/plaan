<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\TechnicalPlanStatus;
use App\Rules\AllowedAttachment;
use Database\Factories\TechnicalPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
     * The media collection holding the scenes' sound files. A scene keeps only
     * the file's handle in its JSON; the file itself lives here.
     */
    public const SOUND_COLLECTION = 'sound';

    protected string $attachmentsCollection = 'technical-plan';

    /**
     * A plan keeps its scenes' sound files apart from its own attachments.
     *
     * @return array<int, string>
     */
    protected function extraAttachmentCollections(): array
    {
        return [self::SOUND_COLLECTION];
    }

    /**
     * The sound files the scenes refer to, keyed by their handle.
     *
     * @return Collection<string, Media>
     */
    public function sceneSoundFiles(): Collection
    {
        return $this->attachments(self::SOUND_COLLECTION)->keyBy('uuid');
    }

    /**
     * The scenes' submitted sound-file handles, less any that do not point at
     * a file the sound collection accepts.
     *
     * The upload endpoint already holds an upload destined for this collection
     * to the audio allowlist, but that destination is the client's word — a
     * file uploaded as a plain attachment could otherwise be passed off as a
     * scene's sound, so the stored file is checked here as well.
     *
     * @return array<int, array{id?: string, name?: string, size?: int}>
     */
    private function submittedSoundFileHandles(): array
    {
        $handles = array_values(array_filter(array_map(
            fn (array $scene): ?array => $scene['soundFile'] ?? null,
            $this->scenes,
        )));

        $allowed = AllowedAttachment::extensionsFor(self::SOUND_COLLECTION);

        $audible = Media::query()
            ->whereIn('uuid', array_filter(array_column($handles, 'id')))
            ->get()
            ->filter(fn (Media $media): bool => in_array(strtolower($media->extension), $allowed, true))
            ->keyBy('uuid');

        return array_values(array_filter(
            $handles,
            fn (array $handle): bool => $audible->has($handle['id'] ?? ''),
        ));
    }

    /**
     * Reconcile the scenes' sound files with the handles the client submitted:
     * move each newly staged upload into the plan's sound collection, drop the
     * files no scene refers to any more, and rewrite every scene's handle to
     * the file as it is now stored (moving a staged upload re-keys it).
     *
     * A scene holds at most one sound file, and never a file and a link at the
     * same time — the request rules enforce both.
     */
    public function syncSceneSoundFiles(): void
    {
        $handles = $this->submittedSoundFileHandles();

        $moved = $this->syncAttachments($handles, self::SOUND_COLLECTION);

        $stored = $this->sceneSoundFiles();

        $this->scenes = array_map(function (array $scene) use ($moved, $stored): array {
            $handle = $scene['soundFile']['id'] ?? null;
            $media = $handle ? $stored->get($moved[$handle] ?? '') : null;

            // A handle that resolved to nothing (unknown or already gone)
            // leaves the scene without a sound file.
            $scene['soundFile'] = $media ? [
                'id' => (string) $media->uuid,
                'name' => $media->file_name,
                'size' => (int) $media->size,
            ] : null;

            return $scene;
        }, $this->scenes);

        $this->save();
    }

    /**
     * Limit the query to the plans the given user may open as the basis for a
     * new plan: their own plans, whatever state those are in, plus the plans
     * their teams' performances have already been handed in with. A team-mate's
     * unfinished draft stays theirs alone.
     *
     * @param  Builder<TechnicalPlan>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $teamIds = $user->teams()->pluck('teams.id');

        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhere(fn (Builder $query) => $query
                ->whereIn('status', [TechnicalPlanStatus::Submitted, TechnicalPlanStatus::Received])
                ->whereHas('performance', fn (Builder $performance) => $performance->whereIn('team_id', $teamIds))));
    }

    /**
     * Determine whether the user may open this plan — see {@see visibleTo()}.
     */
    public function isVisibleTo(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->visibleTo($user)
            ->exists();
    }

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
        $year = date('Y');
        do {
            $token = 'R10-'.$year.'-'.Str::upper(Str::random(12));
        } while (static::query()->where('token', $token)->exists());

        return $token;
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
