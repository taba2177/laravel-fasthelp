<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tabadev\FastHelp\Database\Factories\LinkPreviewFactory;

class LinkPreview extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_link_previews';

    protected $guarded = [];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return LinkPreviewFactory::new();
    }
}
