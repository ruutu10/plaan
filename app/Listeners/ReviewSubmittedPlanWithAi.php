<?php

namespace App\Listeners;

use App\Events\TechnicalPlanCommented;
use App\Events\TechnicalPlanSubmitted;
use App\Models\TechnicalPlan;
use App\Models\TechnicalPlanComment;
use App\Services\TechnicalPlanCriticalFindings;
use App\Services\TechnicalPlanReviewer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read a plan the moment it arrives, and say so on the plan if it cannot be
 * played.
 *
 * The wizard has always offered the performer an AI review on a button, but a
 * button is only pressed by somebody who already suspects their plan needs it.
 * A plan submitted without it reaches the crew unread, and whatever is wrong
 * with it surfaces at the technical run-through an hour before curtain-up.
 *
 * So every first submission is reviewed here, and that review is sifted for the
 * things that would stop the show — see {@see TechnicalPlanCriticalFindings}.
 * Anything found goes onto the plan's own thread as a comment from the crew's
 * side, which mails the performer and leaves the question where it is answered.
 * Nothing found, and nobody hears from us: the great majority of plans are fine,
 * and a letter for each of them teaches performers to ignore the ones that
 * matter.
 *
 * Only first submissions. A performer tidying up a plan the team already holds
 * resubmits it as a matter of course — the same reasoning
 * {@see NotifyPlanSubmitted} declines to mail those — and being told off by a
 * machine on every pass is what would stop them tidying.
 *
 * Queued, and forgiving: two paid API calls have no business inside the request
 * that saves a plan, and a plan is submitted whether or not a review of it was
 * possible.
 */
class ReviewSubmittedPlanWithAi implements ShouldQueue
{
    /**
     * A plan deleted between its submission and this running has nothing left
     * to say about it — that is not a failure worth keeping.
     */
    public bool $deleteWhenMissingModels = true;

    public function handle(TechnicalPlanSubmitted $event): void
    {
        $plan = $event->plan;

        if ($event->isResubmission()) {
            Log::info('An already-submitted plan was updated; not reviewing it again', [
                'plan_id' => $plan->id,
                'previous_status' => $event->previousStatus->value,
            ]);

            return;
        }

        // Checked before the reviewer is resolved: it builds its API client in
        // its constructor, so an unconfigured house would fail on the way in.
        if (blank(config('services.anthropic.key'))) {
            Log::info('No Anthropic key configured; a submitted plan went unreviewed', [
                'plan_id' => $plan->id,
            ]);

            return;
        }

        $findings = $this->findShowStoppers($plan);

        if ($findings === []) {
            return;
        }

        $comment = $plan->comments()->create([
            'user_id' => null,
            'author_name' => TechnicalPlanComment::AGENT_AUTHOR_NAME,
            'body' => $this->composeBody($findings),
            'from_technical_team' => true,
        ]);

        // What tells the performer. The crew's side of the thread is mailed to
        // a plan's author — see {@see NotifyPlanCommented}.
        TechnicalPlanCommented::dispatch($comment);

        Log::info('Wrote the technician AI\'s findings onto a submitted plan', [
            'plan_id' => $plan->id,
            'comment_id' => $comment->id,
            'findings' => count($findings),
        ]);
    }

    /**
     * Review the plan and sift that review, or return nothing at all.
     *
     * Either call is a paid round trip to somebody else's API, and neither is
     * worth failing a submission over: a plan nobody could review is still a
     * plan the crew hold, and they read it themselves as they always have.
     *
     * @return list<string>
     */
    private function findShowStoppers(TechnicalPlan $plan): array
    {
        try {
            $review = app(TechnicalPlanReviewer::class)->reviewMarkdown($plan);
        } catch (Throwable $e) {
            report($e);

            Log::error('Reviewing a submitted plan failed', [
                'plan_id' => $plan->id,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        if (trim($review) === '') {
            Log::warning('A submitted plan\'s review came back empty; nothing to sift', [
                'plan_id' => $plan->id,
            ]);

            return [];
        }

        try {
            $findings = app(TechnicalPlanCriticalFindings::class)->findIn($review);
        } catch (Throwable $e) {
            report($e);

            Log::error('Sifting a submitted plan\'s review failed', [
                'plan_id' => $plan->id,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        if ($findings === []) {
            // The expected outcome, and deliberately a silent one.
            Log::info('A submitted plan was reviewed and holds nothing show-stopping', [
                'plan_id' => $plan->id,
            ]);
        }

        return $findings;
    }

    /**
     * The remark as it appears on the plan: a line saying why it is there, then
     * the findings themselves as a list.
     *
     * Written as markdown, which is what the thread renders. Cut to the length
     * a comment may run — a second pass that produces two thousand characters
     * of show-stoppers has misunderstood its job, and the plan needs a person
     * rather than a longer comment.
     *
     * @param  list<string>  $findings
     */
    private function composeBody(array $findings): string
    {
        $intro = 'Vaatasin plaani üle ja tekkisid küsimused. Palun täpsusta plaanis:';

        $body = $intro."\n\n".collect($findings)
            ->map(fn (string $finding): string => '- '.$finding)
            ->implode("\n");

        if (mb_strlen($body) <= TechnicalPlanComment::MAX_LENGTH) {
            return $body;
        }

        Log::warning('The technician AI\'s findings ran longer than a comment may be', [
            'findings' => count($findings),
            'length' => mb_strlen($body),
        ]);

        // One short of the limit, so the ellipsis that says it was cut fits
        // inside it rather than pushing past it.
        return Str::limit($body, TechnicalPlanComment::MAX_LENGTH - 1, '…');
    }
}
