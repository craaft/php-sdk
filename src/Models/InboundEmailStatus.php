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
        /** Whether the server can write AI cards. */
        public bool $aiAvailable = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $raw = $data['address'] ?? null;
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            address: is_array($raw) ? InboundAddress::fromApi($raw) : null,
            aiAvailable: (bool) ($data['aiAvailable'] ?? false),
        );
    }
}
