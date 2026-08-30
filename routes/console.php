<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('team-invitations:prune-expired')
    ->daily()
    ->description('Delete expired team invitations');

Schedule::command('attachments:prune-stale')
    ->weekly()
    ->description('Delete staged uploads never attached to a model');

Schedule::command('planka:import')
    ->cron('5 4 * * 0,2,4') // 4:05 AM on Sunday, Tuesday, and Thursday
    ->description('Import new shows and performances from the Planka board');

// Nothing chases performers for a missing technical plan on a schedule: the
// reminder is sent by hand from the performance's own page, to the members the
// crew picks — see App\Http\Controllers\PerformanceReminderController.

// Daily: this one repeats for as long as the gap lasts, so there is nothing to
// catch by running it more often — only one digest a day, until a technician
// signs on.
Schedule::command('performances:remind-missing-technicians')
    ->daily()
    ->withoutOverlapping()
    ->description('Remind the technical team about upcoming performances missing a technician');

// Daily is plenty: the command's own grace period decides when a plan goes
// quiet, and a few hours either side of it changes nothing for anybody.
Schedule::command('technical-plans:archive')
    ->daily()
    ->description('Archive technical plans whose performance has been played');
