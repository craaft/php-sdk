<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class ProjectExport
{
    /**
     * @param list<ProjectExportColumn> $columns
     * @param list<ProjectExportCard>   $cards
     */
    public function __construct(
        public int $version,
        public DateTimeImmutable $exportedAt,
        public ProjectExportProject $project,
        public array $columns,
        public array $cards,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $projectRaw = $data['project'] ?? null;
        if (!is_array($projectRaw)) {
            throw new \InvalidArgumentException('export payload missing project');
        }
        $columnsRaw = $data['columns'] ?? [];
        $cardsRaw = $data['cards'] ?? [];

        return new self(
            version: (int) $data['version'],
            exportedAt: Dates::parse((string) $data['exportedAt']) ?? throw new \InvalidArgumentException('missing exportedAt'),
            project: ProjectExportProject::fromApi($projectRaw),
            columns: array_map([ProjectExportColumn::class, 'fromApi'], is_array($columnsRaw) ? $columnsRaw : []),
            cards: array_map([ProjectExportCard::class, 'fromApi'], is_array($cardsRaw) ? $cardsRaw : []),
        );
    }
}
