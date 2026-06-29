<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'source_type',
        'source_identifier',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];
}
