<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire as LivewireTester;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Livewire\AgentChat;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\KbPage;

function makeAgentChatPreviewUser(): Authenticatable
{
    test()->loadMigrationsFrom(
        __DIR__.'/../../../vendor/orchestra/testbench-core/laravel/migrations'
    );

    $user = new Authenticatable;
    $user->forceFill([
        'name' => 'Preview Agent',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

it('attaches previews from a seeded KbPage url in agent chat messages', function () {
    $agent = makeAgentChatPreviewUser();
    $this->actingAs($agent);

    KbPage::factory()->create([
        'url' => 'https://help.example.com/faq',
        'title' => 'FAQ Page',
        'description' => 'Frequently asked questions.',
        'og_image' => null,
    ]);

    $conversation = Conversation::factory()->create();
    $conversation->messages()->create([
        'sender_type' => MessageSender::Client,
        'sender_id' => null,
        'body' => 'See https://help.example.com/faq for answers.',
    ]);

    $component = LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid]);

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1);

    $previews = $messages[0]['previews'];

    expect($previews)->not->toBeEmpty()
        ->and($previews[0]['title'])->toBe('FAQ Page')
        ->and($previews[0]['internal'])->toBeTrue();
});

it('attaches empty previews array when agent chat message has no URLs', function () {
    $agent = makeAgentChatPreviewUser();
    $this->actingAs($agent);

    $conversation = Conversation::factory()->create();
    $conversation->messages()->create([
        'sender_type' => MessageSender::Client,
        'sender_id' => null,
        'body' => 'Plain text, no links here.',
    ]);

    $component = LivewireTester::test(AgentChat::class, ['uuid' => $conversation->uuid]);

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['previews'])->toBe([]);
});
