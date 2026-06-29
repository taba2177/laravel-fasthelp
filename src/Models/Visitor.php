<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tabadev\FastHelp\Database\Factories\VisitorFactory;

class Visitor extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_visitors';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'last_seen_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return VisitorFactory::new();
    }
}
