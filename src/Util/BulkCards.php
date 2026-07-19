<?php

declare(strict_types=1);

namespace Craaft\Util;

use Craaft\Enums\Priority;
use DateTimeInterface;

/**
 * Helpers for the bulk card endpoints.
 *
 * Bulk payload items are associative arrays passed through verbatim so
 * callers keep the API's three-way field semantics: a present key is
 * applied, a key set to null clears a nullable field, and an absent key
 * leaves the field alone.
 */
final class BulkCards
{
    /** Server-side cap on items per bulk request. */
    public const MAX_ITEMS = 100;

    /**
     * Validate item count and normalize per-item values.
     *
     * Keys are never added or removed - explicit nulls survive into the
     * JSON body. Values are normalized to the wire types the single-card
     * methods accept: DateTimeInterface instances are serialized to ISO
     * 8601 strings, Priority enums become their string values, and `tags`
     * arrays are re-indexed so they encode as JSON arrays.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $items): array
    {
        self::assertCount(count($items));
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('each bulk card item must be an array');
            }
            foreach ($item as $key => $value) {
                if ($value instanceof DateTimeInterface) {
                    $item[$key] = Dates::serialize($value);
                } elseif ($value instanceof Priority) {
                    $item[$key] = $value->value;
                } elseif ($key === 'tags' && is_array($value)) {
                    $item[$key] = array_values($value);
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    public static function assertCount(int $count): void
    {
        if ($count < 1 || $count > self::MAX_ITEMS) {
            throw new \InvalidArgumentException(
                'bulk requests accept between 1 and ' . self::MAX_ITEMS . ' items, got ' . $count,
            );
        }
    }
}
