<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\BoardRole;

readonly class InvitationGrant
{
    public function __construct(
        public string $projectId,
        public string $name,
        public BoardRole $role,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $role = BoardRole::tryFrom((string) $data['role']);
        if ($role === null) {
            throw new \InvalidArgumentException("unknown board role: {$data['role']}");
        }
        return new self(
            projectId: (string) $data['projectId'],
            name: (string) $data['name'],
            role: $role,
        );
    }
}
