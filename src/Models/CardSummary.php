<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\Priority;
use Craaft\Util\Dates;
use DateTimeImmutable;

/** Lightweight card preview returned by /cards/upcoming and /search. */
readonly class CardSummary
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $projectName,
        public string $columnKey,
        public string $columnTitle,
        public string $title,
        public ?string $description = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?string $assignedUserId = null,
        public ?string $assignedUserName = null,
        public ?Priority $priority = null,
        public ?DateTimeImmutable $updatedAt = null,
        public bool $archived = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['projectId'],
            projectName: (string) ($data['projectName'] ?? ''),
            columnKey: (string) ($data['columnKey'] ?? $data['column'] ?? ''),
            columnTitle: (string) ($data['columnTitle'] ?? ''),
            title: (string) $data['title'],
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
            dueDate: Dates::parse($data['dueDate'] ?? null),
            assignedUserId: ($data['assignedUserId'] ?? $data['assigneeId'] ?? null) ?: null,
            assignedUserName: ($data['assignedUserName'] ?? null) === null ? null : (string) $data['assignedUserName'],
            priority: Priority::fromApi($data['priority'] ?? null),
            updatedAt: Dates::parse($data['updatedAt'] ?? null),
            archived: (bool) ($data['archived'] ?? false),
        );
    }
}
