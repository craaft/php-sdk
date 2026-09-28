<?php

declare(strict_types=1);

namespace Craaft\Models;

/**
 * Response shape of POST /columns/{id}/archive: the count of live cards
 * archived and their ids, so a caller can offer an undo via
 * `CardsResource::restore()` per id.
 */
readonly class ColumnArchiveResult
{
    /** @param list<string> $ids */
    public function __construct(
        public int $archived,
        public array $ids,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $idsRaw = $data['ids'] ?? [];
        return new self(
            archived: (int) ($data['archived'] ?? 0),
            ids: array_map('strval', is_array($idsRaw) ? $idsRaw : []),
        );
    }
}
