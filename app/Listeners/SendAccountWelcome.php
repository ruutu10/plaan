<?php

namespace App\Listeners;

use App\Actions\BuildWelcomeLoginLink;
use App\Events\UserProvisioned;
use App\Notifications\Users\AccountCreated;
use Illuminate\Support\Facades\Log;

/**
 * Welcome an account a technician created by hand, with a link that logs it
 * in and settles its address — see {@see BuildWelcomeLoginLink}.
 */
class SendAccountWelcome
{
    public function __construct(private BuildWelcomeLoginLink $buildWelcomeLoginLink) {}

    public function handle(UserProvisioned $event): void
    {
        if (! $event->sendWelcome) {
            return;
        }

        $url = $this->buildWelcomeLoginLink->handle($event->user);

        $event->user->notify(new AccountCreated($url, $event->createdBy));

        Log::info('Sent a welcome e-mail to an account created from the management screen', [
            'user_id' => $event->user->id,
            'created_by' => $event->createdBy->id,
        ]);
    }
}
