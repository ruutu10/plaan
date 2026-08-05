<?php

namespace App\Services;

use App\Actions\GrantStaffAccess;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns the bare first name a Planka card gives a crew or cast member into an
 * account, the way {@see PerformanceStaffSync} needs one to
 * write a row. A card almost never gives a full name or an address, so the
 * only thing to match on is the first name — narrowed to the house's own
 * e-mail domains ({@see GrantStaffAccess}), because that is the
 * one fact that tells a "Märt" who happens to have an account from a "Märt"
 * who is a guest performer with none.
 *
 * A name that is not unique within those domains is not a wrong guess away
 * from being right — it is two or more different people — so it resolves to
 * nothing rather than to whichever one happened to be found first.
 */
class StaffMemberLookup
{
    /**
     * The house's own accounts, grouped by first name — read once per run
     * rather than once per name.
     *
     * @var array<string, Collection<int, User>>|null
     */
    protected ?array $usersByFirstName = null;

    /**
     * The account this first name names, or null when there is none — or more
     * than one.
     */
    public function find(string $firstName): ?User
    {
        $key = mb_strtolower(trim($firstName));

        if ($key === '') {
            return null;
        }

        $matches = $this->usersByFirstName()[$key] ?? null;

        return $matches !== null && $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * The house's own accounts — those whose address is on one of the theatre's
     * own e-mail domains — grouped by the first word of their name, folded for
     * the same reason the Planka importer folds names in PHP rather than SQL:
     * SQLite's `LOWER()` leaves Estonian capitals alone.
     *
     * @return array<string, Collection<int, User>>
     */
    protected function usersByFirstName(): array
    {
        if ($this->usersByFirstName !== null) {
            return $this->usersByFirstName;
        }

        /** @var array<int, string> $domains */
        $domains = config('mail.verified_email_domains', []);
        $domains = array_map(strtolower(...), $domains);

        return $this->usersByFirstName = User::query()
            ->get(['id', 'name', 'email'])
            ->filter(fn (User $user): bool => in_array(
                Str::of($user->email)->afterLast('@')->trim()->lower()->value(),
                $domains,
                true,
            ))
            ->groupBy(fn (User $user): string => mb_strtolower(trim(Str::before($user->name, ' '))))
            ->all();
    }
}
