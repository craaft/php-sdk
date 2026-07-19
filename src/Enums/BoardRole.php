<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum BoardRole: string
{
    case Admin = 'admin';
    case Contributor = 'contributor';
}
