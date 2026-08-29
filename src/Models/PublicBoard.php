<?php

declare(strict_types=1);

namespace Craaft\Models;

readonly class PublicBoard
{
    /**
     * @param list<PublicBoardColumn> $columns
     * @param list<PublicBoardCard> $cards
     */
    public function __construct(
        public PublicBoardProject $project,
        public array $columns,
        public array $cards,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $project = $data['project'] ?? [];
        $columns = $data['columns'] ?? [];
        $cards = $data['cards'] ?? [];

        return new self(
            project: PublicBoardProject::fromApi(is_array($project) ? $project : []),
            columns: array_map(
                [PublicBoardColumn::class, 'fromApi'],
                is_array($columns) ? array_values($columns) : [],
            ),
            cards: array_map(
                [PublicBoardCard::class, 'fromApi'],
                is_array($cards) ? array_values($cards) : [],
            ),
        );
    }
}
