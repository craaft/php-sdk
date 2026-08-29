<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\Priority;

/**
 * A card as it appears on a public board.
 *
 * Carries no due date, size, tags, checklist or attachment counts - the
 * public projection stops at priority and assignee.
 */
readonly class PublicBoardCard
{
    public function __construct(
        public string $id,
        public string $column,
        public float $position,
        public string $title,
        public ?string $description = null,
        public ?Priority $priority = null,
        public ?string $assignedUserId = null,
        public ?string $assignedUserName = null,
        public ?string $assignedUserAvatarUrl = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            column: (string) $data['column'],
            position: (float) $data['position'],
            title: (string) $data['title'],
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
            priority: Priority::fromApi($data['priority'] ?? null),
            assignedUserId: ($data['assignedUserId'] ?? null) === null ? null : (string) $data['assignedUserId'],
            assignedUserName: ($data['assignedUserName'] ?? null) === null ? null : (string) $data['assignedUserName'],
            assignedUserAvatarUrl: ($data['assignedUserAvatarUrl'] ?? '') !== ''
                ? (string) $data['assignedUserAvatarUrl']
                : null,
        );
    }
}
