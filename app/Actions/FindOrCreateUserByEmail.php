<?php

namespace App\Actions;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FindOrCreateUserByEmail
{
    /**
     * Find the user for the given e-mail, creating a lightweight account if
     * none exists yet, so plans, magic-link logins, and team memberships can
     * be tied to a real user record.
     */
    public function handle(string $email, SignupSource $signupSource = SignupSource::AnonymousPlan): User
    {
        $user = User::where('email', User::normalizeEmail($email))->first()
            ?? User::provision($email, $signupSource);

        if ($user->wasRecentlyCreated) {
            // Accounts born here were never registered by hand, so this is the
            // only trail of where an unfamiliar user came from.
            Log::info('Provisioned a lightweight account for an unknown e-mail', [
                'user_id' => $user->id,
                'signup_source' => $signupSource->value,
            ]);
        }

        return $user;
    }
}
