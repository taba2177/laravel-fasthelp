<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeminiFeedbackLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_query',
        'gemini_response',
        'context_chunks',
        'feedback_status',
        'admin_feedback',
    ];

    protected $casts = [
        'gemini_response' => 'array',
        'context_chunks' => 'array',
    ];
}
