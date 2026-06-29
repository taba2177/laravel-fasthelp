<?php

use Illuminate\Database\QueryException;
use Tabadev\FastHelp\Models\Visitor;

it('creates a visitor with a unique token', function () {
    $visitor = Visitor::create(['token' => 'token-one']);

    expect($visitor->token)->toBe('token-one');

    expect(fn () => Visitor::create(['token' => 'token-one']))
        ->toThrow(QueryException::class);
});
