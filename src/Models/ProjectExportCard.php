<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\Priority;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class ProjectExportCard
{
    /**
     * @param list<ProjectExportComment>    $comments
     * @param list<ProjectExportAttachment> $attachments
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $columnKey,
        public float $position,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $description = null,
        public ?DateTimeImmutable $dueDate = null,
        public ?Priority $priority = null,
        public ?int $size = null,
        public ?ProjectExportUser $assignee = null,
        public ?ProjectExportUser $createdBy = null,
        public array $comments = [],
        public array $attachments = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $assigneeRaw = $data['assignee'] ?? null;
        $createdByRaw = $data['createdBy'] ?? null;
        $commentsRaw = $data['comments'] ?? [];
        $attachmentsRaw = $data['attachments'] ?? [];

        return new self(
            id: (string) $data['id'],
            title: (string) $data['title'],
            columnKey: (string) $data['columnKey'],
            position: (float) $data['position'],
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: Dates::parse((string) $data['updatedAt']) ?? throw new \InvalidArgumentException('missing updatedAt'),
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
            dueDate: Dates::parse($data['dueDate'] ?? null),
            priority: Priority::fromApi($data['priority'] ?? null),
            size: ($data['size'] ?? null) === null ? null : (int) $data['size'],
            assignee: is_array($assigneeRaw) ? ProjectExportUser::fromApi($assigneeRaw) : null,
            createdBy: is_array($createdByRaw) ? ProjectExportUser::fromApi($createdByRaw) : null,
            comments: array_map([ProjectExportComment::class, 'fromApi'], is_array($commentsRaw) ? $commentsRaw : []),
            attachments: array_map([ProjectExportAttachment::class, 'fromApi'], is_array($attachmentsRaw) ? $attachmentsRaw : []),
        );
    }
}
