<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class ProjectExportComment
{
    public function __construct(
        public ProjectExportUser $author,
        public string $body,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $author = $data['author'] ?? null;
        if (!is_array($author)) {
            throw new \InvalidArgumentException('export comment missing author');
        }
        return new self(
            author: ProjectExportUser::fromApi($author),
            body: (string) $data['body'],
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
        );
    }
}
