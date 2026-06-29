<?php

namespace Tabadev\FastHelp\Contracts;

use Tabadev\FastHelp\Models\Conversation;
use Tabadev\FastHelp\Services\SmartReplyResult;

interface SmartReply
{
    public function reply(Conversation $conversation, string $message): SmartReplyResult;
}
