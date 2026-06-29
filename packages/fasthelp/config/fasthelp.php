<?php

use App\Models\User;

return [
    'user_model' => env('FASTHELP_USER_MODEL', User::class),

    'auth_guard' => env('FASTHELP_AUTH_GUARD', null),

    'cookie' => env('FASTHELP_COOKIE', 'fasthelp_visitor'),

    'broadcasting' => [
        'channel_prefix' => 'fasthelp',
    ],

    'agents' => [
        'resolver' => null,
    ],

    'presence' => [
        'stale_after' => 60,
    ],
];
