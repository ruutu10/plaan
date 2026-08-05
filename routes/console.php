<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('team-invitations:prune-expired')
    ->daily()
    ->description('Delete expired team invitations');

Schedule::command('attachments:prune-stale')
    ->weekly()
    ->description('Delete staged uploads never attached to a model');

Schedule::command('planka:import')
    ->daily()
    ->description('Import new shows and performances from the Planka board');

// Hourly, and quiet almost every hour: the run only mails when a reminder has
// just fallen due. Often enough that the thirty-hour notice lands within an
// hour of its moment, and cheap enough that a missed hour catches up by itself.
Schedule::command('performances:remind-missing-plans')
    ->hourly()
    ->withoutOverlapping()
    ->description('Remind performers about technical plans that have not been handed in');

// Daily, not hourly: unlike the technical-plan reminders this one repeats for
// as long as the gap lasts, so there is nothing to catch by running it more
// often — only one digest a day, until a technician signs on.
Schedule::command('performances:remind-missing-technicians')
    ->daily()
    ->withoutOverlapping()
    ->description('Remind the technical team about upcoming performances missing a technician');

// Daily is plenty: the command's own grace period decides when a plan goes
// quiet, and a few hours either side of it changes nothing for anybody.
Schedule::command('technical-plans:archive')
    ->daily()
    ->description('Archive technical plans whose performance has been played');
