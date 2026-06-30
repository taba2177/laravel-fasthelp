<?php

use Livewire\Livewire as LivewireTester;
use Tabadev\FastHelp\Enums\MessageSender;
use Tabadev\FastHelp\Livewire\Widget;
use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Models\KbPage;

it('attaches previews from a seeded KbPage url in widget messages', function () {
    $page = KbPage::factory()->create([
        'url' => 'https://docs.example.com/getting-started',
        'title' => 'Getting Started Guide',
        'description' => 'A comprehensive guide to getting started.',
        'og_image' => 'https://docs.example.com/og.png',
    ]);

    $conversation = Conversation::factory()->create();
    $conversation->messages()->create([
        'sender_type' => MessageSender::Client,
        'sender_id' => null,
        'body' => 'Check out https://docs.example.com/getting-started for help.',
    ]);

    $component = LivewireTester::test(Widget::class)
        ->set('conversationUuid', $conversation->uuid)
        ->call('loadMessages');

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1);

    $previews = $messages[0]['previews'];

    expect($previews)->not->toBeEmpty()
        ->and($previews[0]['title'])->toBe('Getting Started Guide')
        ->and($previews[0]['internal'])->toBeTrue();
});

it('attaches empty previews array when no URLs in body', function () {
    $conversation = Conversation::factory()->create();
    $conversation->messages()->create([
        'sender_type' => MessageSender::Client,
        'sender_id' => null,
        'body' => 'Hello, I need some help please.',
    ]);

    $component = LivewireTester::test(Widget::class)
        ->set('conversationUuid', $conversation->uuid)
        ->call('loadMessages');

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['previews'])->toBe([]);
});
