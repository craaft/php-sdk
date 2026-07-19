<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class Attachment
{
    public function __construct(
        public string $id,
        public string $cardId,
        public string $name,
        public int $size,
        public string $contentType,
        public string $uploadedBy,
        public string $uploadedByName,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $createdAt = Dates::parse((string) $data['createdAt']);
        return new self(
            id: (string) $data['id'],
            cardId: (string) $data['cardId'],
            name: (string) $data['name'],
            size: (int) $data['size'],
            contentType: (string) $data['contentType'],
            uploadedBy: (string) $data['uploadedBy'],
            uploadedByName: (string) ($data['uploadedByName'] ?? ''),
            createdAt: $createdAt ?? throw new \InvalidArgumentException('missing createdAt'),
        );
    }
}
