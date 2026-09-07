<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Technical plan wizard
    |--------------------------------------------------------------------------
    |
    | Configuration surfaced to the public "Tehnikaplaan" wizard: how many
    | hours before the show a plan should be submitted, and the technical
    | team's contact address shown to performers.
    |
    */

    'deadline_hours' => (int) env('TECHNICAL_PLAN_DEADLINE_HOURS', 24),

    'tech_email' => env('TECHNICAL_PLAN_TECH_EMAIL', 'ando@ruutu10.ee'),

    /*
    |--------------------------------------------------------------------------
    | Scene sound files
    |--------------------------------------------------------------------------
    |
    | The extensions a scene's sound file may have. This is intersected with
    | `media-library.allowed_extensions`, so it can only ever narrow the
    | general upload allowlist, never widen it.
    |
    */

    'sound_extensions' => [
        'mp3',
        'wav',
        'ogg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Venues that cannot take smoke
    |--------------------------------------------------------------------------
    |
    | Halls whose fire alarm rules out smoke and haze altogether. A plan for a
    | night in one of them is never asked whether the technician may use smoke
    | effects — the question has only one answer there, and the wizard gives it
    | rather than offering a choice the hall would refuse.
    |
    | A performance's location is free text as the Planka board writes it, so
    | each name here is matched case-insensitively anywhere within it:
    | "improkeskus" also covers "Tartu improkeskus" and "improkeskuse BB".
    |
    */

    'smoke_not_possible' => [
        'improkeskus',
    ],

];
