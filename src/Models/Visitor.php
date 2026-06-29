<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_visitors';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'last_seen_at' => 'datetime',
    ];
}
