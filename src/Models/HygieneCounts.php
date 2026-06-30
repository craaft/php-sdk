<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class HygieneCounts
{
    public function __construct(
        public int $ghosts,
        public int $longInProgress,
        public int $mineNoDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            ghosts: (int) ($data['ghosts'] ?? 0),
            longInProgress: (int) ($data['longInProgress'] ?? 0),
            mineNoDate: (int) ($data['mineNoDate'] ?? 0),
        );
    }
}