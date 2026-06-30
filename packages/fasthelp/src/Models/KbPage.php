<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tabadev\FastHelp\Database\Factories\KbPageFactory;

class KbPage extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_kb_pages';

    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
        'indexed_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return KbPageFactory::new();
    }
}
