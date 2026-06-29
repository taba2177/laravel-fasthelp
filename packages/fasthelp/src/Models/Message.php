<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tabadev\FastHelp\Enums\MessageSender;

class Message extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_messages';

    protected $guarded = [];

    protected $casts = [
        'sender_type' => MessageSender::class,
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
