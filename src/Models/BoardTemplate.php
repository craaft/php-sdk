<?php

declare(strict_types=1);

namespace Craaft\Models;

/**
 * A named column layout a new board can start from (`GET /board-templates`).
 * Pass `key` as the `$template` argument to `ProjectsResource::create()`.
 */
readonly class BoardTemplate
{
    /** @param list<BoardTemplateColumn> $columns */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public array $columns,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $columnsRaw = $data['columns'] ?? [];
        return new self(
            key: (string) $data['key'],
            name: (string) $data['name'],
            description: (string) ($data['description'] ?? ''),
            columns: array_map([BoardTemplateColumn::class, 'fromApi'], is_array($columnsRaw) ? $columnsRaw : []),
        );
    }
}
