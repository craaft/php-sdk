<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class ProjectExportProject
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isFavorite,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $description = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            isFavorite: (bool) ($data['isFavorite'] ?? false),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: Dates::parse((string) $data['updatedAt']) ?? throw new \InvalidArgumentException('missing updatedAt'),
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
        );
    }
}