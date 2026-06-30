<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum CardEventType: string
{
    case Moved = 'moved';
    case Priority = 'priority';
    case Assignee = 'assignee';
}