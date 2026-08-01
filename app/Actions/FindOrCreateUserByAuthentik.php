<?php

namespace App\Actions;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class FindOrCreateUserByAuthentik
{
    public function __construct(
        private GrantStaffAccess $grantStaffAccess,
    ) {}

    /**
     * Find the user for this Authentik identity, linking or provisioning an
     * account as needed, so an SSO login always resolves to a real user —
     * mirroring App\Actions\Fortify\CreateNewUser, a freshly provisioned
     * account joins no team of its own unless its address takes it into the
     * house team (see App\Actions\GrantStaffAccess).
     */
    public function handle(SocialiteUser $ssoUser): User
    {
        $subject = (string) $ssoUser->getId();
        $email = strtolower(trim((string) $ssoUser->getEmail()));

        if ($user = User::where('authentik_id', $subject)->first()) {
            return $user;
        }

        if ($user = User::where('email', $email)->first()) {
            $user->forceFill(['authentik_id' => $subject])->save();

            Log::info('Linked existing account to Authentik', [
                'user_id' => $user->id,
            ]);

            return $user;
        }

        return DB::transaction(function () use ($ssoUser, $subject, $email) {
            $user = User::create([
                'name' => $ssoUser->getName() ?: Str::of($email)->before('@')->trim()->value(),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'authentik_id' => $subject,
                'signup_source' => SignupSource::AuthentikSso->value,
            ]);

            // Authentik is the authority on this address; a self-registered
            // account still has to click a verification e-mail, an SSO one
            // does not.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->grantStaffAccess->handle($user);

            Log::info('Provisioned a new account via Authentik SSO', [
                'user_id' => $user->id,
                'email' => $user->email,
                'signup_source' => SignupSource::AuthentikSso->value,
            ]);

            return $user;
        });
    }
}
