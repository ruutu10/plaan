<?php

namespace App\Actions;

use App\Models\Performance;
use App\Models\User;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;

/**
 * The link a reminder is really made of: one press and the performer is signed
 * in, on the technical-plan wizard, with the right night already chosen and the
 * first step behind them.
 *
 * A performer chased about a plan is by definition somebody who has not got
 * round to it. Anything standing between the mail and the form — remembering
 * which address they used, waiting for a second mail to log in, hunting their
 * performance out of a list — is another place to give up, so the link clears all
 * three at once.
 */
class BuildTechnicalPlanInvite
{
    /**
     * The step the link opens on, counting from zero. Step one is choosing the
     * night, which the link has already done, so the performer lands on the
     * first question they actually have to answer.
     */
    private const OPENS_ON_STEP = 1;

    /**
     * How long after the curtain the link goes on working. The plan is no use
     * afterwards, but a link that dies while somebody is still filling the form
     * is worse than one that outlives its purpose by an evening.
     */
    private const GRACE_HOURS = 12;

    /**
     * How many times the link may be followed. Writing a plan is not one
     * sitting for most people — they open the mail, get halfway, and come back
     * to it — so this is a bound on a leaked link rather than a usage budget.
     */
    private const MAX_VISITS = 25;

    /**
     * Build a single-user login link that lands on this performance's plan.
     *
     * The link is a credential, and it is the recipient's own: it signs in
     * whoever follows it as {@see $user}. One is built per performer rather
     * than one per mail.
     */
    public function handle(User $user, Performance $performance): string
    {
        $action = new LoginAction($user, redirect()->to($this->wizardUrl($performance)));

        return MagicLink::create(
            $action,
            lifetime: $this->lifetimeMinutes($performance),
            numMaxVisits: self::MAX_VISITS,
        )->url;
    }

    /**
     * Where the link comes out: the wizard, told which night it is for and
     * which step to open on.
     */
    public function wizardUrl(Performance $performance): string
    {
        return route('technical-plan.index', [
            'performance' => $performance->id,
            'step' => self::OPENS_ON_STEP,
        ]);
    }

    /**
     * How long the link stays good for, in minutes: until the performance has
     * been and gone. A reminder six days out therefore carries a link that
     * lasts the six days, and one sent the night before carries a short-lived
     * one — which is the right way round.
     */
    private function lifetimeMinutes(Performance $performance): int
    {
        $expiresAt = $performance->date->copy()->addHours(self::GRACE_HOURS);

        // A performance already past its grace (which the reminder run will not
        // hand us, but nothing here should depend on that) still gets a link
        // that works for the hour rather than one that is born expired.
        return max(60, (int) ceil(now()->diffInMinutes($expiresAt, absolute: false)));
    }
}
