<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('exposes all top-level config sections', function () {
    foreach (['user_model', 'auth_guard', 'cookie', 'agents', 'presence', 'broadcasting', 'widget', 'ai', 'routes'] as $key) {
        expect(config()->has("fasthelp.$key"))->toBeTrue("missing fasthelp.$key");
    }
});

it('does not throw when the AI key is unset', function () {
    config()->set('fasthelp.ai.api_key', null);
    expect(config('fasthelp.ai.enabled'))->toBeBool();
});

it('registers package migrations so tables exist', function () {
    expect(Schema::hasTable('fasthelp_conversations'))->toBeTrue()
        ->and(Schema::hasTable('fasthelp_agents'))->toBeTrue();
});

it('declares the documented publish tags', function () {
    $groups = ServiceProvider::$publishGroups;
    foreach (['fasthelp-config', 'fasthelp-migrations', 'fasthelp-views', 'fasthelp-translations', 'fasthelp-assets'] as $tag) {
        expect($groups)->toHaveKey($tag);
    }
});
