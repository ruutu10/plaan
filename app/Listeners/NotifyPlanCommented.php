<?php

namespace App\Listeners;

use App\Events\TechnicalPlanCommented;
use App\Notifications\TechnicalPlanCommented as TechnicalPlanCommentedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Tell the other side of the conversation that something was said. A comment
 * from the crew goes to the plan's author; a comment from the performer's side
 * goes to the technical team's address. Nobody is ever mailed about their own
 * comment — they are looking at it.
 *
 * A plan with no author — filled in ahead of any account, or one whose author
 * has since been removed — has nobody on the far side to tell, and the crew's
 * remark stays on the page for whoever opens it next.
 */
class NotifyPlanCommented
{
    public function handle(TechnicalPlanCommented $event): void
    {
        $comment = $event->comment;
        $plan = $comment->plan;

        $notification = new TechnicalPlanCommentedNotification($comment);

        if ($comment->from_technical_team) {
            $author = $plan->user;

            if ($author === null) {
                Log::info('A plan with no author was commented on by the crew; nobody to tell', [
                    'plan_id' => $plan->id,
                    'comment_id' => $comment->id,
                ]);

                return;
            }

            // The crew's own people write on their own plans too, and a letter
            // about a remark you just wrote is noise.
            if ($author->is($comment->user)) {
                Log::info('A technician commented on their own plan; no mail sent', [
                    'plan_id' => $plan->id,
                    'comment_id' => $comment->id,
                ]);

                return;
            }

            $author->notify($notification);

            Log::info('Mailed a plan\'s author about a comment from the technical team', [
                'plan_id' => $plan->id,
                'comment_id' => $comment->id,
            ]);

            return;
        }

        $techEmail = (string) config('technical_plan.tech_email');

        if ($techEmail === '') {
            Log::warning('No technical contact configured; a comment on a plan reached nobody', [
                'plan_id' => $plan->id,
                'comment_id' => $comment->id,
            ]);

            return;
        }

        if ($techEmail === $comment->user->email) {
            Log::info('A comment was written from the technical contact\'s own address; no mail sent', [
                'plan_id' => $plan->id,
                'comment_id' => $comment->id,
            ]);

            return;
        }

        Notification::route('mail', $techEmail)->notify($notification);

        Log::info('Mailed the technical team about a comment on a plan', [
            'plan_id' => $plan->id,
            'comment_id' => $comment->id,
        ]);
    }
}
