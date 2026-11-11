<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Take the visitor's address from Cloudflare's CF-Connecting-IP header, so
 * that {@see Request::ip()} names the visitor rather than the Cloudflare edge
 * that forwarded them.
 *
 * The header is believed only when the request reached the application through
 * a trusted proxy (see config/trustedproxy.php); from any other peer it is
 * ignored, so a caller cannot claim an address by sending it directly.
 *
 * The address replaces X-Forwarded-For rather than REMOTE_ADDR: the peer stays
 * a trusted proxy, so its forwarded scheme and host are still honoured.
 */
class TrustCloudflareConnectingIp
{
    public const HEADER = 'CF-Connecting-IP';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connectingIp = $request->headers->get(self::HEADER);

        if ($connectingIp !== null
            && filter_var($connectingIp, FILTER_VALIDATE_IP) !== false
            && $request->isFromTrustedProxy()) {
            $request->headers->set('X-Forwarded-For', $connectingIp);
        }

        return $next($request);
    }
}
