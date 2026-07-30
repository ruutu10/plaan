<?php

namespace App\Actions\Sso;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class RedirectToAuthentik
{
    /**
     * Build the redirect to Authentik's authorize endpoint. When $silent is
     * true this adds prompt=none: Authentik will bounce straight back with
     * an auth code if the browser already holds an Authentik session, or
     * with an error (typically login_required) if it doesn't — either way,
     * no UI is ever shown to the user.
     */
    public function handle(Request $request, bool $silent): RedirectResponse
    {
        $request->session()->put('sso.silent_attempt', $silent);

        $provider = Socialite::driver('authentik');

        return $silent
            ? $provider->with(['prompt' => 'none'])->redirect()
            : $provider->redirect();
    }
}
