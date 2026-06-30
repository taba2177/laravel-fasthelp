<?php

it('has a kb section in the fasthelp config', function () {
    $kb = config('fasthelp.kb');

    expect($kb)->toBeArray();
});

it('kb config has all required keys', function () {
    $kb = config('fasthelp.kb');

    expect($kb)->toHaveKeys([
        'enabled',
        'base_url',
        'max_pages',
        'same_domain_only',
        'respect_robots',
        'embedding_model',
        'retrieve_top_k',
        'min_similarity',
        'user_agent',
        'schedule',
    ]);
});

it('kb enabled defaults to a boolean false', function () {
    $enabled = config('fasthelp.kb.enabled');

    expect($enabled)->toBeBool()->and($enabled)->toBeFalse();
});

it('kb max_pages defaults to an integer', function () {
    expect(config('fasthelp.kb.max_pages'))->toBeInt();
});

it('kb retrieve_top_k defaults to an integer', function () {
    expect(config('fasthelp.kb.retrieve_top_k'))->toBeInt();
});

it('kb min_similarity defaults to a float', function () {
    expect(config('fasthelp.kb.min_similarity'))->toBeFloat();
});

it('kb schedule defaults to off', function () {
    expect(config('fasthelp.kb.schedule'))->toBe('off');
});

it('existing fasthelp config keys are still intact', function () {
    expect(config('fasthelp'))->toHaveKeys([
        'user_model',
        'auth_guard',
        'cookie',
        'agents',
        'presence',
        'broadcasting',
        'widget',
        'ai',
        'routes',
    ]);
});
