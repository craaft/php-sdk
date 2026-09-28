<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Enums\WebhookFormat;
use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function webhook(): array
    {
        return [
            'id' => 'wh1', 'endpointId' => 'ep1', 'url' => 'https://example.com/hook',
            'secret' => 'whsec_x', 'description' => '', 'format' => 'craaft', 'events' => [],
            'active' => true, 'createdAt' => '2026-05-08T10:00:00Z', 'recentDeliveries' => [],
        ];
    }

    public function testUpdate(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->webhook(), ['active' => false]));
        $wh = $b->client()->webhooks->update('wh1', active: false);
        $call = $b->stub()->lastCall();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame(self::BASE . '/webhooks/wh1', $call['url']);
        $this->assertSame(['active' => false], json_decode($call['body'], true));
        $this->assertFalse($wh->active);
    }

    public function testUpdateAllFields(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->webhook(), ['format' => 'discord']));
        $b->client()->webhooks->update(
            'wh1',
            url: 'https://discord.com/api/webhooks/x',
            description: 'Discord alerts',
            format: WebhookFormat::Discord,
            events: ['card.created', 'card.deleted'],
            active: true,
        );
        $this->assertSame(
            [
                'url' => 'https://discord.com/api/webhooks/x',
                'description' => 'Discord alerts',
                'format' => 'discord',
                'events' => ['card.created', 'card.deleted'],
                'active' => true,
            ],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testUpdateRecentDeliveries(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->webhook(), [
            'recentDeliveries' => [[
                'event' => 'card.created', 'status' => 'delivered', 'statusCode' => 200,
                'attempts' => 1, 'error' => null, 'createdAt' => '2026-05-08T10:00:00Z',
            ]],
        ]));
        $wh = $b->client()->webhooks->update('wh1', active: true);
        $this->assertCount(1, $wh->recentDeliveries);
        $this->assertSame('card.created', $wh->recentDeliveries[0]->event);
        $this->assertSame('delivered', $wh->recentDeliveries[0]->status);
        $this->assertNull($wh->recentDeliveries[0]->error);
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $b->client()->webhooks->delete('wh1');
        $call = $b->stub()->lastCall();
        $this->assertSame('DELETE', $call['method']);
        $this->assertSame(self::BASE . '/webhooks/wh1', $call['url']);
    }
}
