<?php

declare(strict_types=1);

namespace Craaft\Models;

/** Response shape of /cards/focus. */
readonly class FocusResponse
{
    /**
     * @param list<CardSummary>      $due
     * @param list<AttentionCard>    $attention
     */
    public function __construct(
        public array $due,
        public array $attention,
        public HygieneCounts $hygiene,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $dueRaw = $data['due'] ?? [];
        $attRaw = $data['attention'] ?? [];
        return new self(
            due: array_map([CardSummary::class, 'fromApi'], is_array($dueRaw) ? $dueRaw : []),
            attention: array_map([AttentionCard::class, 'fromApi'], is_array($attRaw) ? $attRaw : []),
            hygiene: HygieneCounts::fromApi($data['hygiene'] ?? []),
        );
    }
}