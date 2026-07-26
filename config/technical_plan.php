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

];
