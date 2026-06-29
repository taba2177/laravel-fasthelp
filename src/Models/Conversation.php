<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Tabadev\FastHelp\Database\Factories\ConversationFactory;
use Tabadev\FastHelp\Enums\ConversationStatus;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_conversations';

    protected $guarded = [];

    protected $casts = [
        'status' => ConversationStatus::class,
        'meta' => 'array',
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return ConversationFactory::new();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
