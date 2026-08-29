<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\Priority;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class Card
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public string $column,
        public string $title,
        public float $position,
        public ?string $description,
        public ?DateTimeImmutable $dueDate,
        public ?string $assignedUserId,
        public ?string $assignedUserName,
        public ?int $size,
        public ?Priority $priority,
        public ?string $createdBy,
        public ?string $createdByName,
        public ?string $updatedBy,
        public ?string $updatedByName,
        public int $attachmentCount,
        /** Completed checklist items (denormalized count). */
        public int $checklistDone,
        /** Total checklist items (denormalized count). */
        public int $checklistTotal,
        public array $tags,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        /**
         * Whether the authenticated caller follows this card. Scoped to the
         * token's user, so it differs per caller for the same card.
         */
        public bool $following = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $rawTags = $data['tags'] ?? null;
        $tags = is_array($rawTags) ? array_map('strval', $rawTags) : [];

        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['projectId'],
            column: (string) $data['column'],
            title: (string) $data['title'],
            position: (float) $data['position'],
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
            dueDate: Dates::parse($data['dueDate'] ?? null),
            assignedUserId: ($data['assignedUserId'] ?? $data['assigneeId'] ?? null) ?: null,
            assignedUserName: ($data['assignedUserName'] ?? null) === null ? null : (string) $data['assignedUserName'],
            size: ($data['size'] ?? null) === null ? null : (int) $data['size'],
            priority: Priority::fromApi($data['priority'] ?? null),
            createdBy: ($data['createdBy'] ?? null) === null ? null : (string) $data['createdBy'],
            createdByName: ($data['createdByName'] ?? null) === null ? null : (string) $data['createdByName'],
            updatedBy: ($data['updatedBy'] ?? null) === null ? null : (string) $data['updatedBy'],
            updatedByName: ($data['updatedByName'] ?? null) === null ? null : (string) $data['updatedByName'],
            attachmentCount: (int) ($data['attachmentCount'] ?? 0),
            checklistDone: (int) ($data['checklistDone'] ?? 0),
            checklistTotal: (int) ($data['checklistTotal'] ?? 0),
            tags: $tags,
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: Dates::parse((string) $data['updatedAt']) ?? throw new \InvalidArgumentException('missing updatedAt'),
            following: (bool) ($data['following'] ?? false),
        );
    }
}
