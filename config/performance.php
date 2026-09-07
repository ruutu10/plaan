<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Venue timezone
    |--------------------------------------------------------------------------
    |
    | The wall clock the house runs on. Start times are stored in UTC, like
    | every other timestamp in the app, and read back through this zone — so
    | "19:00" means seven in the evening in the theatre whatever the server
    | thinks the time is, and goes on meaning it across a daylight-saving
    | change.
    |
    */

    'timezone' => env('PERFORMANCE_TIMEZONE', 'Europe/Tallinn'),

    /*
    |--------------------------------------------------------------------------
    | Default start time
    |--------------------------------------------------------------------------
    |
    | The venue-local time a performance starts at when nobody has said
    | otherwise: what the Planka import falls back to for a card naming no
    | time, and what the performances registered before start times existed
    | were moved to.
    |
    */

    'default_start_time' => env('PERFORMANCE_DEFAULT_START_TIME', '19:00'),

    /*
    |--------------------------------------------------------------------------
    | Missing-technician reminders
    |--------------------------------------------------------------------------
    |
    | The technical team is chased every second day, at nine in the morning
    | on the venue clock above, about upcoming performances nobody has
    | signed on to run sound and light for — see
    | App\Console\Commands\RemindAboutMissingTechnicians. `lead_days` decides
    | how far ahead a performance starts showing up on the digest; `enabled`
    | is a switch of its own, separate from the technical-plan reminders
    | above, so a house may run one without the other.
    |
    */

    'technician_reminders' => [

        'enabled' => (bool) env('PERFORMANCE_TECHNICIAN_REMINDERS_ENABLED', true),

        'lead_days' => (int) env('PERFORMANCE_TECHNICIAN_REMINDER_LEAD_DAYS', 7),

    ],

];
