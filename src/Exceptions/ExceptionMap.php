<?php

declare(strict_types=1);

namespace Craaft\Exceptions;

/**
 * Maps HTTP status codes to the most specific CraaftApiError subclass.
 */
final class ExceptionMap
{
    /**
     * Map an HTTP status code to the most specific CraaftApiError subclass.
     */
    public static function classForStatus(int $status): string
    {
        return match (true) {
            400 === $status,
            413 === $status,
            422 === $status => ValidationError::class,
            401 === $status => AuthenticationError::class,
            402 === $status => PlanLimitError::class,
            403 === $status => PermissionError::class,
            404 === $status => NotFoundError::class,
            409 === $status => ConflictError::class,
            429 === $status => RateLimitError::class,
            $status >= 500 && $status < 600 => ServerError::class,
            default => CraaftApiError::class,
        };
    }
}
