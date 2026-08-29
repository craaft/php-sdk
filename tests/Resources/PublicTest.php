<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Enums\Priority;
use Craaft\Enums\TextColor;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class PublicTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function board(): array
    {
        return [
            'project' => [
                'id' => 'p1',
                'name' => 'Roadmap',
                'description' => 'Public plan',
                'backgroundImage' => '',
                'backgroundColor' => '#101820',
                'colorScheme' => 'midnight',
                'textColor' => 'light',
                'updatedAt' => '2026-05-08T10:00:00Z',
            ],
            'columns' => [
                ['key' => 'todo', 'title' => 'To Do', 'color' => '', 'position' => 1.0],
                ['key' => 'done', 'title' => 'Done', 'color' => 'green', 'position' => 2.0, 'isDone' => true],
            ],
            'cards' => [
                [
                    'id' => 'c1',
                    'column' => 'todo',
                    'position' => 1.0,
                    'title' => 'Ship it',
                    'priority' => 'high',
                    'assignedUserName' => 'Ava',
                ],
            ],
        ];
    }

    public function testBoardMapsTheTrimmedProjection(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->board());
        $board = $b->client()->public->board('tok123');

        $this->assertSame(self::BASE . '/public/projects/tok123', $b->stub()->lastCall()['url']);
        $this->assertSame('Roadmap', $board->project->name);
        $this->assertSame(TextColor::Light, $board->project->textColor);
        $this->assertSame(['todo', 'done'], array_map(fn($c) => $c->key, $board->columns));
        $this->assertTrue($board->columns[1]->isDone);
        $this->assertSame(Priority::High, $board->cards[0]->priority);
        $this->assertSame('Ava', $board->cards[0]->assignedUserName);
    }

    public function testBoardDefaultsMissingOptionals(): void
    {
        $payload = $this->board();
        $payload['project'] = ['id' => 'p1', 'name' => 'Bare', 'updatedAt' => '2026-05-08T10:00:00Z'];
        $payload['cards'] = [];

        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $payload);
        $board = $b->client()->public->board('tok123');

        $this->assertNull($board->project->description);
        $this->assertNull($board->project->colorScheme);
        $this->assertNull($board->project->textColor);
        $this->assertSame([], $board->cards);
    }

    public function testBoardEscapesTheToken(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->board());
        $b->client()->public->board('a/b');
        $this->assertSame(self::BASE . '/public/projects/a%2Fb', $b->stub()->lastCall()['url']);
    }

    public function testBoardBackgroundReturnsBytes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(200, "\x89PNG\r\n", contentType: 'image/png');
        $this->assertSame("\x89PNG\r\n", $b->client()->public->boardBackground('tok123'));
        $this->assertStringEndsWith('/public/projects/tok123/background-image', $b->stub()->lastCall()['url']);
    }

    public function testAvatarReturnsBytes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(200, "\xff\xd8\xff", contentType: 'image/jpeg');
        $this->assertSame("\xff\xd8\xff", $b->client()->public->avatar('u1'));
        $this->assertStringEndsWith('/users/u1/avatar', $b->stub()->lastCall()['url']);
    }

    public function testVersionReturnsThePayload(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['version' => '1.2.3']);
        $this->assertSame(['version' => '1.2.3'], $b->client()->version());
        $this->assertStringEndsWith('/version', $b->stub()->lastCall()['url']);
    }
}
