<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class InboundEmailTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function address(): array
    {
        return [
            'email' => 'board-abc123@mail.craaft.io', 'token' => 'abc123',
            'targetColumn' => null, 'active' => true, 'createdAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testGetWhenNotEnabled(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['enabled' => false, 'address' => null]);
        $status = $b->client()->inboundEmail->get('p1');
        $this->assertSame(self::BASE . '/projects/p1/inbound-email', $b->stub()->lastCall()['url']);
        $this->assertFalse($status->enabled);
        $this->assertNull($status->address);
    }

    public function testGetWhenEnabled(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['enabled' => true, 'address' => $this->address()]);
        $status = $b->client()->inboundEmail->get('p1');
        $this->assertTrue($status->enabled);
        $this->assertSame('abc123', $status->address?->token);
        $this->assertNull($status->address?->targetColumn);
    }

    public function testEnableWithoutTargetColumnSendsNoBody(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->address());
        $addr = $b->client()->inboundEmail->enable('p1');
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/projects/p1/inbound-email', $call['url']);
        $this->assertNull($call['body']);
        $this->assertSame('abc123', $addr->token);
    }

    public function testEnableWithTargetColumn(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, array_merge($this->address(), ['targetColumn' => 'todo']));
        $addr = $b->client()->inboundEmail->enable('p1', targetColumn: 'todo');
        $this->assertSame(
            ['targetColumn' => 'todo'],
            json_decode($b->stub()->lastCall()['body'], true),
        );
        $this->assertSame('todo', $addr->targetColumn);
    }

    public function testUpdateRotatesToken(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->address(), ['token' => 'newtok']));
        $addr = $b->client()->inboundEmail->update('p1', rotate: true);
        $this->assertSame(['rotate' => true], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertSame('newtok', $addr->token);
    }

    public function testUpdateClearsTargetColumn(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->address());
        $b->client()->inboundEmail->update('p1', targetColumn: '');
        $this->assertSame(
            ['targetColumn' => ''],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testUpdateSendsAiEnrichAndParsesItBack(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->address(), ['aiEnrich' => true]));
        $addr = $b->client()->inboundEmail->update('p1', aiEnrich: true);
        $this->assertSame(['aiEnrich' => true], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertTrue($addr->aiEnrich);
    }

    public function testEnableSendsAiEnrich(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, array_merge($this->address(), ['aiEnrich' => true]));
        $addr = $b->client()->inboundEmail->enable('p1', aiEnrich: true);
        $this->assertSame(['aiEnrich' => true], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertTrue($addr->aiEnrich);
    }

    public function testGetParsesAiAvailableAndDefaultsAiEnrichOff(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['enabled' => true, 'address' => $this->address(), 'aiAvailable' => true]);
        $status = $b->client()->inboundEmail->get('p1');
        $this->assertTrue($status->aiAvailable);
        $this->assertFalse($status->address?->aiEnrich);
    }

    public function testDisable(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $b->client()->inboundEmail->disable('p1');
        $call = $b->stub()->lastCall();
        $this->assertSame('DELETE', $call['method']);
        $this->assertSame(self::BASE . '/projects/p1/inbound-email', $call['url']);
    }
}
