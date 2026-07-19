<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Models\ChecklistItem;
use Craaft\Util\Id;

/**
 * Endpoints under /checklist.
 *
 * Card-scoped listing and creation live on CardsResource
 * (`listChecklist`, `addChecklistItem`), mirroring the comments split.
 * Any board member (contributor or above) may edit or delete items.
 */
final class ChecklistResource extends BaseResource
{
    public function update(string $itemId, ?string $text = null, ?bool $done = null): ChecklistItem
    {
        $body = [];
        if ($text !== null) {
            $body['text'] = $text;
        }
        if ($done !== null) {
            $body['done'] = $done;
        }
        $data = $this->transport->request('PATCH', '/checklist/' . Id::segment($itemId), null, $body);
        return ChecklistItem::fromApi(is_array($data) ? $data : []);
    }

    public function delete(string $itemId): void
    {
        $this->transport->request('DELETE', '/checklist/' . Id::segment($itemId));
    }
}
