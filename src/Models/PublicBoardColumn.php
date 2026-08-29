<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class PublicBoardColumn
{
    public function __construct(
        public string $key,
        public string $title,
        public float $position,
        public ?string $color = null,
        public bool $isDone = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            title: (string) $data['title'],
            position: (float) $data['position'],
            color: ($data['color'] ?? '') !== '' ? (string) $data['color'] : null,
            isDone: (bool) ($data['isDone'] ?? false),
        );
    }
}
