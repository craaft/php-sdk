<?php

declare(strict_types=1);

namespace Craaft\Http;

/**
 * Immutable retry configuration.
 *
 * All times are in seconds. The class is a readonly struct so default
 * instances shared across many clients cannot be mutated by accident.
 */
final class RetryConfig
{
    /**
     * @param int        $maxAttempts               Total attempts (1 = no retries).
     * @param float      $backoffBase                Base for exponential backoff.
     * @param float      $backoffMax                 Cap on a single backoff delay.
     * @param float      $jitter                     Fraction of the base to add as uniform jitter.
     * @param bool       $retryWritesOn5xx           Whether to retry writes on 5xx.
     * @param bool       $retryWritesOnNetworkError  Whether to retry writes after a network failure.
     * @param list<int>  $retryStatus                HTTP statuses eligible for retry.
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly float $backoffBase = 0.5,
        public readonly float $backoffMax = 30.0,
        public readonly float $jitter = 0.25,
        public readonly bool $retryWritesOn5xx = false,
        public readonly bool $retryWritesOnNetworkError = false,
        public readonly array $retryStatus = [429, 502, 503, 504],
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be >= 1');
        }
        if ($backoffBase < 0.0) {
            throw new \InvalidArgumentException('backoffBase must be >= 0');
        }
        if ($backoffMax < 0.0) {
            throw new \InvalidArgumentException('backoffMax must be >= 0');
        }
        if ($jitter < 0.0 || $jitter > 1.0) {
            throw new \InvalidArgumentException('jitter must be between 0 and 1');
        }
    }
}
