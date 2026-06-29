<?php

namespace Tabadev\FastHelp\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tabadev\FastHelp\Database\Factories\AgentStatusFactory;
use Tabadev\FastHelp\Enums\AgentPresence;

class AgentStatus extends Model
{
    use HasFactory;

    protected $table = 'fasthelp_agent_statuses';

    protected $guarded = [];

    protected $casts = [
        'status' => AgentPresence::class,
        'last_seen_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return AgentStatusFactory::new();
    }
}
