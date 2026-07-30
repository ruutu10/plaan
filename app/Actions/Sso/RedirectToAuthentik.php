<?php

namespace App\Actions\Sso;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class RedirectToAuthentik
{
    /**
     * Build the redirect to Authentik's authorize endpoint. When $silent is
     * true this adds prompt=none: Authentik will bounce straight back with
     * an auth code if the browser already holds an Authentik session, or
     * with an error (typically login_required) if it doesn't — either way,
     * no UI is ever shown to the user.
     */
    public function handle(Request $request, bool $silent): Response
    {
        $request->session()->put('sso.silent_attempt', $silent);

        $provider = Socialite::driver('authentik');

        $redirect = $silent
            ? $provider->with(['prompt' => 'none'])->redirect()
            : $provider->redirect();

        // Authentik is a different origin. If the caller reached this action
        // via an Inertia client-side visit (e.g. an in-app link to
        // /tehnikaplaan), a plain redirect would have the browser's fetch()
        // auto-follow it to Authentik as a cross-origin XHR — which Authentik
        // refuses with a CORS error, since it only expects a real page
        // navigation. Inertia::location() detects that case and tells the
        // client to do a full window.location visit instead; for an ordinary
        // full-page request (like landing on /login fresh) it's a no-op
        // passthrough of the same redirect.
        return Inertia::location($redirect);
    }
}
