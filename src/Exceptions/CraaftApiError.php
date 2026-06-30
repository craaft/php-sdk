<?php

declare(strict_types=1);

namespace Craaft\Exceptions;

/**
 * An error response returned by the Craaft API.
 */
class CraaftApiError extends CraaftError
{
    /**
     * @param string      $message      Parsed human-readable error message.
     * @param int         $statusCode   HTTP status code.
     * @param string      $responseBody Raw response body (bytes).
     * @param string|null $requestId    x-request-id header if present.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $responseBody,
        public readonly ?string $requestId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
