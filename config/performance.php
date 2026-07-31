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
    | Technical-plan reminders
    |--------------------------------------------------------------------------
    |
    | Performers are chased for a technical plan that has not been handed in
    | yet. When the reminders are due is fixed by App\Enums\ReminderSchedule;
    | this switch only decides whether the scheduled run mails anything at all,
    | so a house that would rather chase by hand can turn it off.
    |
    */

    'reminders' => [

        'enabled' => (bool) env('PERFORMANCE_REMINDERS_ENABLED', true),

    ],

];
