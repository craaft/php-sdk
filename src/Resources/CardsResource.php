<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\HygieneType;
use Craaft\Enums\Priority;
use Craaft\Models\AttentionCard;
use Craaft\Models\Card;
use Craaft\Models\CardEvent;
use Craaft\Models\CardSummary;
use Craaft\Models\ChecklistItem;
use Craaft\Models\Comment;
use Craaft\Models\FocusResponse;
use Craaft\Util\BulkCards;
use Craaft\Util\Dates;
use Craaft\Util\Id;
use DateTimeInterface;

/**
 * Endpoints under /cards (and /search, since it returns cards).
 */
final class CardsResource extends BaseResource
{
    public function update(
        string $cardId,
        ?string $title = null,
        ?string $description = null,
        ?string $column = null,
        ?float $position = null,
        DateTimeInterface|string|null $dueDate = null,
        ?string $assignedUserId = null,
        ?int $size = null,
        ?Priority $priority = null,
        ?array $tags = null,
    ): Card {
        $body = [];
        if ($title !== null) {
            $body['title'] = $title;
        }
        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($column !== null) {
            $body['column'] = $column;
        }
        if ($position !== null) {
            $body['position'] = $position;
        }
        if ($dueDate !== null) {
            $body['dueDate'] = Dates::serialize($dueDate);
        }
        if ($assignedUserId !== null) {
            $body['assignedUserId'] = $assignedUserId;
        }
        if ($size !== null) {
            $body['size'] = $size;
        }
        if ($priority !== null) {
            $body['priority'] = $priority->value;
        }
        if ($tags !== null) {
            $body['tags'] = array_values($tags);
        }
        $data = $this->transport->request('PATCH', '/cards/' . Id::segment($cardId), null, $body);
        return Card::fromApi($this->ensureArray($data));
    }

    /**
     * Apply up to 100 partial card updates in one transaction.
     *
     * Each item is an associative array of `['id' => ...]` plus any
     * fields the single-card update accepts: `title`, `description`,
     * `column`, `position`, `dueDate`, `assignedUserId`, `size`,
     * `priority`, and `tags`. Unlike `update()`, items are passed
     * through verbatim so all three field states are expressible: a
     * present key is applied, a key set to null clears a nullable field
     * (dueDate / assignedUserId / size / priority), and an absent key
     * leaves the field alone. DateTimeInterface values and Priority
     * enums are serialized for you.
     *
     * All-or-nothing: one invalid item rolls back the whole batch and
     * the server's ValidationError names the offending index
     * (`cards[3]: title is required`). Bulk requests never send
     * notification emails.
     *
     * @param list<array<string, mixed>> $cards
     * @return list<Card> Updated cards, in request order
     */
    public function bulkUpdate(array $cards): array
    {
        $body = ['cards' => BulkCards::normalize($cards)];
        $data = $this->transport->request('PATCH', '/cards/bulk', null, $body);
        $rows = $this->ensureArray($data)['cards'] ?? [];
        return array_map([Card::class, 'fromApi'], is_array($rows) ? $rows : []);
    }

    /**
     * Move up to 100 cards to a column in one transaction.
     *
     * Without `$targetProjectId`, sweeps the cards to `$column` on their
     * own board - every id must belong to the same board (400
     * otherwise). With `$targetProjectId`, moves the batch to that board
     * instead (same workspace only; 404 otherwise; 422 when the column
     * does not exist there). Moved cards append to the end of the target
     * column in request order. All-or-nothing; no notification emails.
     *
     * @param list<string> $ids
     * @return list<Card> Moved cards, in request order
     */
    public function bulkMove(array $ids, string $column, ?string $targetProjectId = null): array
    {
        BulkCards::assertCount(count($ids));
        $body = ['ids' => array_values($ids), 'column' => $column];
        if ($targetProjectId !== null) {
            $body['targetProjectId'] = $targetProjectId;
        }
        $data = $this->transport->request('POST', '/cards/bulk/move', null, $body);
        $rows = $this->ensureArray($data)['cards'] ?? [];
        return array_map([Card::class, 'fromApi'], is_array($rows) ? $rows : []);
    }

    public function delete(string $cardId): void
    {
        $this->transport->request('DELETE', '/cards/' . Id::segment($cardId));
    }

    public function move(string $cardId, string $targetProjectId, string $column): Card
    {
        $body = ['targetProjectId' => $targetProjectId, 'column' => $column];
        $data = $this->transport->request('POST', '/cards/' . Id::segment($cardId) . '/move', null, $body);
        return Card::fromApi($this->ensureArray($data));
    }

    /** @return list<CardSummary> */
    public function upcoming(): array
    {
        $data = $this->transport->request('GET', '/cards/upcoming');
        return array_map([CardSummary::class, 'fromApi'], is_array($data) ? $data : []);
    }

    public function focus(): FocusResponse
    {
        $data = $this->transport->request('GET', '/cards/focus');
        return FocusResponse::fromApi($this->ensureArray($data));
    }

    /** @return list<AttentionCard> */
    public function hygiene(HygieneType $type): array
    {
        $data = $this->transport->request('GET', '/cards/hygiene', ['type' => $type->value]);
        return array_map([AttentionCard::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /** @return list<CardEvent> */
    public function listEvents(string $cardId): array
    {
        $data = $this->transport->request('GET', '/cards/' . Id::segment($cardId) . '/events');
        return array_map([CardEvent::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /** @return list<CardSummary> */
    public function search(string $q, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new \InvalidArgumentException('limit must be between 1 and 50');
        }
        $data = $this->transport->request('GET', '/search', ['q' => $q, 'limit' => $limit]);
        $cards = $this->ensureArray($data)['cards'] ?? [];
        return array_map([CardSummary::class, 'fromApi'], is_array($cards) ? $cards : []);
    }

    /** @return list<Comment> */
    public function listComments(string $cardId): array
    {
        $data = $this->transport->request('GET', '/cards/' . Id::segment($cardId) . '/comments');
        return array_map([Comment::class, 'fromApi'], is_array($data) ? $data : []);
    }

    public function addComment(string $cardId, string $body): Comment
    {
        $data = $this->transport->request('POST', '/cards/' . Id::segment($cardId) . '/comments', null, ['body' => $body]);
        return Comment::fromApi($this->ensureArray($data));
    }

    /** @return list<ChecklistItem> */
    public function listChecklist(string $cardId): array
    {
        $data = $this->transport->request('GET', '/cards/' . Id::segment($cardId) . '/checklist');
        return array_map([ChecklistItem::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /** Add a checklist item (max 1000 chars); it is appended to the end. */
    public function addChecklistItem(string $cardId, string $text): ChecklistItem
    {
        $data = $this->transport->request('POST', '/cards/' . Id::segment($cardId) . '/checklist', null, ['text' => $text]);
        return ChecklistItem::fromApi($this->ensureArray($data));
    }

    private function ensureArray(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }
}
