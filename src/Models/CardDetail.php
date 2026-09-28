<?php

declare(strict_types=1);

namespace Craaft\Models;

/**
 * One authorised envelope for a card view (GET /cards/{id}/detail): the
 * card plus its comments, activity events, checklist and attachments, so
 * opening a card costs one round-trip instead of five.
 */
readonly class CardDetail
{
    /**
     * @param list<Comment>       $comments
     * @param list<CardEvent>     $events
     * @param list<ChecklistItem> $checklist
     * @param list<Attachment>    $attachments
     */
    public function __construct(
        public Card $card,
        public array $comments,
        public array $events,
        public array $checklist,
        public array $attachments,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        return new self(
            card: Card::fromApi((array) $data['card']),
            comments: array_map(static fn(array $c): Comment => Comment::fromApi($c), array_values((array) ($data['comments'] ?? []))),
            events: array_map(static fn(array $e): CardEvent => CardEvent::fromApi($e), array_values((array) ($data['events'] ?? []))),
            checklist: array_map(static fn(array $i): ChecklistItem => ChecklistItem::fromApi($i), array_values((array) ($data['checklist'] ?? []))),
            attachments: array_map(static fn(array $a): Attachment => Attachment::fromApi($a), array_values((array) ($data['attachments'] ?? []))),
        );
    }
}
