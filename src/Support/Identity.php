<?php

namespace Tabadev\FastHelp\Support;

use Illuminate\Database\Eloquent\Model;
use Tabadev\FastHelp\Models\Visitor;

final class Identity
{
    public function __construct(
        public readonly Visitor $visitor,
        public readonly ?Model $user = null,
    ) {}

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function isUser(): bool
    {
        return ! $this->isGuest();
    }
}
