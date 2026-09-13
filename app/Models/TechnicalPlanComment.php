<?php

namespace App\Models;

use Database\Factories\TechnicalPlanCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One remark on a technical plan, written on the plan's own overview page. The
 * thread is how the performer and the crew settle the questions a plan raises
 * without either side having to find the other's address.
 *
 * @property int $id
 * @property int $technical_plan_id
 * @property int|null $user_id
 * @property string|null $author_name
 * @property string $body
 * @property bool $from_technical_team
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TechnicalPlan $plan
 * @property-read User|null $user
 */
#[Fillable(['technical_plan_id', 'user_id', 'author_name', 'body', 'from_technical_team'])]
class TechnicalPlanComment extends Model
{
    /** @use HasFactory<TechnicalPlanCommentFactory> */
    use HasFactory;

    /**
     * How long a single comment may run. Long enough for the kind of question
     * a plan actually raises, short enough that anything longer belongs in the
     * plan itself.
     */
    public const MAX_LENGTH = 2000;

    /**
     * How the technician AI signs what it writes on a plan. Kept plainly
     * readable as an agent rather than dressed up as one of the crew: the
     * performer should know at a glance that what they are being asked to fix
     * was found by a machine, and that the crew have not read it yet.
     */
    public const AGENT_AUTHOR_NAME = 'AI tehnik (agent)';

    /**
     * Who to show as the author. A person signs with their account, so their
     * current name is used and follows them when they change it; anything
     * without an account — the technician AI — signs with the name written
     * down beside the comment when it was made.
     */
    public function authorName(): string
    {
        return $this->user === null
            ? (string) $this->author_name
            : $this->user->name;
    }

    /**
     * Determine whether the user may take this comment back off the plan.
     *
     * Two ways: it is theirs, or they hold
     * {@see TechnicalPlan::EDIT_ALL_PERMISSION} — the crew keep the plans in
     * front of them tidy, and a remark that should never have been on a plan
     * is theirs to remove whoever wrote it.
     *
     * Deliberately narrower than who may *write* on a plan: holding the share
     * link lets somebody join the conversation, not edit what others have said
     * in it.
     *
     * A comment with no account behind it — the technician AI's — is nobody's
     * own, so it falls to the crew alone. A performer who disagrees with what
     * the agent found answers it rather than deleting it.
     */
    public function isDeletableBy(User $user): bool
    {
        return $this->user_id === $user->id || $user->can(TechnicalPlan::EDIT_ALL_PERMISSION);
    }

    /**
     * The plan this comment is about.
     *
     * @return BelongsTo<TechnicalPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TechnicalPlan::class, 'technical_plan_id');
    }

    /**
     * Who wrote it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_technical_team' => 'boolean',
        ];
    }
}
