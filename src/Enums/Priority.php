<?php

declare(strict_types=1);

namespace Craaft\Enums;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        return self::tryFrom($value);
    }
}
