<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlanComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One remark in a plan's conversation, as the overview page shows it.
 *
 * Only the writer's name travels, never their address: the thread opens to
 * anyone holding the plan's share link, and a link is not what hands out an
 * e-mail address — the same rule the plan document itself is written by, see
 * {@see TechnicalPlan::authorEmail()}.
 *
 * @property-read TechnicalPlanComment $resource
 */
class PlanComment extends JsonResource
{
    /**
     * Consumed unwrapped, like every other payload the wizard reads.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the comment into the shape the overview page renders.
     *
     * @return array{id: int, body: string, authorName: string, fromTechnicalTeam: bool, createdAt: string|null}
     */
    public function toArray(Request $request): array
    {
        $comment = $this->resource;

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            // Never nameless: a comment is only written by somebody signed in,
            // and one whose writer is later removed goes with them.
            'authorName' => $comment->user->name,
            'fromTechnicalTeam' => $comment->from_technical_team,
            'createdAt' => $comment->created_at?->toIso8601String(),
        ];
    }
}
