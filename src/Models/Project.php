<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\BoardRole;
use Craaft\Enums\TextColor;
use Craaft\Enums\Visibility;
use Craaft\Enums\WorkspaceRole;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class Project
{
    /**
     * @param list<Column>                 $columns
     * @param list<ProjectMemberPreview>   $members
     * @param array<string, int>          $columnCounts
     */
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $name,
        public ?string $description,
        public bool $isFavorite,
        public ?string $publicToken,
        public ?string $backgroundImage,
        public ?string $backgroundColor,
        public ?string $colorScheme,
        public TextColor $textColor,
        public ?WorkspaceRole $myRole,
        public ?Visibility $visibility,
        public ?BoardRole $myBoardRole,
        public bool $canUploadAttachments,
        public ?string $workspaceName,
        public int $totalCards,
        public array $columnCounts,
        public array $columns,
        public array $members,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $colsRaw = $data['columns'] ?? [];
        $membersRaw = $data['members'] ?? [];
        $cc = $data['columnCounts'] ?? [];
        $columnCounts = [];
        if (is_array($cc)) {
            foreach ($cc as $k => $v) {
                $columnCounts[(string) $k] = (int) $v;
            }
        }

        $textColor = TextColor::tryFrom((string) ($data['textColor'] ?? 'light')) ?? TextColor::Light;
        $myRole = ($data['myRole'] ?? null) === null ? null : WorkspaceRole::tryFrom((string) $data['myRole']);
        $visibility = ($data['visibility'] ?? null) === null ? null : Visibility::tryFrom((string) $data['visibility']);
        $myBoardRole = ($data['myBoardRole'] ?? null) === null ? null : BoardRole::tryFrom((string) $data['myBoardRole']);

        return new self(
            id: (string) $data['id'],
            workspaceId: (string) $data['workspaceId'],
            name: (string) $data['name'],
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
            isFavorite: (bool) ($data['isFavorite'] ?? false),
            publicToken: ($data['publicToken'] ?? null) ?: null,
            backgroundImage: ($data['backgroundImage'] ?? null) ?: null,
            backgroundColor: ($data['backgroundColor'] ?? null) ?: null,
            colorScheme: ($data['colorScheme'] ?? null) ?: null,
            textColor: $textColor,
            myRole: $myRole,
            visibility: $visibility,
            myBoardRole: $myBoardRole,
            canUploadAttachments: (bool) ($data['canUploadAttachments'] ?? false),
            workspaceName: ($data['workspaceName'] ?? null) === null ? null : (string) $data['workspaceName'],
            totalCards: (int) ($data['totalCards'] ?? 0),
            columnCounts: $columnCounts,
            columns: array_map([Column::class, 'fromApi'], is_array($colsRaw) ? $colsRaw : []),
            members: array_map([ProjectMemberPreview::class, 'fromApi'], is_array($membersRaw) ? $membersRaw : []),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            updatedAt: Dates::parse((string) $data['updatedAt']) ?? throw new \InvalidArgumentException('missing updatedAt'),
        );
    }
}