<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

/**
 * One recorded delivery attempt on a WebhookSubscription (the last few,
 * most recent first, capped at 10).
 *
 * `status` is `"delivered"` or `"failed"`. Left as a plain string rather
 * than a backed enum since it is only ever read, never sent by the caller.
 */
readonly class WebhookDelivery
{
    public function __construct(
        public string $event,
        public string $status,
        public int $statusCode,
        public int $attempts,
        public ?string $error,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            event: (string) $data['event'],
            status: (string) $data['status'],
            statusCode: (int) ($data['statusCode'] ?? 0),
            attempts: (int) ($data['attempts'] ?? 0),
            error: ($data['error'] ?? null) === null ? null : (string) $data['error'],
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
        );
    }
}
