<?php

use Illuminate\Support\Facades\Event;
use Tabadev\FastHelp\Enums\AgentPresence;
use Tabadev\FastHelp\Events\AgentPresenceChanged;
use Tabadev\FastHelp\Models\AgentStatus;
use Tabadev\FastHelp\Services\PresenceService;

it('marks a user online, upserting the status row and dispatching the event', function () {
    Event::fake([AgentPresenceChanged::class]);

    $service = app(PresenceService::class);
    $service->markOnline(101);

    $status = AgentStatus::where('user_id', 101)->first();

    expect($status)->not->toBeNull()
        ->and($status->status)->toBe(AgentPresence::Online)
        ->and($status->last_seen_at)->not->toBeNull();

    Event::assertDispatched(AgentPresenceChanged::class);

    // Calling again should update, not duplicate, the row.
    $service->markOnline(101);
    expect(AgentStatus::where('user_id', 101)->count())->toBe(1);
});

it('marks a user away', function () {
    Event::fake([AgentPresenceChanged::class]);

    AgentStatus::factory()->create(['user_id' => 202, 'status' => AgentPresence::Online]);

    $service = app(PresenceService::class);
    $service->markAway(202);

    expect(AgentStatus::where('user_id', 202)->first()->status)->toBe(AgentPresence::Away);

    Event::assertDispatched(AgentPresenceChanged::class);
});

it('marks a user offline', function () {
    Event::fake([AgentPresenceChanged::class]);

    AgentStatus::factory()->create(['user_id' => 303, 'status' => AgentPresence::Online]);

    $service = app(PresenceService::class);
    $service->markOffline(303);

    expect(AgentStatus::where('user_id', 303)->first()->status)->toBe(AgentPresence::Offline);

    Event::assertDispatched(AgentPresenceChanged::class);
});

it('lists only online agents within the staleness window', function () {
    config(['fasthelp.presence.stale_after' => 60]);

    $fresh = AgentStatus::factory()->create([
        'status' => AgentPresence::Online,
        'last_seen_at' => now(),
    ]);

    $stale = AgentStatus::factory()->create([
        'status' => AgentPresence::Online,
        'last_seen_at' => now()->subSeconds(120),
    ]);

    $away = AgentStatus::factory()->create([
        'status' => AgentPresence::Away,
        'last_seen_at' => now(),
    ]);

    $offline = AgentStatus::factory()->create([
        'status' => AgentPresence::Offline,
        'last_seen_at' => now(),
    ]);

    $service = app(PresenceService::class);
    $online = $service->onlineAgents();

    expect($online->pluck('id'))->toContain($fresh->id)
        ->and($online->pluck('id'))->not->toContain($stale->id)
        ->and($online->pluck('id'))->not->toContain($away->id)
        ->and($online->pluck('id'))->not->toContain($offline->id);
});

it('counts only the online agents within the staleness window', function () {
    config(['fasthelp.presence.stale_after' => 60]);

    AgentStatus::factory()->create(['status' => AgentPresence::Online, 'last_seen_at' => now()]);
    AgentStatus::factory()->create(['status' => AgentPresence::Online, 'last_seen_at' => now()]);
    AgentStatus::factory()->create(['status' => AgentPresence::Online, 'last_seen_at' => now()->subSeconds(120)]);
    AgentStatus::factory()->create(['status' => AgentPresence::Away, 'last_seen_at' => now()]);

    $service = app(PresenceService::class);

    expect($service->onlineCount())->toBe(2);
});
