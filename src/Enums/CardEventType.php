<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum CardEventType: string
{
    case Moved = 'moved';
    /** Moved to another board; values are "<project id>:<column key>". */
    case MovedBoard = 'moved_board';
    case Priority = 'priority';
    case Assignee = 'assignee';
}
