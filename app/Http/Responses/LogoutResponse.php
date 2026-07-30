<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        // Authentik's own session outlives this one, so without this cookie
        // App\Actions\Sso\AttemptSilentAuthentikLogin would silently sign the
        // user straight back in on their very next visit to /login or
        // /tehnikaplaan — "log out" would look broken.
        $loggedOut = cookie('sso_logged_out', '1', 1);

        return $request->wantsJson()
            ? (new JsonResponse('', 204))->withCookie($loggedOut)
            : redirect(Fortify::redirects('logout', '/'))->withCookie($loggedOut);
    }
}
