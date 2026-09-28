<?php

declare(strict_types=1);

namespace Craaft\Models;

/** Response shape of GET /projects/{id}/webhooks. */
readonly class WebhookList
{
    /**
     * @param list<WebhookSubscription> $webhooks
     * @param list<string>              $eventCatalogue Broadcast event names for a filter picker.
     */
    public function __construct(
        public array $webhooks,
        public array $eventCatalogue,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $webhooksRaw = $data['webhooks'] ?? [];
        $catalogueRaw = $data['eventCatalogue'] ?? [];
        return new self(
            webhooks: array_map([WebhookSubscription::class, 'fromApi'], is_array($webhooksRaw) ? $webhooksRaw : []),
            eventCatalogue: array_map('strval', is_array($catalogueRaw) ? $catalogueRaw : []),
        );
    }
}
