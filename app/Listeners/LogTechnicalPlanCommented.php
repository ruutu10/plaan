<?php

namespace App\Listeners;

use App\Events\TechnicalPlanCommented;
use App\Http\Controllers\TechnicalPlanCommentController;

/**
 * Keep an audit-trail entry for every remark left on a plan, filed under the
 * plan so it reads alongside the plan's own changes. A comment taken back off
 * is filed by {@see TechnicalPlanCommentController}
 * itself, since no event is fired for that.
 */
class LogTechnicalPlanCommented
{
    public function handle(TechnicalPlanCommented $event): void
    {
        $comment = $event->comment;

        activity()
            ->performedOn($comment->plan)
            ->causedBy($comment->user)
            ->event('commented')
            ->withProperties([
                'comment_id' => $comment->id,
                'from_technical_team' => $comment->from_technical_team,
            ])
            ->log(sprintf(
                'Technical plan commented on by %s',
                $comment->user->name ?? $comment->author_name ?? 'an unknown author',
            ));
    }
}
