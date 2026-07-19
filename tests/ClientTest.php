<?php

declare(strict_types=1);

namespace Craaft\Tests;

use Craaft\CraaftClient;
use Craaft\Exceptions\CraaftError;
use Craaft\Http\RetryConfig;
use Craaft\Tests\Http\StubExecutor;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CRAAFT_API_TOKEN');
        putenv('CRAAFT_BASE_URL');
        parent::tearDown();
    }

    public function testInitRequiresApiKey(): void
    {
        putenv('CRAAFT_API_TOKEN=');
        $this->expectException(CraaftError::class);
        $this->expectExceptionMessageMatches('/API key required/');
        new CraaftClient();
    }

    public function testInitUsesEnvVar(): void
    {
        putenv('CRAAFT_API_TOKEN=cra_env');
        $c = new CraaftClient();
        $this->assertSame('cra_env', $c->transport->apiKey);
    }

    public function testInitArgOverridesEnv(): void
    {
        putenv('CRAAFT_API_TOKEN=cra_env');
        $c = new CraaftClient(apiKey: 'cra_arg');
        $this->assertSame('cra_arg', $c->transport->apiKey);
    }

    public function testDefaultBaseUrl(): void
    {
        putenv('CRAAFT_BASE_URL=');
        $c = new CraaftClient(apiKey: 'cra_x');
        $this->assertSame('https://craaft.io/api/v1', $c->transport->baseUrl);
    }

    public function testCustomBaseUrl(): void
    {
        $c = new CraaftClient(apiKey: 'cra_x', baseUrl: 'http://localhost:8080/api/v1');
        $this->assertSame('http://localhost:8080/api/v1', $c->transport->baseUrl);
    }

    public function testBaseUrlFromEnv(): void
    {
        putenv('CRAAFT_BASE_URL=https://staging.example.com/api/v1');
        $c = new CraaftClient(apiKey: 'cra_x');
        $this->assertSame('https://staging.example.com/api/v1', $c->transport->baseUrl);
    }

    public function testBaseUrlArgOverridesEnv(): void
    {
        putenv('CRAAFT_BASE_URL=https://staging.example.com/api/v1');
        $c = new CraaftClient(apiKey: 'cra_x', baseUrl: 'https://prod.example.com/api/v1');
        $this->assertSame('https://prod.example.com/api/v1', $c->transport->baseUrl);
    }

    public function testRejectsPlainHttpForNonLocalhost(): void
    {
        $this->expectException(CraaftError::class);
        new CraaftClient(apiKey: 'cra_x', baseUrl: 'http://example.com/api/v1');
    }

    public function testRejectsBadTokenFormat(): void
    {
        $this->expectException(CraaftError::class);
        new CraaftClient(apiKey: 'not_a_real_token');
    }

    public function testDefaultUserAgentIncludesVersion(): void
    {
        $c = new CraaftClient(apiKey: 'cra_x');
        $this->assertStringContainsString('craaft-php/', $c->transport->userAgent);
        $this->assertStringContainsString(CraaftClient::VERSION, $c->transport->userAgent);
    }

    public function testCustomUserAgent(): void
    {
        $c = new CraaftClient(apiKey: 'cra_x', userAgent: 'my-app/1.0');
        $this->assertSame('my-app/1.0', $c->transport->userAgent);
    }

    public function testCustomRetryConfig(): void
    {
        $cfg = new RetryConfig(maxAttempts: 5);
        $c = new CraaftClient(apiKey: 'cra_x', retry: $cfg);
        $this->assertSame(5, $c->transport->retry->maxAttempts);
    }

    public function testDisablingRetries(): void
    {
        $c = new CraaftClient(apiKey: 'cra_x', retry: new RetryConfig(maxAttempts: 1));
        $this->assertSame(1, $c->transport->retry->maxAttempts);
    }

    public function testResourceSubClientsPresent(): void
    {
        $c = new CraaftClient(apiKey: 'cra_x');
        $this->assertNotNull($c->me);
        $this->assertNotNull($c->projects);
        $this->assertNotNull($c->cards);
        $this->assertNotNull($c->comments);
        $this->assertNotNull($c->columns);
        $this->assertNotNull($c->attachments);
        $this->assertNotNull($c->members);
        $this->assertNotNull($c->checklist);
        $this->assertNotNull($c->milestones);
    }

    public function testEndToEndAuthorizationHeader(): void
    {
        $stub = new StubExecutor();
        $stub->enqueueJson(200, [
            'id' => 'u1',
            'email' => 'a@b.co',
            'name' => 'A',
            'username' => 'a',
            'avatarUrl' => null,
            'hasPassword' => true,
        ]);
        $c = new CraaftClient(apiKey: 'cra_test', http: $stub);
        $c->me->get();
        $call = $stub->lastCall();
        $this->assertContains('Authorization: Bearer cra_test', $call['headers']);
        $this->assertStringContainsString('https://craaft.io/api/v1/me', $call['url']);
    }
}
