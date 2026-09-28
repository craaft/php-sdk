<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\Priority;
use Craaft\Util\Dates;
use DateTimeImmutable;

/**
 * Lightweight card preview: the shared base of `SearchResult` (GET
 * /search) and `UpcomingCard` (GET /cards/upcoming, and the `due` bucket of
 * GET /cards/focus).
 *
 * The two endpoints return genuinely different shapes - search never sends
 * `dueDate` / `assignedUserId` / `assignedUserName` / `priority`, and
 * upcoming never sends `description` / `updatedAt` / `archived` - but this
 * class carries every field so existing code written against one shared
 * `CardSummary` type keeps working. Prefer the specific subtype (which
 * `CardsResource::search()` and `CardsResource::upcoming()` now return) for
 * an accurate picture of what is actually populated; both subtypes are
 * `instanceof CardSummary`.
 */
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

    /**
     * @param array<string, mixed> $data
     *
     * Uses `new static()` (not `new self()`) so `SearchResult::fromApi()`
     * and `UpcomingCard::fromApi()` inherit this method and still
     * construct their own subtype.
     */
    public static function fromApi(array $data): static
    {
        return new static(
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
