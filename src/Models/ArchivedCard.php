<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

/**
 * One row from GET /projects/{id}/cards/archived: the same board-list card
 * shape as `ProjectsResource::listCards()` (no description body) plus the
 * moment it was archived.
 */
readonly class ArchivedCard
{
    public function __construct(
        public Card $card,
        public DateTimeImmutable $archivedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            card: Card::fromApi($data),
            archivedAt: Dates::parse((string) $data['archivedAt']) ?? throw new \InvalidArgumentException('missing archivedAt'),
        );
    }
}
