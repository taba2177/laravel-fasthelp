<?php

use App\Models\User;

return [
    'user_model' => env('FASTHELP_USER_MODEL', User::class),
];
