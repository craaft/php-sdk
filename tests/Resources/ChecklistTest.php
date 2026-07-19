<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Exceptions\NotFoundError;
use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ChecklistTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function item(): array
    {
        return [
            'id' => 'ck1',
            'cardId' => 'card1',
            'text' => 'write tests',
            'done' => false,
            'position' => 1.0,
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testUpdateText(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->item(), ['text' => 'edited']));
        $item = $b->client()->checklist->update('ck1', text: 'edited');
        $call = $b->stub()->lastCall();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame(self::BASE . '/checklist/ck1', $call['url']);
        $this->assertSame(['text' => 'edited'], json_decode($call['body'], true));
        $this->assertSame('edited', $item->text);
    }

    public function testUpdateDoneOnly(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->item(), ['done' => true]));
        $item = $b->client()->checklist->update('ck1', done: true);
        $this->assertSame(['done' => true], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertTrue($item->done);
    }

    public function testUpdateTextAndDone(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->item(), ['text' => 'edited', 'done' => true]));
        $b->client()->checklist->update('ck1', text: 'edited', done: true);
        $this->assertSame(
            ['text' => 'edited', 'done' => true],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testUpdate404RaisesNotFound(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(404, ['error' => 'not found']);
        $this->expectException(NotFoundError::class);
        $b->client()->checklist->update('missing', done: true);
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $this->assertNull($b->client()->checklist->delete('ck1'));
        $call = $b->stub()->lastCall();
        $this->assertSame('DELETE', $call['method']);
        $this->assertSame(self::BASE . '/checklist/ck1', $call['url']);
    }
}
