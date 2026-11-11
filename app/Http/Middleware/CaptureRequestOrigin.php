<?php

namespace App\Http\Middleware;

use App\Actions\LogActivityWithRequestOrigin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Note where a request came from — its address and browser — so that whatever
 * it sets off can say so in the audit trail; see
 * {@see LogActivityWithRequestOrigin}.
 *
 * Held as hidden context: it travels with every job the request queues, so a
 * letter mailed or a sync run on the queue still carries the origin of the
 * click that started it, and it stays out of every ordinary log line.
 */
class CaptureRequestOrigin
{
    public const IP = 'request_ip';

    public const USER_AGENT = 'request_user_agent';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::addHidden([
            self::IP => $request->ip(),
            self::USER_AGENT => $request->userAgent(),
        ]);

        return $next($request);
    }
}
