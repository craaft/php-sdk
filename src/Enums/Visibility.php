<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum Visibility: string
{
    case Private = 'private';
    case Workspace = 'workspace';
}
