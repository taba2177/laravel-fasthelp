<?php

namespace Tabadev\FastHelp\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Resolved = 'resolved';
}
