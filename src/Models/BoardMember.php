<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\BoardMemberSource;
use Craaft\Enums\BoardRole;
use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class BoardMember
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public string $username,
        public string $avatarUrl,
        public BoardRole $role,
        public BoardMemberSource $source,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $role = BoardRole::tryFrom((string) $data['role']);
        if ($role === null) {
            throw new \InvalidArgumentException("unknown board role: {$data['role']}");
        }
        $source = BoardMemberSource::tryFrom((string) $data['source']);
        if ($source === null) {
            throw new \InvalidArgumentException("unknown board member source: {$data['source']}");
        }
        $createdAt = Dates::parse((string) $data['createdAt']);

        return new self(
            userId: (string) $data['userId'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            username: (string) ($data['username'] ?? ''),
            avatarUrl: (string) ($data['avatarUrl'] ?? ''),
            role: $role,
            source: $source,
            createdAt: $createdAt ?? throw new \InvalidArgumentException('missing createdAt'),
        );
    }
}