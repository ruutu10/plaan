<?php

use Anthropic\Messages\Model;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => Model::CLAUDE_SONNET_5,
        'max_tokens' => 10000,
    ],

    /*
    | The Planka board the season is planned on. Every card in the watched
    | lists describes one evening; the import reads their descriptions. The
    | token is a Planka API key, sent in the `X-Api-Key` header.
    */
    'planka' => [
        'url' => env('PLANKA_URL'),
        'list_ids' => env('PLANKA_LIST_IDS'),
        'token' => env('PLANKA_ACCESS_TOKEN'),

        // Cards carrying any of these board labels are not performances and
        // are passed over without being read.
        'excluded_labels' => ['TÖÖTUBA'],
    ],

];
