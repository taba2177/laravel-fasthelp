<?php

use Tabadev\FastHelp\Support\Vectors;

it('returns approximately 1.0 for identical vectors', function () {
    $v = [0.1, 0.5, 0.9, 0.3];
    expect(Vectors::cosine($v, $v))->toBeGreaterThan(0.9999);
});

it('returns 0.0 for orthogonal vectors', function () {
    expect(Vectors::cosine([1.0, 0.0], [0.0, 1.0]))->toBe(0.0);
});

it('returns 0.0 for empty vectors', function () {
    expect(Vectors::cosine([], []))->toBe(0.0);
});

it('returns 0.0 when vector lengths differ', function () {
    expect(Vectors::cosine([1.0, 2.0], [1.0, 2.0, 3.0]))->toBe(0.0);
});

it('returns 0.0 when a vector has zero magnitude', function () {
    expect(Vectors::cosine([0.0, 0.0], [1.0, 2.0]))->toBe(0.0);
});
