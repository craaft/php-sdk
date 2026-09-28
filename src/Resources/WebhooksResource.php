<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\WebhookFormat;
use Craaft\Models\WebhookSubscription;
use Craaft\Util\Id;

/**
 * Endpoints under /webhooks/{id} (the subscription id).
 *
 * Listing and creation are board-scoped and live on
 * `ProjectsResource::listWebhooks()` / `createWebhook()` - the same split
 * used for columns (`ProjectsResource::addColumn()` + `ColumnsResource`)
 * and milestones (`ProjectsResource::addMilestone()` + `MilestonesResource`).
 */
final class WebhooksResource extends BaseResource
{
    /**
     * Partial update - send only the fields to change. Board-admin only; a
     * downgraded (Free) board raises PlanLimitError here (delete() stays
     * open so it can still clean up).
     *
     * @param list<string>|null $events
     */
    public function update(
        string $subscriptionId,
        ?string $url = null,
        ?string $description = null,
        ?WebhookFormat $format = null,
        ?array $events = null,
        ?bool $active = null,
    ): WebhookSubscription {
        $body = [];
        if ($url !== null) {
            $body['url'] = $url;
        }
        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($format !== null) {
            $body['format'] = $format->value;
        }
        if ($events !== null) {
            $body['events'] = array_values($events);
        }
        if ($active !== null) {
            $body['active'] = $active;
        }
        $data = $this->transport->request('PATCH', '/webhooks/' . Id::segment($subscriptionId), null, $body);
        return WebhookSubscription::fromApi(is_array($data) ? $data : []);
    }

    /** Board-admin only. Not plan-gated, so a downgraded board can still clean up. */
    public function delete(string $subscriptionId): void
    {
        $this->transport->request('DELETE', '/webhooks/' . Id::segment($subscriptionId));
    }
}
