<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class Invitation
{
    /** @param list<InvitationGrant> $boardGrants */
    public function __construct(
        public string $id,
        public string $email,
        public string $role,
        public string $invitedBy,
        public string $invitedByName,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public array $boardGrants,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $grantsRaw = $data['boardGrants'] ?? [];
        return new self(
            id: (string) $data['id'],
            email: (string) $data['email'],
            role: (string) $data['role'],
            invitedBy: (string) $data['invitedBy'],
            invitedByName: (string) ($data['invitedByName'] ?? ''),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            expiresAt: Dates::parse((string) $data['expiresAt']) ?? throw new \InvalidArgumentException('missing expiresAt'),
            boardGrants: array_map([InvitationGrant::class, 'fromApi'], is_array($grantsRaw) ? $grantsRaw : []),
        );
    }
}