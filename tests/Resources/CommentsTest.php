<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class CommentsTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function comment(): array
    {
        return [
            'id' => 'cm1', 'cardId' => 'card1', 'authorId' => 'u1', 'body' => 'hi',
            'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testUpdate(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->comment());
        $b->client()->comments->update('cm1', 'edited');
        $this->assertSame(self::BASE . '/comments/cm1', $b->stub()->lastCall()['url']);
        $this->assertSame(['body' => 'edited'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $b->client()->comments->delete('cm1');
        $this->assertSame('DELETE', $b->stub()->lastCall()['method']);
    }
}
