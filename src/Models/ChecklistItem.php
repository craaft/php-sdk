<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class ChecklistItem
{
    public function __construct(
        public string $id,
        public string $cardId,
        public string $text,
        public bool $done,
        public float $position,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            cardId: (string) $data['cardId'],
            text: (string) $data['text'],
            done: (bool) ($data['done'] ?? false),
            position: (float) ($data['position'] ?? 0.0),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: Dates::parse((string) $data['updatedAt']) ?? throw new \InvalidArgumentException('missing updatedAt'),
        );
    }
}
