<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Concerns\LogsModelActivity;
use App\Enums\TechnicalPlanStatus;
use App\Listeners\LogTechnicalPlanStatusChanged;
use App\Listeners\LogTechnicalPlanSubmitted;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $token
 * @property TechnicalPlanStatus $status
 * @property int|null $user_id
 * @property int $performance_id
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
    use HasAttachments, LogsModelActivity;

    /** @use HasFactory<TechnicalPlanFactory> */
    use HasFactory;

    /**
     * Only a plan's creation is worth an automatic entry — the wizard saves a
     * draft on every step, and logging each of those would drown out the
     * events actually worth reading. Submitting a plan and moving it through
     * its statuses are significant enough to log in their own right instead;
     * see {@see LogTechnicalPlanSubmitted} and
     * {@see LogTechnicalPlanStatusChanged}.
     *
     * @var array<int, string>
     */
    protected static array $doNotRecordEvents = ['updated'];

    /**
     * The media collection holding the scenes' sound files. A scene keeps only
     * the file's handle in its JSON; the file itself lives here.
     */
    public const SOUND_COLLECTION = 'sound';

    /**
     * How near a performance has to be before its plan is expected at all.
     * Further out than this nothing is owed yet — the evening is still being
     * put together — so the dashboard leaves those nights out of its count of
     * missing plans rather than holding people to a plan nobody asked for.
     */
    public const EXPECTED_WITHIN_DAYS = 7;

    /**
     * The permission — held by the "technician" role — that opens every plan in
     * the house to its holder, not just the ones they wrote or their teams sent.
     */
    public const VIEW_ALL_PERMISSION = 'technical_plans.view_all';

    /**
     * The permission — held by the "technician" role — that lets its holder
     * change any plan's status from the admin overview, not just the ones they
     * wrote or their teams sent.
     */
    public const EDIT_ALL_PERMISSION = 'technical_plans.edit_all';

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

        $lost = 0;

        $this->scenes = array_map(function (array $scene) use ($moved, $stored, &$lost): array {
            $handle = $scene['soundFile']['id'] ?? null;
            $media = $handle ? $stored->get($moved[$handle] ?? '') : null;

            // A handle that resolved to nothing (unknown or already gone)
            // leaves the scene without a sound file.
            if ($handle && ! $media) {
                $lost++;
            }

            $scene['soundFile'] = $media ? [
                'id' => (string) $media->uuid,
                'name' => $media->file_name,
                'size' => (int) $media->size,
            ] : null;

            return $scene;
        }, $this->scenes);

        // Sound cues going missing between the wizard and the plan is the kind
        // of thing that is only discovered at the desk on the night.
        if ($lost > 0) {
            Log::warning('Scenes lost their sound file while saving a plan', [
                'plan_id' => $this->id,
                'scenes_affected' => $lost,
                'scenes' => count($this->scenes),
            ]);
        }

        Log::debug('Reconciled scene sound files', [
            'plan_id' => $this->id,
            'submitted' => count($handles),
            'stored' => $stored->count(),
        ]);

        $this->save();
    }

    /**
     * Limit the query to the plans the given user may open as the basis for a
     * new plan: their own plans, whatever state those are in, plus the plans
     * their teams' performances have already been handed in with — archived
     * ones included, since a played format's plan is the one worth copying for
     * its next run. A team-mate's unfinished draft stays theirs alone.
     *
     * @param  Builder<TechnicalPlan>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $teamIds = $user->teamIds();

        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhere(fn (Builder $query) => $query
                ->whereIn('status', TechnicalPlanStatus::reusable())
                // A performance is the team's through the format it stages, or by
                // being the team's own act on an evening somebody else stages —
                // an Õppelava slot's plan belongs to whoever plays the slot.
                ->whereHas('performance', fn (Builder $performance) => $performance
                    ->whereIn('performances.team_id', $teamIds)
                    ->orWhereHas('format', fn (Builder $format) => $format->whereIn('team_id', $teamIds)))));
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
     * Limit the query to the plans this user may write to on the strength of
     * who they are: their own, and those of a performance — or its format —
     * staged by one of their teams.
     *
     * Unlike {@see visibleTo()}, a team-mate's unfinished draft counts. That
     * one governs opening a plan as the basis for a *new* plan, where somebody
     * else's half-written draft is not a starting point worth offering; this
     * one is about fixing the plan in front of you, which is exactly what an
     * unfinished draft is for.
     *
     * @param  Builder<TechnicalPlan>  $query
     */
    #[Scope]
    protected function editableBy(Builder $query, User $user): void
    {
        $teamIds = $user->teamIds();

        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            // A performance is the team's through the format it stages, or by
            // being the team's own act on an evening somebody else stages —
            // an Õppelava slot's plan belongs to whoever plays the slot.
            ->orWhereHas('performance', fn (Builder $performance) => $performance
                ->whereIn('performances.team_id', $teamIds)
                ->orWhereHas('format', fn (Builder $format) => $format->whereIn('team_id', $teamIds))));
    }

    /**
     * Determine whether the user may write to this plan. Three ways in: they
     * hold the plan's key — handing out the share link is how a plan's author
     * lets somebody else work on it — {@see editableBy()} covers them on the
     * strength of the team the plan's night belongs to, or they hold
     * {@see EDIT_ALL_PERMISSION}, which reaches every plan in the house.
     *
     * @param  string|null  $key  The plan token the caller arrived with, if any.
     */
    public function isEditableBy(User $user, ?string $key = null): bool
    {
        if ($key !== null && $key === $this->token) {
            return true;
        }

        // The crew running the formats fix the plans they are handed, whoever
        // wrote them and whichever group's night they are for.
        if ($user->can(self::EDIT_ALL_PERMISSION)) {
            return true;
        }

        return static::query()
            ->whereKey($this->getKey())
            ->editableBy($user)
            ->exists();
    }

    /**
     * Limit the query to the plans the given user may read in the overview of
     * plans that have been sent in. Holders of {@see VIEW_ALL_PERMISSION} — the
     * crew running the formats — are not limited at all; everybody else is shown
     * their own plans and their groups', whatever state those are in.
     *
     * Reading a plan in the listing reaches exactly as far as writing to it, so
     * this leans on {@see editableBy()} for what makes a plan a group's: the
     * performance is theirs, or the format staging it is. A team-mate's unfinished
     * draft counts here for the same reason it counts there — it is a plan the
     * group still owes, and the overview is where they would go looking for it.
     *
     * @param  Builder<TechnicalPlan>  $query
     */
    #[Scope]
    protected function listableBy(Builder $query, User $user): void
    {
        if ($user->can(self::VIEW_ALL_PERMISSION)) {
            return;
        }

        $query->editableBy($user);
    }

    /**
     * Determine whether the user may read this plan in the overview — see
     * {@see listableBy()}. Answered by the same scope the listing is built
     * with, so a row that is offered always opens.
     */
    public function isListableBy(User $user): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->listableBy($user)
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
     * The performance this plan describes the technical needs of. Every plan
     * names one — a performer whose night is not on the books writes under the
     * stand-in performance, see {@see Performance::placeholder()} — but the
     * relation still reads as null once a performance has been put aside, which
     * is why the listings go on asking for it safely.
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

    /**
     * The properties worth an audit trail. The plan's content — scenes, sound,
     * equipment — is left out: it is what the plan *is*, not a fact about it,
     * and the wizard already keeps every draft as it was last saved.
     *
     * @return array<int, string>
     */
    protected function activityLogAttributes(): array
    {
        return ['status', 'user_id', 'performance_id', 'submitted_at'];
    }
}
