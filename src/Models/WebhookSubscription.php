<?php

declare(strict_types=1);

namespace Craaft\Models;

use Craaft\Enums\WebhookFormat;
use Craaft\Util\Dates;
use DateTimeImmutable;

/**
 * An outbound webhook subscription for one board.
 *
 * `secret` signs `craaft`-format deliveries via the `X-Craaft-Signature`
 * header and is returned on every read (creation included), not shown once
 * and redacted afterwards - slack/discord deliveries are unsigned and
 * ignore it.
 */
readonly class WebhookSubscription
{
    /**
     * @param list<string>          $events Event filter; empty = all events.
     * @param list<WebhookDelivery> $recentDeliveries
     */
    public function __construct(
        public string $id,
        public string $endpointId,
        public string $url,
        public string $secret,
        public string $description,
        public WebhookFormat $format,
        public array $events,
        public bool $active,
        public DateTimeImmutable $createdAt,
        public array $recentDeliveries,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $format = WebhookFormat::tryFrom((string) ($data['format'] ?? ''));
        if ($format === null) {
            throw new \InvalidArgumentException('unknown webhook format: ' . ($data['format'] ?? ''));
        }
        $eventsRaw = $data['events'] ?? [];
        $deliveriesRaw = $data['recentDeliveries'] ?? [];
        return new self(
            id: (string) $data['id'],
            endpointId: (string) $data['endpointId'],
            url: (string) $data['url'],
            secret: (string) $data['secret'],
            description: (string) ($data['description'] ?? ''),
            format: $format,
            events: array_map('strval', is_array($eventsRaw) ? $eventsRaw : []),
            active: (bool) ($data['active'] ?? false),
            createdAt: Dates::parse((string) $data['createdAt']) ?? throw new \InvalidArgumentException('missing createdAt'),
            recentDeliveries: array_map([WebhookDelivery::class, 'fromApi'], is_array($deliveriesRaw) ? $deliveriesRaw : []),
        );
    }
}
