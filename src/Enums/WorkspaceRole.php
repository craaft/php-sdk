<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
