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
 * only thing to match on is the first name.
 *
 * Anybody with an account can staff a night — guests play on the house's
 * stage and lend a hand behind it — but the house's own people, those on its
 * e-mail domains ({@see GrantStaffAccess}), are looked for first: a card
 * naming "Märt" means the house's Märt when there is one, not a guest who
 * happens to share the name.
 *
 * A name that is not unique among the accounts it is looked for in is not a
 * wrong guess away from being right — it is two or more different people — so
 * it resolves to nothing rather than to whichever one happened to be found
 * first.
 */
class StaffMemberLookup
{
    /**
     * Every account — read once per run rather than once per name.
     *
     * @var Collection<int, User>|null
     */
    protected ?Collection $users = null;

    /**
     * The account this name names: the house's own when the house has one by
     * that name, otherwise anybody's — still only when exactly one account
     * answers to it.
     *
     * The name is read as a first name first. Only when no account has that
     * first name is it matched against whole account names instead — so a
     * card that does spell out "Arne Tamm" is matched on all of it.
     *
     * A name shared by two house accounts is not handed on to the wider
     * search: it is already two people, and finding a third outside would
     * only be a guess.
     */
    public function find(string $name): ?User
    {
        $key = $this->fold($name);

        if ($key === '') {
            return null;
        }

        $matches = $this->users()->filter(fn (User $user): bool => $this->fold(Str::before($user->name, ' ')) === $key);

        if ($matches->isEmpty()) {
            $matches = $this->users()->filter(fn (User $user): bool => $this->fold($user->name) === $key);
        }

        $house = $matches->filter(fn (User $user): bool => $user->isHouseStaff());

        return $house->isNotEmpty() ? $this->soleMatch($house) : $this->soleMatch($matches);
    }

    /**
     * The one account in the matches, or null when there is none or more.
     *
     * @param  Collection<int, User>  $matches
     */
    protected function soleMatch(Collection $matches): ?User
    {
        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * A name as it is compared: trimmed, its runs of whitespace made single,
     * and folded in PHP for the same reason the Planka importer folds names
     * in PHP rather than SQL — SQLite's `LOWER()` leaves Estonian capitals
     * alone.
     */
    protected function fold(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    /**
     * Every account, with only what matching needs.
     *
     * @return Collection<int, User>
     */
    protected function users(): Collection
    {
        return $this->users ??= User::query()->get(['id', 'name', 'email'])->toBase();
    }
}
