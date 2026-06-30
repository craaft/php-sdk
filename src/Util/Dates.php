<?php

declare(strict_types=1);

namespace Craaft\Util;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Date-time helpers for serializing to and parsing from the ISO 8601 form
 * the Craaft API expects.
 */
final class Dates
{
    /**
     * Serialize a value for an API date-time field.
     *
     * Strings pass through unchanged so callers can format themselves.
     * Naive DateTimeImmutable values are assumed UTC.
     */
    public static function serialize(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        $tz = $value->getTimezone();
        if ($tz === null || $tz->getName() === 'Z' || $tz->getName() === '+00:00') {
            // Nothing to do; format will include Z or offset.
        }
        if ($tz === null) {
            $value = $value->setTimezone(new DateTimeZone('UTC'));
        }
        $iso = $value->format(DateTimeInterface::ATOM);
        // DateTimeInterface::ATOM uses +00:00; the API accepts Z too but we
        // keep ATOM for round-trip safety.
        return $iso;
    }

    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        // fromisoformat / ATOM: convert trailing Z into +00:00 for older PHP.
        $normalized = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
        $dt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $normalized);
        if ($dt === false) {
            // Fall back to the looser ISO parser.
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s.uP', $normalized) ?: DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:sP', $normalized);
        }
        if ($dt === false) {
            throw new \InvalidArgumentException("unparseable date-time: {$value}");
        }
        return $dt;
    }
}