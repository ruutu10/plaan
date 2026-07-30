<?php

namespace App\Actions\Sso;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AttemptSilentAuthentikLogin
{
    public function __construct(private RedirectToAuthentik $redirectToAuthentik) {}

    /**
     * Returns a redirect to Authentik if a silent prompt=none check should be
     * attempted here, or null if the caller should render its normal guest
     * view (already checked this session, SSO not configured, user just
     * logged out, etc.).
     *
     * Where the user lands after a successful login is whatever Laravel's
     * own `url.intended` session key already points at (set by Laravel when
     * a guest is bounced off a protected route, consumed by
     * `redirect()->intended()` in AuthentikController::callback()) — the
     * same mechanism every other login method in this app relies on. This
     * method does not touch it: for the plain `/login` page, no intended URL
     * being set is the correct, already-existing default (falls through to
     * `Fortify::redirects('login')`). Callers that are themselves a
     * destination worth returning to — like the technical-plan wizard's
     * entry page — set their own fallback before calling this.
     */
    public function handle(Request $request): ?Response
    {
        if (! $this->shouldAttempt($request)) {
            return null;
        }

        $request->session()->put('sso.silent_checked', true);

        return $this->redirectToAuthentik->handle($request, silent: true);
    }

    private function shouldAttempt(Request $request): bool
    {
        return filled(config('services.authentik.client_id'))
            && Auth::guard('web')->guest()
            && ! $request->session()->get('sso.silent_checked')
            && ! $request->hasCookie('sso_logged_out');
    }
}
