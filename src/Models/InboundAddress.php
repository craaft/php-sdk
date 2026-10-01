<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

/** A board's email-to-card intake address. */
readonly class InboundAddress
{
    public function __construct(
        public string $email,
        public string $token,
        public ?string $targetColumn,
        public bool $active,
        public bool $aiEnrich,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            email: (string) $data['email'],
            token: (string) $data['token'],
            targetColumn: ($data['targetColumn'] ?? null) === null ? null : (string) $data['targetColumn'],
            active: (bool) ($data['active'] ?? false),
            aiEnrich: (bool) ($data['aiEnrich'] ?? false),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
        );
    }
}
