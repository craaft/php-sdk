<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\BoardRole;
use Craaft\Util\Dates;
use DateTimeImmutable;

/** Grant returned by add_member / update_member. */
readonly class BoardMemberGrant
{
    public function __construct(
        public string $userId,
        public BoardRole $role,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $role = BoardRole::tryFrom((string) $data['role']);
        if ($role === null) {
            throw new \InvalidArgumentException("unknown board role: {$data['role']}");
        }
        $createdAt = Dates::parse((string) $data['createdAt']);
        return new self(
            userId: (string) $data['userId'],
            role: $role,
            createdAt: $createdAt ?? throw new \InvalidArgumentException('missing createdAt'),
        );
    }
}