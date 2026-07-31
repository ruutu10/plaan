<?php

namespace App\Actions;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GrantStaffAccess
{
    /**
     * The role the house's own people hold: it carries the right to read every
     * technical plan that has been handed in.
     */
    public const ROLE = 'staff';

    /**
     * Take an account into the house if its address belongs to the theatre — a
     * seat in the theatre's own team, and the staff role — so colleagues do not
     * have to be invited by hand. Anybody else is left exactly as they were.
     *
     * The address has to have been proven first: an unverified one is only a
     * claim, and typing a colleague's domain into the sign-up form must not be
     * enough to read the house's plans. Every door into the application either
     * proves the address itself (SSO) or ends up here through
     * {@see Verified}.
     *
     * Safe to call more than once: neither the membership nor the role is
     * duplicated.
     *
     * @return bool Whether the account was taken on as staff.
     */
    public function handle(User $user): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        if (! $this->isHouseAddress($user->email)) {
            return false;
        }

        return DB::transaction(function () use ($user) {
            $team = Team::firstOrCreate(
                ['name' => (string) config('teams.theatre_team_name')],
                ['is_personal' => false],
            );

            $user->teams()->syncWithoutDetaching([
                $team->id => ['role' => TeamRole::Member->value],
            ]);

            $user->assignRole(self::ROLE);

            // Accounts that never got a team of their own — the ones the magic
            // link provisions — would otherwise land nowhere.
            if (! $user->current_team_id) {
                $user->switchTeam($team);
            }

            Log::info('Granted staff access to a house e-mail address', [
                'user_id' => $user->id,
                'email' => $user->email,
                'team_id' => $team->id,
            ]);

            return true;
        });
    }

    /**
     * Determine whether the address belongs to one of the theatre's own
     * domains.
     */
    private function isHouseAddress(string $email): bool
    {
        if (! str_contains($email, '@')) {
            return false;
        }

        $domain = Str::of($email)->afterLast('@')->trim()->lower()->value();

        /** @var array<int, string> $houseDomains */
        $houseDomains = config('mail.verified_email_domains', []);

        return in_array($domain, array_map(strtolower(...), $houseDomains), true);
    }
}
