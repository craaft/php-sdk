<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\TextColor;
use Craaft\Util\Dates;
use DateTimeImmutable;

/**
 * The project half of a public share snapshot.
 *
 * Deliberately thinner than Project - the public endpoint omits workspace,
 * ownership and membership fields.
 */
readonly class PublicBoardProject
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $updatedAt,
        public ?string $description = null,
        public ?string $backgroundImage = null,
        public ?string $backgroundColor = null,
        public ?string $colorScheme = null,
        public ?TextColor $textColor = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            updatedAt: Dates::parse((string) $data['updatedAt'])
                ?? throw new \InvalidArgumentException('missing updatedAt'),
            description: ($data['description'] ?? null) === null ? null : (string) $data['description'],
            backgroundImage: ($data['backgroundImage'] ?? '') !== '' ? (string) $data['backgroundImage'] : null,
            backgroundColor: ($data['backgroundColor'] ?? '') !== '' ? (string) $data['backgroundColor'] : null,
            colorScheme: ($data['colorScheme'] ?? '') !== '' ? (string) $data['colorScheme'] : null,
            textColor: TextColor::tryFrom((string) ($data['textColor'] ?? '')),
        );
    }
}
