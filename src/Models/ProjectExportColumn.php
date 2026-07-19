<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class ProjectExportColumn
{
    public function __construct(
        public string $key,
        public string $name,
        public float $position,
        public ?string $color = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            position: (float) $data['position'],
            color: ($data['color'] ?? null) ?: null,
        );
    }
}
