<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Models\InboundAddress;
use Craaft\Models\InboundEmailStatus;
use Craaft\Util\Id;

/**
 * Endpoints under /projects/{id}/inbound-email: a board's email-to-card
 * intake address. Board-admin only, Pro boards only - get/enable/update
 * raise PlanLimitError (402) on a Free board; disable() is not plan-gated
 * so a downgraded board can still turn intake off.
 */
final class InboundEmailResource extends BaseResource
{
    /** Current status: not configured, or the address plus whether it's active. */
    public function get(string $projectId): InboundEmailStatus
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId) . '/inbound-email');
        return InboundEmailStatus::fromApi(is_array($data) ? $data : []);
    }

    /**
     * Mint the board's inbound address. Raises ConflictError (409) if one
     * already exists - use update() to change it, or disable() then
     * enable() again for a clean slate. `$aiEnrich` turns AI cards for email
     * threads on or off; turning it on raises ConflictError (409) when the
     * server has no AI key.
     */
    public function enable(string $projectId, ?string $targetColumn = null, ?bool $aiEnrich = null): InboundAddress
    {
        $body = [];
        if ($targetColumn !== null) {
            $body['targetColumn'] = $targetColumn;
        }
        if ($aiEnrich !== null) {
            $body['aiEnrich'] = $aiEnrich;
        }
        $data = $this->transport->request(
            'POST',
            '/projects/' . Id::segment($projectId) . '/inbound-email',
            null,
            $body === [] ? null : $body,
        );
        return InboundAddress::fromApi(is_array($data) ? $data : []);
    }

    /**
     * Partial update - send only the fields to change. `$targetColumn = ''`
     * clears it back to the board's first column; `$rotate = true` mints a
     * new token, so the old address stops accepting mail immediately.
     * `$aiEnrich` turns AI cards for email threads on or off; turning it on
     * raises ConflictError (409) when the server has no AI key.
     */
    public function update(
        string $projectId,
        ?bool $active = null,
        ?string $targetColumn = null,
        ?bool $rotate = null,
        ?bool $aiEnrich = null,
    ): InboundAddress {
        $body = [];
        if ($active !== null) {
            $body['active'] = $active;
        }
        if ($targetColumn !== null) {
            $body['targetColumn'] = $targetColumn;
        }
        if ($rotate !== null) {
            $body['rotate'] = $rotate;
        }
        if ($aiEnrich !== null) {
            $body['aiEnrich'] = $aiEnrich;
        }
        $data = $this->transport->request('PATCH', '/projects/' . Id::segment($projectId) . '/inbound-email', null, $body);
        return InboundAddress::fromApi(is_array($data) ? $data : []);
    }

    /** Disable inbound email for a board. Board-admin only, not plan-gated. */
    public function disable(string $projectId): void
    {
        $this->transport->request('DELETE', '/projects/' . Id::segment($projectId) . '/inbound-email');
    }
}
