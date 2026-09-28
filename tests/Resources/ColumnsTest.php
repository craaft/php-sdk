<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ColumnsTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function column(): array
    {
        return [
            'id' => 'col1', 'key' => 'todo', 'title' => 'To Do',
            'color' => '', 'position' => 1.0, 'isDone' => false, 'cardLimit' => null,
        ];
    }

    public function testUpdate(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->column());
        $b->client()->columns->update('col1', title: 'New', position: 3.0, isDone: true);
        $this->assertSame(
            ['title' => 'New', 'position' => 3.0, 'isDone' => true],
            json_decode($b->stub()->lastCall()['body'], true),
        );
        $this->assertSame(self::BASE . '/columns/col1', $b->stub()->lastCall()['url']);
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $b->client()->columns->delete('col1');
        $this->assertSame('DELETE', $b->stub()->lastCall()['method']);
    }

    public function testArchiveReturnsCount(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['archived' => 5, 'ids' => ['card1', 'card2', 'card3', 'card4', 'card5']]);
        $this->assertSame(5, $b->client()->columns->archive('col1'));
        $this->assertSame(self::BASE . '/columns/col1/archive', $b->stub()->lastCall()['url']);
    }

    public function testArchiveOnANonDoneColumnReturnsZero(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['archived' => 0, 'ids' => []]);
        $this->assertSame(0, $b->client()->columns->archive('col1'));
    }

    public function testArchiveWithIdsReturnsCountAndIds(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['archived' => 2, 'ids' => ['card1', 'card2']]);
        $result = $b->client()->columns->archiveWithIds('col1');
        $this->assertSame(2, $result->archived);
        $this->assertSame(['card1', 'card2'], $result->ids);
        $this->assertSame(self::BASE . '/columns/col1/archive', $b->stub()->lastCall()['url']);
    }

    public function testArchiveWithIdsOnANonDoneColumnReturnsZeroAndNoIds(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['archived' => 0, 'ids' => []]);
        $result = $b->client()->columns->archiveWithIds('col1');
        $this->assertSame(0, $result->archived);
        $this->assertSame([], $result->ids);
    }
}
