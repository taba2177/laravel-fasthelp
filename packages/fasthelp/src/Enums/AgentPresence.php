<?php

namespace Tabadev\FastHelp\Enums;

enum AgentPresence: string
{
    case Online = 'online';
    case Away = 'away';
    case Offline = 'offline';
}
