<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\HygieneType;
use Craaft\Enums\Priority;
use Craaft\Models\AttentionCard;
use Craaft\Models\Card;
use Craaft\Models\CardEvent;
use Craaft\Models\CardSummary;
use Craaft\Models\Comment;
use Craaft\Models\FocusResponse;
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

    private function ensureArray(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }
}