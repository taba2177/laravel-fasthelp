<?php

use App\Models\User;

return [
    'user_model' => env('FASTHELP_USER_MODEL', User::class),

    'auth_guard' => env('FASTHELP_AUTH_GUARD', null),

    'cookie' => env('FASTHELP_COOKIE', 'fasthelp_visitor'),
];
