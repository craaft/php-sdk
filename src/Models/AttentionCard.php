<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\Priority;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class AttentionCard
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $projectName,
        public string $columnKey,
        public string $columnTitle,
        public string $title,
        public DateTimeImmutable $updatedAt,
        public ?string $assignedUserId,
        public ?string $assignedUserName,
        public ?Priority $priority,
        public bool $staleInProgress,
        public bool $highPriority,
        public bool $idleByMe,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $updatedAt = Dates::parse($data['updatedAt'] ?? null);
        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['projectId'],
            projectName: (string) ($data['projectName'] ?? ''),
            columnKey: (string) ($data['columnKey'] ?? ''),
            columnTitle: (string) ($data['columnTitle'] ?? ''),
            title: (string) $data['title'],
            updatedAt: $updatedAt ?? throw new \InvalidArgumentException('missing updatedAt'),
            assignedUserId: ($data['assignedUserId'] ?? null) === null ? null : (string) $data['assignedUserId'],
            assignedUserName: ($data['assignedUserName'] ?? null) === null ? null : (string) $data['assignedUserName'],
            priority: Priority::fromApi($data['priority'] ?? null),
            staleInProgress: (bool) ($data['staleInProgress'] ?? false),
            highPriority: (bool) ($data['highPriority'] ?? false),
            idleByMe: (bool) ($data['idleByMe'] ?? false),
        );
    }
}
