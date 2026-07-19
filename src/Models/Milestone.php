<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class Milestone
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $name,
        /** Plain calendar date in `YYYY-MM-DD` form (no time component). */
        public string $dueOn,
        public ?DateTimeImmutable $achievedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['projectId'],
            name: (string) $data['name'],
            dueOn: (string) $data['dueOn'],
            achievedAt: Dates::parse($data['achievedAt'] ?? null),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: Dates::parse((string) $data['updatedAt']) ?? throw new \InvalidArgumentException('missing updatedAt'),
        );
    }
}
