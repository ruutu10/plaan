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

// Nothing chases performers for a missing technical plan on a schedule: the
// reminder is sent by hand from the performance's own page, to the members the
// crew picks — see App\Http\Controllers\PerformanceReminderController.

// Every second day at nine in the morning: this one repeats for as long as the
// gap lasts, so a daily digest was mostly the same letter twice. The lead
// window is a week, which still leaves three or four chances to be read before
// the night arrives, and a morning one is read the same day it lands.
//
// Nine o'clock in the theatre, not on the server — the app runs in UTC, so the
// hour is pinned to the venue zone and stays at nine across a daylight-saving
// change, like every other clock time the house reads.
//
// `*/2` counts days of the month, so a 31-day month runs the 31st and the 1st
// back to back — a day early once in a while, never a run missed.
Schedule::command('performances:remind-missing-technicians')
    ->cron('0 9 */2 * *')
    ->timezone(config('performance.timezone'))
    ->withoutOverlapping()
    ->description('Remind the technical team about upcoming performances missing a technician');

// Daily is plenty: the command's own grace period decides when a plan goes
// quiet, and a few hours either side of it changes nothing for anybody.
Schedule::command('technical-plans:archive')
    ->daily()
    ->description('Archive technical plans whose performance has been played');
