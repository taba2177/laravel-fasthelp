<?php

namespace Tabadev\FastHelp\Services;

use Illuminate\Support\Collection;
use Tabadev\FastHelp\Enums\AgentPresence;
use Tabadev\FastHelp\Events\AgentPresenceChanged;
use Tabadev\FastHelp\Models\AgentStatus;
use Tabadev\FastHelp\Support\Settings;

class PresenceService
{
    public function markOnline(int $userId): void
    {
        $this->upsert($userId, AgentPresence::Online);
    }

    public function markAway(int $userId): void
    {
        $this->upsert($userId, AgentPresence::Away);
    }

    public function markOffline(int $userId): void
    {
        $this->upsert($userId, AgentPresence::Offline);
    }

    /**
     * @return Collection<int, AgentStatus>
     */
    public function onlineAgents(): Collection
    {
        return AgentStatus::query()
            ->where('status', AgentPresence::Online)
            ->where('last_seen_at', '>=', now()->subSeconds($this->staleAfter()))
            ->get();
    }

    public function onlineCount(): int
    {
        return $this->onlineAgents()->count();
    }

    private function upsert(int $userId, AgentPresence $status): void
    {
        AgentStatus::updateOrCreate(
            ['user_id' => $userId],
            ['status' => $status, 'last_seen_at' => now()]
        );

        event(new AgentPresenceChanged($this->onlineCount()));
    }

    private function staleAfter(): int
    {
        return (int) app(Settings::class)->get('presence.stale_after', 60);
    }
}
