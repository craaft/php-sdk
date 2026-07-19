<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum BoardMemberSource: string
{
    case Explicit = 'explicit';
    case WorkspaceAdmin = 'workspace-admin';
    case WorkspaceVisible = 'workspace-visible';
}
