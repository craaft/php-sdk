<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class ProjectExportUser
{
    public function __construct(
        public string $username,
        public string $name,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            username: (string) $data['username'],
            name: (string) $data['name'],
        );
    }
}
