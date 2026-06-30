<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum HygieneType: string
{
    case Ghosts = 'ghosts';
    case Stuck = 'stuck';
    case MineNoDate = 'mine_no_date';
}