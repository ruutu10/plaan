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
        'max_tokens' => 20000,

        // How long an answer is reused for a request that is identical down to
        // the last character of its prompt, in seconds.
        'cache_ttl' => env('ANTHROPIC_CACHE_TTL', 60 * 60 * 24 * 21),
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

    /*
    | The Jellyfin library the recordings of played nights live in. A crew
    | member pastes an episode's address onto the performance it recorded, and
    | what this app knows about the night — who played it, when, where — is
    | pushed to that one episode. Nothing is ever read back.
    |
    | The key is an admin API key (Dashboard → API Keys), sent as
    | `Authorization: MediaBrowser Token="..."`. A user's own token is refused:
    | writing an item needs elevation. Leave the URL empty to switch the whole
    | thing off — the field and the link both stay off the screens.
    */
    'jellyfin' => [
        'url' => env('JELLYFIN_URL'),
        'api_key' => env('JELLYFIN_API_KEY'),
    ],

    /*
    | Authentik SSO. base_url is the bare Authentik root (e.g.
    | https://sso.example.com — the provider appends /application/o/...
    | itself). Leave client_id empty to disable SSO entirely: the silent
    | login check and the "Continue with Authentik" link both stay inert.
    */
    'authentik' => [
        'base_url' => env('AUTHENTIK_BASE_URL'),
        'client_id' => env('AUTHENTIK_CLIENT_ID'),
        'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),
        'redirect' => env('AUTHENTIK_REDIRECT_URI'),
    ],

];
