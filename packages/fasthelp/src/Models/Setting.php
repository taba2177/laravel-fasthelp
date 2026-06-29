<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'fasthelp_settings';

    protected $guarded = [];

    protected $casts = [
        'value' => 'json',
    ];
}
