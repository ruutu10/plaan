<?php

namespace App\Actions;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FindOrCreateUserByEmail
{
    /**
     * Find the user for the given e-mail, creating a lightweight account if
     * none exists yet, so plans and magic-link logins can be tied to a real
     * user record.
     */
    public function handle(string $email): User
    {
        $email = strtolower(trim($email));

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::of($email)->before('@')->trim()->value() ?: 'Esineja',
                'password' => Hash::make(Str::random(40)),
                'signup_source' => SignupSource::AnonymousPlan->value,
            ],
        );

        if ($user->wasRecentlyCreated) {
            // Accounts born here were never registered by hand, so this is the
            // only trail of where an unfamiliar user came from.
            Log::info('Provisioned a lightweight account for an unknown e-mail', [
                'user_id' => $user->id,
                'signup_source' => SignupSource::AnonymousPlan->value,
            ]);
        }

        return $user;
    }
}
