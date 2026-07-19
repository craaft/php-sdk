<?php

declare(strict_types=1);

namespace Craaft\Tests\Http;

use Craaft\Exceptions\AuthenticationError;
use Craaft\Exceptions\ConflictError;
use Craaft\Exceptions\ConnectionError;
use Craaft\Exceptions\CraaftApiError;
use Craaft\Exceptions\NotFoundError;
use Craaft\Exceptions\PlanLimitError;
use Craaft\Exceptions\RateLimitError;
use Craaft\Exceptions\ServerError;
use Craaft\Exceptions\TimeoutError;
use Craaft\Exceptions\ValidationError;
use Craaft\Http\HttpAttempt;
use Craaft\Http\RetryConfig;
use Craaft\Http\Transport;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private const BASE = 'https://example.test/api/v1';

    private function makeTransport(?RetryConfig $retry = null, ?StubExecutor $http = null, ?callable $sleeper = null): array
    {
        $stub = $http ?? new StubExecutor();
        $sleeps = new \stdClass();
        $sleeps->values = [];
        $sleeper ??= static function (int $us) use ($sleeps): void {
            $sleeps->values[] = $us / 1_000_000.0;
        };
        $retry ??= new RetryConfig(maxAttempts: 1);
        $t = new Transport(
            apiKey: 'cra_test',
            baseUrl: self::BASE,
            timeout: 5.0,
            retry: $retry,
            userAgent: 'craaft-php/test',
            http: $stub,
            sleeper: $sleeper,
            jitterFn: static fn(float $a): float => 0.0,
        );
        return [$t, $stub, $sleeps];
    }

    /**
     * @dataProvider provideStatusMap
     */
    public function testErrorStatusToException(int $status, string $expectedClass): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueueJson($status, ['error' => 'bad'], ['x-request-id' => 'req_1']);
        $this->expectException($expectedClass);
        try {
            $t->request('GET', '/probe');
        } catch (CraaftApiError $e) {
            $this->assertSame($status, $e->statusCode);
            $this->assertSame('bad', $e->getMessage() === '' ? '' : $e->getMessage());
            $this->assertSame('req_1', $e->requestId);
            throw $e;
        }
    }

    public static function provideStatusMap(): array
    {
        return [
            '400' => [400, ValidationError::class],
            '401' => [401, AuthenticationError::class],
            '402' => [402, PlanLimitError::class],
            '403' => [403, \Craaft\Exceptions\PermissionError::class],
            '404' => [404, NotFoundError::class],
            '409' => [409, ConflictError::class],
            '422' => [422, ValidationError::class],
            '500' => [500, ServerError::class],
            '503' => [503, ServerError::class],
        ];
    }

    public function testUnparseableBodyFallsBackToStatus(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueue(new HttpAttempt(500, "HTTP/1.1 500 OK\r\n\r\n", '<html>500 oops</html>'));
        $this->expectException(ServerError::class);
        try {
            $t->request('GET', '/probe');
        } catch (ServerError $e) {
            $this->assertSame(500, $e->statusCode);
            $this->assertStringContainsString('oops', strtolower($e->getMessage()));
            throw $e;
        }
    }

    public function testAuthorizationAndUserAgentHeaders(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueueJson(200, ['ok' => true]);
        $t->request('GET', '/probe');
        $call = $stub->lastCall();
        $this->assertContains('Authorization: Bearer cra_test', $call['headers']);
        $this->assertContains('User-Agent: craaft-php/test', $call['headers']);
        $this->assertContains('Accept: application/json', $call['headers']);
    }

    public function testReturnsParsedJsonOn2xx(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueueJson(200, ['ok' => true]);
        $this->assertSame(['ok' => true], $t->request('GET', '/probe'));
    }

    public function testReturnsNullOn204(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $this->assertNull($t->request('DELETE', '/probe'));
    }

    public function testQueryParamsAppended(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueueJson(200, []);
        $t->request('GET', '/probe', ['q' => 'hi', 'limit' => 10]);
        $this->assertStringContainsString('q=hi', $stub->lastCall()['url']);
        $this->assertStringContainsString('limit=10', $stub->lastCall()['url']);
    }

    public function testJsonBodySerialized(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueueJson(201, ['ok' => true]);
        $t->request('POST', '/probe', null, ['name' => 'x']);
        $this->assertSame('{"name":"x"}', $stub->lastCall()['body']);
        $this->assertContains('Content-Type: application/json', $stub->lastCall()['headers']);
    }

    public function testRetryOn503ThenSuccess(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 3, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueJson(503, ['err' => 1]);
        $stub->enqueueJson(200, ['ok' => true]);
        $result = $t->request('GET', '/probe');
        $this->assertSame(['ok' => true], $result);
        $this->assertSame(2, $stub->callCount());
    }

    public function testRetryExhaustedRaisesServerError(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 3, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueJson(503, ['error' => 'down']);
        $stub->enqueueJson(503, ['error' => 'down']);
        $stub->enqueueJson(503, ['error' => 'down']);
        $this->expectException(ServerError::class);
        try {
            $t->request('GET', '/probe');
        } catch (ServerError $e) {
            $this->assertSame(3, $stub->callCount());
            throw $e;
        }
    }

    public function testRetry429HonorsRetryAfterSeconds(): void
    {
        [$t, $stub, $sleeps] = $this->makeTransport(new RetryConfig(maxAttempts: 3, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueJson(429, ['error' => 'rate limited'], ['Retry-After' => '2']);
        $stub->enqueueJson(200, ['ok' => true]);
        $t->request('GET', '/probe');
        $this->assertSame([2.0], $sleeps->values);
    }

    public function testPostDoesNotRetryOn503ByDefault(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 3, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueJson(503, ['error' => 'x']);
        $this->expectException(ServerError::class);
        try {
            $t->request('POST', '/probe', null, ['a' => 1]);
        } catch (ServerError $e) {
            $this->assertSame(1, $stub->callCount());
            throw $e;
        }
    }

    public function testPostRetriesOn503WhenEnabled(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 3, backoffBase: 0.0, jitter: 0.0, retryWritesOn5xx: true));
        $stub->enqueueJson(503, ['error' => 'x']);
        $stub->enqueueJson(201, ['ok' => true]);
        $result = $t->request('POST', '/probe', null, ['a' => 1]);
        $this->assertSame(['ok' => true], $result);
        $this->assertSame(2, $stub->callCount());
    }

    public function testPostRetriesOn429(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 3, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueJson(429, ['error' => 'rl']);
        $stub->enqueueJson(201, ['ok' => true]);
        $result = $t->request('POST', '/probe', null, ['a' => 1]);
        $this->assertSame(['ok' => true], $result);
        $this->assertSame(2, $stub->callCount());
    }

    public function testConnectionErrorRetriesThenRaises(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 2, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueNetworkError('boom');
        $stub->enqueueNetworkError('boom');
        $this->expectException(ConnectionError::class);
        try {
            $t->request('GET', '/probe');
        } catch (ConnectionError $e) {
            $this->assertSame(2, $stub->callCount());
            throw $e;
        }
    }

    public function testTimeoutErrorRaisesTyped(): void
    {
        [$t, $stub] = $this->makeTransport(new RetryConfig(maxAttempts: 1, backoffBase: 0.0, jitter: 0.0));
        $stub->enqueueNetworkError('slow', timeout: true);
        $this->expectException(TimeoutError::class);
        $t->request('GET', '/probe');
    }

    public function testRateLimitErrorExposesRetryAfter(): void
    {
        [$t, $stub] = $this->makeTransport();
        $stub->enqueueJson(429, ['error' => 'slow down'], ['Retry-After' => '5']);
        try {
            $t->request('GET', '/probe');
            $this->fail('expected RateLimitError');
        } catch (RateLimitError $e) {
            $this->assertSame(5.0, $e->retryAfter);
        }
    }
}
