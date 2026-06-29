<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatHistory extends Model
{
    use HasFactory;

    protected $table = 'chat_histories';

    protected $fillable = [
        'user_query_hash',
        'user_query',
        'bot_response',
        'expires_at',
    ];

    protected $casts = [
        'bot_response' => 'array',
        'expires_at' => 'datetime',
    ];
}
