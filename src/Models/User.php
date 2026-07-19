<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class User
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public string $username,
        public ?string $avatarUrl,
        public bool $hasPassword,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            email: (string) $data['email'],
            name: (string) $data['name'],
            username: (string) $data['username'],
            avatarUrl: ($data['avatarUrl'] ?? null) ?: null,
            hasPassword: (bool) ($data['hasPassword'] ?? false),
        );
    }
}
