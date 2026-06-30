<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class Comment
{
    public function __construct(
        public string $id,
        public string $cardId,
        public string $authorId,
        public string $body,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $createdAt = Dates::parse((string) $data['createdAt']);
        $updatedAt = Dates::parse((string) ($data['updatedAt'] ?? $data['createdAt']));

        return new self(
            id: (string) $data['id'],
            cardId: (string) $data['cardId'],
            authorId: (string) $data['authorId'],
            body: (string) $data['body'],
            createdAt: $createdAt ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: $updatedAt ?? throw new \InvalidArgumentException('missing updatedAt'),
        );
    }
}