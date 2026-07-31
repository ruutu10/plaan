<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateUserByAuthentik;
use App\Actions\Sso\RedirectToAuthentik;
use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthentikController extends Controller
{
    use RedirectsToCurrentTeam;

    /**
     * The user-initiated "Continue with Authentik" redirect.
     */
    public function redirect(Request $request, RedirectToAuthentik $redirectToAuthentik): Response
    {
        return $redirectToAuthentik->handle($request, silent: false);
    }

    /**
     * Shared callback for both the interactive redirect above and the
     * silent prompt=none attempt fired by AttemptSilentAuthentikLogin.
     */
    public function callback(Request $request, FindOrCreateUserByAuthentik $findOrCreateUser): RedirectResponse
    {
        $silent = (bool) $request->session()->pull('sso.silent_attempt', false);

        if ($request->has('error')) {
            Log::info('Authentik login did not complete', [
                'error' => $request->string('error')->value(),
                'silent' => $silent,
            ]);

            return $this->failure($request, $silent);
        }

        try {
            $ssoUser = Socialite::driver('authentik')->user();
        } catch (Throwable $e) {
            Log::warning('Authentik callback failed to exchange the code', [
                'exception' => $e->getMessage(),
                'silent' => $silent,
            ]);

            return $this->failure($request, $silent);
        }

        $user = $findOrCreateUser->handle($ssoUser);

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->logSignIn($request, 'authentik');

        return redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }

    /**
     * A silent (prompt=none) failure is swallowed with no visible error — it
     * just means there was no Authentik session, which is the expected
     * outcome for most visitors — and returns to whichever page triggered
     * the check (peeking at `url.intended`, the same value a successful
     * login would consume via redirect()->intended(), rather than always
     * defaulting to /login). An interactive failure always goes to /login,
     * with a visible error, since that's the only place the button lives.
     */
    private function failure(Request $request, bool $silent): RedirectResponse
    {
        if (! $silent) {
            return redirect()->route('login')->withErrors([
                'email' => 'Authentikuga sisselogimine ebaõnnestus.',
            ]);
        }

        return redirect()->to($request->session()->get('url.intended', route('login')));
    }
}
