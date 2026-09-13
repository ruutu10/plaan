<?php

namespace App\Http\Controllers;

use App\Events\TechnicalPlanCommented;
use App\Http\Requests\StoreTechnicalPlanCommentRequest;
use App\Http\Resources\PlanComment as PlanCommentResource;
use App\Models\TechnicalPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The conversation about one plan: what has been said, and saying something.
 *
 * Both endpoints are read and written by the plan's own overview page — the
 * last step of the wizard, and the same page a share link opens — over the
 * wizard's JSON API rather than through Inertia, because that page is served
 * to guests as well.
 */
class TechnicalPlanCommentController extends Controller
{
    /**
     * The thread on a plan, oldest first.
     *
     * Open without an account, like the plan itself: a share link opens the
     * whole overview page, and the conversation is part of what that page says.
     * Only names travel with it, never addresses — see {@see PlanCommentResource}.
     */
    public function index(Request $request, TechnicalPlan $plan): JsonResponse
    {
        $comments = $plan->comments()->with('user')->get();

        return response()->json([
            'results' => PlanCommentResource::collection($comments)->resolve($request),
            // Said here rather than worked out in the browser: the same rule
            // decides it as guards the endpoint below, and only one of the two
            // should be in a position to change its mind.
            'canComment' => $this->mayComment($request, $plan),
        ]);
    }

    /**
     * Say something on a plan. Writing on a plan reaches exactly as far as
     * changing it does — see {@see TechnicalPlan::isEditableBy()} — so the
     * plan's author, their team, the crew, and anyone signed in holding the
     * share link may all join the conversation.
     */
    public function store(StoreTechnicalPlanCommentRequest $request, TechnicalPlan $plan): JsonResponse
    {
        $user = $request->user();

        if (! $this->mayComment($request, $plan)) {
            Log::warning('Refused a comment on a plan the user may not write to', [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        // Which side of the conversation this is decides who is told about it,
        // and it is written down rather than looked up again later: a comment
        // goes on having come from the crew even after its writer has left it.
        $fromTechnicalTeam = $user->can(TechnicalPlan::VIEW_ALL_PERMISSION);

        $comment = $plan->comments()->create([
            'user_id' => $user->id,
            'body' => $request->validated('body'),
            'from_technical_team' => $fromTechnicalTeam,
        ]);

        // Who is mailed about a comment is between the event and its listeners
        // — see App\Listeners\NotifyPlanCommented.
        TechnicalPlanCommented::dispatch($comment);

        Log::info('A technical plan was commented on', [
            'plan_id' => $plan->id,
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'from_technical_team' => $fromTechnicalTeam,
        ]);

        $comment->setRelation('user', $user);

        return response()->json(
            PlanCommentResource::make($comment)->resolve($request),
            201,
        );
    }

    /**
     * Whether this visitor may add to the plan's conversation: signed in, and
     * holding the plan as somebody who could change it.
     *
     * Reaching a plan here means naming its token in the URL, and holding a
     * plan's token is what its author hands out to let somebody else work on
     * it — so for a signed-in visitor that half of the rule always answers.
     * The question is asked through {@see TechnicalPlan::isEditableBy()} all
     * the same, so joining the conversation reaches exactly as far as the
     * wizard's own save does, and goes on doing so if that rule is ever
     * tightened.
     */
    private function mayComment(Request $request, TechnicalPlan $plan): bool
    {
        $user = $request->user();

        return $user !== null && $plan->isEditableBy($user, $plan->token);
    }
}
