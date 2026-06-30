<?php

declare(strict_types=1);

namespace Craaft\Http;

/**
 * Result of a single HTTP attempt.
 */
final class HttpAttempt
{
    public function __construct(
        public readonly int $status,
        public readonly string $headers,
        public readonly string $body,
    ) {}
}
