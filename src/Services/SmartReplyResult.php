<?php

namespace Tabadev\FastHelp\Services;

class SmartReplyResult
{
    public function __construct(
        public readonly ?string $reply,
        public readonly bool $shouldHandoff,
    ) {}

    public static function answer(string $reply): self
    {
        return new self($reply, false);
    }

    public static function handoff(): self
    {
        return new self(null, true);
    }
}
