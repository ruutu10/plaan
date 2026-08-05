<?php

namespace App\Actions\MagicLink;

use App\Models\Team;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use MagicLink\Actions\LoginAction;

/**
 * The magic link's own login, which also settles the address it was sent to.
 *
 * A link that arrives by e-mail and is followed is the same proof the
 * verification mail asks for — the person clicking it has read the mailbox —
 * so there is no sense in mailing them a second link to prove the same thing.
 * Marking it here is what lets a house address reach its staff role by this
 * door as well: the grant hangs off {@see Verified}.
 */
class LogInAndVerifyEmail extends LoginAction
{
    /**
     * A team to make current once the user is logged in — for a newcomer
     * whose account only exists because they were added to this team, so it
     * should not linger without one until the link is followed and the
     * membership is confirmed real.
     *
     * @param  mixed  $httpResponse
     */
    public function __construct(
        Authenticatable $user,
        $httpResponse = null,
        ?string $guard = null,
        private readonly ?int $switchToTeamId = null,
    ) {
        parent::__construct($user, $httpResponse, $guard);
    }

    /**
     * Execute Action.
     *
     * Whatever the link was built to answer with is passed straight back: the
     * package lets an action carry a response, a rendered view or a closure,
     * and none of that is this class's business.
     */
    public function run(): mixed
    {
        $response = parent::run();

        $user = Auth::guard($this->guard)->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        if ($this->switchToTeamId !== null && $team = Team::find($this->switchToTeamId)) {
            $user->switchTeam($team);
        }

        return $response;
    }
}
