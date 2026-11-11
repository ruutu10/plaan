<?php

namespace App\Actions;

use App\Http\Middleware\CaptureRequestOrigin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Models\Activity;

/**
 * Files every audit-trail entry with the address and browser of the request
 * behind it, under the `ip` and `user_agent` properties — whether the entry is
 * written during that request or by a job it queued.
 *
 * Something with no request behind it at all, like a console import, gets
 * neither: a made-up origin would be worse than none. A value an entry already
 * carries under either key is left as it is.
 */
class LogActivityWithRequestOrigin extends LogActivityAction
{
    protected function transformChanges(Model $activity): void
    {
        parent::transformChanges($activity);

        if (! $activity instanceof Activity || ! Context::hasHidden(CaptureRequestOrigin::IP)) {
            return;
        }

        $activity->properties = collect([
            'ip' => Context::getHidden(CaptureRequestOrigin::IP),
            'user_agent' => Context::getHidden(CaptureRequestOrigin::USER_AGENT),
        ])->merge($activity->properties ?? []);
    }
}
