<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('attachments:prune-stale')
    ->weekly()
    ->description('Delete staged uploads never attached to a model');

Schedule::command('planka:import')
    ->weekly()
    ->description('Import new shows and performances from the Planka board');

// Hourly, and quiet almost every hour: the run only mails when a reminder has
// just fallen due. Often enough that the thirty-hour notice lands within an
// hour of its moment, and cheap enough that a missed hour catches up by itself.
Schedule::command('performances:remind-missing-plans')
    ->hourly()
    ->withoutOverlapping()
    ->description('Remind performers about technical plans that have not been handed in');
