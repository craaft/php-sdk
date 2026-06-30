<?php

declare(strict_types=1);

namespace Craaft\Util;

/**
 * URL path-segment helpers.
 *
 * IDs are URL-encoded so untrusted values cannot inject path or query
 * characters, and empty strings are rejected as a likely programming bug.
 */
final class Id
{
    public static function segment(string $value): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException('resource ID must be a non-empty string');
        }
        return rawurlencode($value);
    }
}