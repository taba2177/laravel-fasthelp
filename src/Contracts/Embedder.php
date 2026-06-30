<?php

namespace Tabadev\FastHelp\Contracts;

interface Embedder
{
    /** Returns a float vector, or null on failure / when unavailable. */
    public function embed(string $text): ?array;
}
