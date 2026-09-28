<?php

declare(strict_types=1);

namespace Craaft\Models;

/** One column a board template seeds (part of BoardTemplate). */
readonly class BoardTemplateColumn
{
    public function __construct(
        public string $title,
        public string $color,
        public bool $isDone,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            title: (string) $data['title'],
            color: (string) ($data['color'] ?? ''),
            isDone: (bool) ($data['isDone'] ?? false),
        );
    }
}
