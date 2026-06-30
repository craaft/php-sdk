<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\WorkspaceRole;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class WorkspaceMember
{
    /**
     * @param list<BoardAccess>|null $boardAccess Null when omitted (owner/admin rows or other-user reads).
     */
    public function __construct(
        public string $userId,
        public string $email,
        public string $name,
        public WorkspaceRole $role,
        public string $avatarUrl,
        public DateTimeImmutable $joinedAt,
        public ?array $boardAccess = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $role = WorkspaceRole::tryFrom((string) $data['role']);
        if ($role === null) {
            throw new \InvalidArgumentException("unknown workspace role: {$data['role']}");
        }
        $joinedAt = Dates::parse((string) $data['joinedAt']);

        $boardAccess = null;
        if (array_key_exists('boardAccess', $data)) {
            $raw = $data['boardAccess'];
            $boardAccess = is_array($raw)
                ? array_map([BoardAccess::class, 'fromApi'], $raw)
                : [];
        }

        return new self(
            userId: (string) $data['userId'],
            email: (string) $data['email'],
            name: (string) $data['name'],
            role: $role,
            avatarUrl: (string) ($data['avatarUrl'] ?? ''),
            joinedAt: $joinedAt ?? throw new \InvalidArgumentException('missing joinedAt'),
            boardAccess: $boardAccess,
        );
    }
}