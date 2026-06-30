<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class Column
{
    public function __construct(
        public string $id,
        public string $key,
        public string $title,
        public string $color,
        public float $position,
        public bool $isDone,
        public ?int $cardLimit,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            key: (string) $data['key'],
            title: (string) $data['title'],
            color: (string) ($data['color'] ?? ''),
            position: (float) $data['position'],
            isDone: (bool) ($data['isDone'] ?? false),
            cardLimit: ($data['cardLimit'] ?? null) === null ? null : (int) $data['cardLimit'],
        );
    }
}