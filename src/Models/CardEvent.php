<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\CardEventType;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class CardEvent
{
    public function __construct(
        public string $id,
        public CardEventType $type,
        public DateTimeImmutable $createdAt,
        public ?string $fromValue = null,
        public ?string $toValue = null,
        public ?string $fromName = null,
        public ?string $toName = null,
        public ?string $actorId = null,
        public ?string $actorName = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $type = CardEventType::tryFrom((string) $data['type']);
        if ($type === null) {
            throw new \InvalidArgumentException("unknown card event type: " . ($data['type'] ?? ''));
        }
        $createdAt = Dates::parse((string) $data['createdAt']);

        return new self(
            id: (string) $data['id'],
            type: $type,
            createdAt: $createdAt ?? throw new \InvalidArgumentException('missing createdAt'),
            fromValue: ($data['fromValue'] ?? null) === null ? null : (string) $data['fromValue'],
            toValue: ($data['toValue'] ?? null) === null ? null : (string) $data['toValue'],
            fromName: ($data['fromName'] ?? null) === null ? null : (string) $data['fromName'],
            toName: ($data['toName'] ?? null) === null ? null : (string) $data['toName'],
            actorId: ($data['actorId'] ?? null) === null ? null : (string) $data['actorId'],
            actorName: ($data['actorName'] ?? null) === null ? null : (string) $data['actorName'],
        );
    }
}