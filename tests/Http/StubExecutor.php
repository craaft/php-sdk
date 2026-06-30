<?php

declare(strict_types=1);

namespace Craaft\Tests\Http;

use Craaft\Exceptions\ConnectionError;
use Craaft\Exceptions\TimeoutError;
use Craaft\Http\HttpAttempt;

/**
 * Records every execute() call and replays a queue of prepared responses
 * (or thrown exceptions). Mirrors the behaviour of the Python `responses`
 * library: each call pops the next queued entry.
 */
final class StubExecutor
{
    /** @var list<HttpAttempt|\Throwable> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    private array $calls = [];

    public function enqueue(HttpAttempt|\Throwable $response): void
    {
        $this->queue[] = $response;
    }

    public function enqueueResponse(int $status, string $body = '', string $headers = '', ?string $contentType = null): void
    {
        $hdrs = $headers;
        if ($contentType !== null && $hdrs === '') {
            $hdrs = "HTTP/1.1 {$status} OK\r\nContent-Type: {$contentType}\r\n\r\n";
        }
        $this->queue[] = new HttpAttempt($status, $hdrs, $body);
    }

    public function enqueueJson(int $status, mixed $data, array $extraHeaders = []): void
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hdrs = "HTTP/1.1 {$status} OK\r\nContent-Type: application/json\r\n";
        foreach ($extraHeaders as $name => $value) {
            $hdrs .= "{$name}: {$value}\r\n";
        }
        $hdrs .= "\r\n";
        $this->queue[] = new HttpAttempt($status, $hdrs, (string) $body);
    }

    public function enqueueNetworkError(string $message = 'boom', bool $timeout = false): void
    {
        $this->queue[] = $timeout
            ? new TimeoutError($message)
            : new ConnectionError($message);
    }

    /**
     * @param list<string> $headers
     */
    public function execute(string $method, string $url, array $headers, ?string $body, float|array $timeout, int $bodyLimit): HttpAttempt
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
        if ($this->queue === []) {
            throw new \LogicException('StubExecutor queue is empty');
        }
        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        return $next;
    }

    /** @return list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    public function calls(): array
    {
        return $this->calls;
    }

    public function callCount(): int
    {
        return count($this->calls);
    }

    public function lastCall(): array
    {
        if ($this->calls === []) {
            throw new \LogicException('no calls recorded');
        }
        return $this->calls[count($this->calls) - 1];
    }
}
