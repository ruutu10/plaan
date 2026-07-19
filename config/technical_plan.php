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

];
