<?php

declare(strict_types=1);

namespace Craaft\Exceptions;

/**
 * 429 Too Many Requests.
 *
 * Exposes the parsed Retry-After hint (seconds, possibly fractional) when the
 * server supplies one; null otherwise.
 */
class RateLimitError extends CraaftApiError
{
    public function __construct(
        string $message,
        int $statusCode,
        string $responseBody,
        ?string $requestId,
        public readonly ?float $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $responseBody, $requestId, $previous);
    }
}
