<?php

namespace Tabadev\FastHelp\Enums;

enum MessageSender: string
{
    case Client = 'client';
    case Agent = 'agent';
    case Bot = 'bot';
    case System = 'system';
}
