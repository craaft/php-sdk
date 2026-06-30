<?php

declare(strict_types=1);

namespace Craaft\Http;

use Craaft\Exceptions;
use Craaft\Exceptions\CraaftError;
use Craaft\Exceptions\RateLimitError;

/**
 * Owns request construction, retries transient failures with backoff, and
 * maps non-2xx responses to typed exceptions.
 *
 * The actual network I/O is delegated to an injectable executor (default:
 * {@see CurlExecutor}) so the transport is fully unit-testable without a
 * live server.
 */
final class Transport
{
    public const MAX_RETRY_AFTER_SECONDS = 300.0;
    private const DEFAULT_MAX_RESPONSE_BYTES = 32 * 1024 * 1024;

    /** Methods that mutate server state; retried conservatively. */
    private const WRITE_METHODS = ['POST', 'PATCH', 'PUT', 'DELETE'];

    public readonly string $baseUrl;

    /** @var callable(int<0,max>): void */
    private $sleeper;

    /** @var callable(float): float */
    private $jitterFn;

    private readonly int $maxResponseBytes;

    /** @var list<string> */
    private array $log = [];

    /**
     * @param callable(string $method, string $url, list<string> $headers, ?string $body, float|array $timeout, int $bodyLimit): HttpAttempt|null $http
     * @param callable(int<0,max>): void|null $sleeper  Microsecond sleeper (tests inject a recorder).
     * @param callable(float): float|null      $jitterFn Returns a uniform delta in seconds in [-amount, amount].
     */
    public function __construct(
        public readonly string $apiKey,
        string $baseUrl,
        public readonly mixed $timeout,
        public readonly RetryConfig $retry,
        public readonly string $userAgent,
        int $maxResponseBytes = self::DEFAULT_MAX_RESPONSE_BYTES,
        private readonly ?object $http = null,
        ?callable $sleeper = null,
        ?callable $jitterFn = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->maxResponseBytes = $maxResponseBytes;
        $this->sleeper = $sleeper ?? static fn (int $us): int => usleep($us) ?? 0;
        $this->jitterFn = $jitterFn ?? static function (float $amount): float {
            if ($amount <= 0.0) {
                return 0.0;
            }
            return (mt_rand(0, (int) (2_000_000 * $amount)) / 1_000_000.0) - $amount;
        };
    }

    /**
     * Perform an HTTP request and return decoded JSON, raw bytes (when
     * parseJson=false), or null for 204 / empty bodies.
     *
     * @param array<string, mixed>|null      $params
     * @param array<string, mixed>|null      $json
     * @param array<string, array{name: string, contents: string|resource, contentType?: string|null, filename?: string|null}>|null $files
     * @param array<string, string>|null      $headers
     */
    public function request(
        string $method,
        string $path,
        ?array $params = null,
        ?array $json = null,
        ?array $files = null,
        bool $parseJson = true,
        ?int $maxResponseBytes = null,
        ?array $headers = null,
    ): mixed {
        $url = $this->baseUrl . $path;
        if ($params !== null && $params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $method = strtoupper($method);
        $attempts = max(1, $this->retry->maxAttempts);
        $bodyLimit = $maxResponseBytes ?? $this->maxResponseBytes;

        $boundary = $files !== null ? $this->multipartBoundary() : null;
        $payload = $this->buildBody($json, $files, $boundary);
        $contentType = $files !== null
            ? 'multipart/form-data; boundary=' . $boundary
            : ($json !== null ? 'application/json' : null);
        $curlHeaders = $this->buildHeaders($headers, $contentType);

        $http = $this->http ?? new CurlExecutor();

        $lastStatus = null;
        $lastBody = '';
        $lastRequestId = null;
        $lastRetryAfter = null;
        $lastAttempt = null;

        for ($attempt = 0; $attempt < $attempts; ++$attempt) {
            try {
                $result = $http->execute($method, $url, $curlHeaders, $payload, $this->timeout, $bodyLimit);
            } catch (Exceptions\TimeoutError | Exceptions\ConnectionError $e) {
                $this->log[] = "network error on {$method} {$path} attempt {$attempt}: {$e->getMessage()}";
                if (!$this->shouldRetry($method, $attempt, null, true)) {
                    throw $e;
                }
                $this->sleepBefore($attempt, null);
                continue;
            }

            $status = $result->status;
            $lastStatus = $status;
            $lastBody = $result->body;
            $lastRequestId = $this->headerValue($result->headers, 'x-request-id');
            $lastRetryAfter = $this->headerValue($result->headers, 'retry-after');
            $lastAttempt = $result;

            $this->log[] = "{$method} {$path} status={$status} attempt={$attempt}";

            if ($status >= 200 && $status < 300) {
                if ($status === 204 || $result->body === '') {
                    return null;
                }
                if (!$parseJson) {
                    return $result->body;
                }
                try {
                    return json_decode($result->body, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new CraaftError("server returned a 2xx response with non-JSON body: {$e->getMessage()}");
                }
            }

            if ($status >= 300 && $status < 400) {
                $location = $this->headerValue($result->headers, 'location') ?? '';
                throw new CraaftError("unexpected redirect: {$status} -> '{$location}'");
            }

            if (!$this->shouldRetry($method, $attempt, $status, false)) {
                $this->raiseForStatus($status, $result->body, $lastRequestId, $lastRetryAfter);
            }

            $this->sleepBefore($attempt, $status === 429 ? $lastRetryAfter : null);
        }

        if ($lastAttempt !== null) {
            $this->raiseForStatus((int) $lastStatus, $lastBody, $lastRequestId, $lastRetryAfter);
        }
        throw new CraaftError('Transport::request exited the retry loop with no response or error');
    }

    public function close(): void
    {
        // Nothing to free; cURL handles are created/destroyed per request.
    }

    /** @return list<string> */
    public function logLines(): array
    {
        return $this->log;
    }

    private function shouldRetry(string $method, int $attempt, ?int $status, bool $networkError): bool
    {
        if ($attempt + 1 >= $this->retry->maxAttempts) {
            return false;
        }
        if ($networkError) {
            return !($this->isWrite($method) && !$this->retry->retryWritesOnNetworkError);
        }
        if ($status === null) {
            return false;
        }
        if (!in_array($status, $this->retry->retryStatus, true)) {
            return false;
        }
        return !($this->isWrite($method) && $status >= 500 && $status < 600 && !$this->retry->retryWritesOn5xx);
    }

    private function isWrite(string $method): bool
    {
        return in_array($method, self::WRITE_METHODS, true);
    }

    private function backoffSeconds(int $attempt): float
    {
        $base = min($this->retry->backoffBase * (2 ** $attempt), $this->retry->backoffMax);
        if ($this->retry->jitter > 0.0) {
            $base += ($this->jitterFn)($base * $this->retry->jitter);
        }
        return max(0.0, $base);
    }

    private function sleepBefore(int $attempt, ?string $retryAfterHeader): void
    {
        $seconds = $retryAfterHeader !== null ? $this->parseRetryAfter($retryAfterHeader) : null;
        if ($seconds === null) {
            $seconds = $this->backoffSeconds($attempt);
        }
        if ($seconds <= 0.0) {
            return;
        }
        ($this->sleeper)((int) ($seconds * 1_000_000));
    }

    private function parseRetryAfter(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return min(max(0.0, (float) $value), self::MAX_RETRY_AFTER_SECONDS);
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        $delta = (float) $ts - time();
        return min(max(0.0, $delta), self::MAX_RETRY_AFTER_SECONDS);
    }

    private function multipartBoundary(): string
    {
        return '----CraaftBoundary' . bin2hex(random_bytes(16));
    }

    /**
     * @param array<string, string>|null $extra
     * @return list<string>
     */
    private function buildHeaders(?array $extra, ?string $contentType): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
        ];
        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }
        if ($extra !== null) {
            foreach ($extra as $name => $value) {
                $headers[$name] = $value;
            }
        }
        $out = [];
        foreach ($headers as $name => $value) {
            $out[] = "{$name}: {$value}";
        }
        return $out;
    }

    private function buildBody(?array $json, ?array $files, ?string $boundary): ?string
    {
        if ($files !== null) {
            return $this->encodeMultipart($files, $boundary ?? $this->multipartBoundary());
        }
        if ($json !== null) {
            try {
                return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            } catch (\JsonException $e) {
                throw new CraaftError("failed to encode JSON request body: {$e->getMessage()}");
            }
        }
        return null;
    }

    /**
     * @param array<string, array{name: string, contents: string|resource, contentType?: string|null, filename?: string|null}> $files
     */
    private function encodeMultipart(array $files, string $boundary): string
    {
        $out = '';
        foreach ($files as $part) {
            $out .= "--{$boundary}\r\n";
            $disposition = "Content-Disposition: form-data; name=\"{$part['name']}\"";
            $filename = $part['filename'] ?? null;
            if (is_string($filename) && $filename !== '') {
                $disposition .= "; filename=\"{$filename}\"";
            }
            $out .= $disposition . "\r\n";
            $contentType = $part['contentType'] ?? null;
            if (is_string($contentType) && $contentType !== '') {
                $out .= "Content-Type: {$contentType}\r\n";
            }
            $out .= "\r\n";
            $contents = $part['contents'];
            if (is_resource($contents)) {
                rewind($contents);
                $contents = stream_get_contents($contents);
            }
            if (!is_string($contents)) {
                throw new CraaftError('unsupported multipart contents type');
            }
            $out .= $contents . "\r\n";
        }
        $out .= "--{$boundary}--\r\n";
        return $out;
    }

    private function raiseForStatus(int $status, string $body, ?string $requestId, ?string $retryAfter): never
    {
        $message = $this->parseMessage($body);
        if ($message === '') {
            $message = "HTTP {$status}";
        }
        $cls = Exceptions\ExceptionMap::classForStatus($status);
        if ($cls === RateLimitError::class) {
            $retrySeconds = $retryAfter !== null ? $this->parseRetryAfter($retryAfter) : null;
            throw new RateLimitError($message, $status, $body, $requestId, $retrySeconds);
        }
        throw new $cls($message, $status, $body, $requestId);
    }

    private function parseMessage(string $body): string
    {
        if ($body === '') {
            return '';
        }
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['error']) && is_string($data['error'])) {
            return $this->scrub($data['error']);
        }
        return $this->scrub(mb_substr($body, 0, 200));
    }

    private function scrub(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;
    }

    private function headerValue(string $headers, string $name): ?string
    {
        $lines = preg_split('/\r\n/', $headers) ?: [];
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            if (strcasecmp(trim($parts[0]), $name) === 0) {
                return trim($parts[1]);
            }
        }
        return null;
    }
}
