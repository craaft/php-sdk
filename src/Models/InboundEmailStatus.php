<?php

declare(strict_types=1);

namespace Craaft\Models;

/** Response shape of GET /projects/{id}/inbound-email. */
readonly class InboundEmailStatus
{
    public function __construct(
        public bool $enabled,
        /** Present only when $enabled is true. */
        public ?InboundAddress $address,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $raw = $data['address'] ?? null;
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            address: is_array($raw) ? InboundAddress::fromApi($raw) : null,
        );
    }
}
