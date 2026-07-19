<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Util\Dates;
use DateTimeImmutable;

readonly class ProjectExportAttachment
{
    public function __construct(
        public string $filename,
        public int $size,
        public DateTimeImmutable $uploadedAt,
        public ?ProjectExportUser $uploader = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $uploaderRaw = $data['uploader'] ?? null;
        return new self(
            filename: (string) $data['filename'],
            size: (int) $data['size'],
            uploadedAt: Dates::parse((string) $data['uploadedAt']) ?? throw new \InvalidArgumentException('missing uploadedAt'),
            uploader: is_array($uploaderRaw) ? ProjectExportUser::fromApi($uploaderRaw) : null,
        );
    }
}
