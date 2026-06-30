<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class ProjectMemberPreview
{
    public function __construct(
        public string $id,
        public string $name,
        public string $avatarUrl,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            avatarUrl: (string) ($data['avatarUrl'] ?? ''),
        );
    }
}