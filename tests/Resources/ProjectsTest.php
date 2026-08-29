<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Enums\Visibility;
use Craaft\Exceptions\CraaftError;
use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ProjectsTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function project(): array
    {
        return [
            'id' => 'p1',
            'workspaceId' => 'w1',
            'name' => 'Demo',
            'description' => '',
            'isFavorite' => false,
            'publicToken' => '',
            'backgroundImage' => null,
            'backgroundColor' => '',
            'colorScheme' => '',
            'textColor' => 'light',
            'myRole' => 'owner',
            'visibility' => 'private',
            'myBoardRole' => 'admin',
            'canUploadAttachments' => false,
            'totalCards' => 0,
            'columnCounts' => [],
            'columns' => [],
            'members' => [],
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testList(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [$this->project()]);
        $projects = $b->client()->projects->list();
        $this->assertSame('Demo', $projects[0]->name);
        $this->assertSame('GET', $b->stub()->lastCall()['method']);
        $this->assertSame(self::BASE . '/projects', $b->stub()->lastCall()['url']);
    }

    public function testGet(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->project());
        $b->client()->projects->get('p1');
        $this->assertSame(self::BASE . '/projects/p1', $b->stub()->lastCall()['url']);
    }

    public function testCreate(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->project());
        $b->client()->projects->create('Demo', description: 'desc');
        $this->assertSame(
            ['name' => 'Demo', 'description' => 'desc'],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testCreateWithoutDescription(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->project());
        $b->client()->projects->create('Demo');
        $this->assertSame(['name' => 'Demo'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testUpdateTranslatesVisibility(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->project());
        $b->client()->projects->update('p1', visibility: Visibility::Workspace, isFavorite: true);
        $body = json_decode($b->stub()->lastCall()['body'], true);
        $this->assertSame('workspace', $body['visibility']);
        $this->assertTrue($body['isFavorite']);
        $this->assertCount(2, $body);
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $b->client()->projects->delete('p1');
        $this->assertSame('DELETE', $b->stub()->lastCall()['method']);
    }

    public function testEnableShareReturnsToken(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['publicToken' => 'sharetok']);
        $this->assertSame('sharetok', $b->client()->projects->enableShare('p1'));
    }

    public function testListTags(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['api', 'sdk']);
        $this->assertSame(['api', 'sdk'], $b->client()->projects->listTags('p1'));
    }

    public function testAddColumn(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'id' => 'c1', 'key' => 'todo', 'title' => 'To Do',
            'color' => '', 'position' => 0.0, 'isDone' => false, 'cardLimit' => null,
        ]);
        $b->client()->projects->addColumn('p1', 'To Do');
        $this->assertSame(['title' => 'To Do'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testCreateCard(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'id' => 'card1', 'projectId' => 'p1', 'column' => 'todo', 'title' => 'x',
            'position' => 1.0, 'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z',
            'attachmentCount' => 0, 'tags' => [],
        ]);
        $b->client()->projects->createCard('p1', title: 'x', column: 'todo', position: 1.0);
        $this->assertSame(
            ['title' => 'x', 'column' => 'todo', 'position' => 1.0],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    private function cardRow(): array
    {
        return [
            'id' => 'card1', 'projectId' => 'p1', 'column' => 'todo', 'title' => 'x',
            'position' => 1.0, 'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z',
            'attachmentCount' => 0, 'tags' => [],
        ];
    }

    public function testCreateCardsBulk(): void
    {
        $b = new ClientBuilder();
        $second = array_merge($this->cardRow(), ['id' => 'card2', 'title' => 'y', 'column' => 'doing']);
        $b->stub()->enqueueJson(201, ['cards' => [$this->cardRow(), $second]]);
        $cards = $b->client()->projects->createCards('p1', [
            ['title' => 'x', 'column' => 'todo'],
            ['title' => 'y', 'column' => 'doing', 'position' => 2.5, 'priority' => \Craaft\Enums\Priority::High,
                'dueDate' => new \DateTimeImmutable('2026-08-01T12:00:00', new \DateTimeZone('UTC')),
                'tags' => ['launch']],
        ]);
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/projects/p1/cards/bulk', $call['url']);
        $this->assertSame(
            ['cards' => [
                ['title' => 'x', 'column' => 'todo'],
                ['title' => 'y', 'column' => 'doing', 'position' => 2.5, 'priority' => 'high',
                    'dueDate' => '2026-08-01T12:00:00+00:00', 'tags' => ['launch']],
            ]],
            json_decode($call['body'], true),
        );
        $this->assertCount(2, $cards);
        $this->assertSame('card1', $cards[0]->id);
        $this->assertSame('card2', $cards[1]->id);
    }

    public function testCreateCardsRejectsEmptyBatch(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->projects->createCards('p1', []);
    }

    public function testCreateCardsRejectsOversizedBatch(): void
    {
        $c = (new ClientBuilder())->client();
        $this->expectException(\InvalidArgumentException::class);
        $c->projects->createCards('p1', array_fill(0, 101, ['title' => 'x', 'column' => 'todo']));
    }

    public function testCreateCards400NamesOffendingIndex(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(400, ['error' => 'cards[3]: title is required']);
        $this->expectException(\Craaft\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('cards[3]: title is required');
        $b->client()->projects->createCards('p1', [['column' => 'todo']]);
    }

    public function testListMilestones(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [[
            'id' => 'm1', 'projectId' => 'p1', 'name' => 'Beta launch', 'dueOn' => '2026-08-01',
            'achievedAt' => null, 'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z',
        ]]);
        $milestones = $b->client()->projects->listMilestones('p1');
        $this->assertSame(self::BASE . '/projects/p1/milestones', $b->stub()->lastCall()['url']);
        $this->assertSame('Beta launch', $milestones[0]->name);
        $this->assertSame('2026-08-01', $milestones[0]->dueOn);
        $this->assertNull($milestones[0]->achievedAt);
    }

    public function testAddMilestone(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'id' => 'm1', 'projectId' => 'p1', 'name' => 'Beta launch', 'dueOn' => '2026-08-01',
            'achievedAt' => null, 'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z',
        ]);
        $m = $b->client()->projects->addMilestone('p1', name: 'Beta launch', dueOn: '2026-08-01');
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/projects/p1/milestones', $call['url']);
        $this->assertSame(
            ['name' => 'Beta launch', 'dueOn' => '2026-08-01'],
            json_decode($call['body'], true),
        );
        $this->assertSame('m1', $m->id);
    }

    public function testAddMilestoneSerializesDatetimeDueOn(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'id' => 'm1', 'projectId' => 'p1', 'name' => 'Beta launch', 'dueOn' => '2026-08-01',
            'achievedAt' => null, 'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z',
        ]);
        $b->client()->projects->addMilestone(
            'p1',
            name: 'Beta launch',
            dueOn: new \DateTimeImmutable('2026-08-01T23:59:00', new \DateTimeZone('UTC')),
        );
        $body = json_decode($b->stub()->lastCall()['body'], true);
        $this->assertSame('2026-08-01', $body['dueOn']);
    }

    public function testListMembers(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [[
            'userId' => 'u1', 'name' => 'Alice', 'email' => 'a@b.co', 'username' => 'alice',
            'avatarUrl' => '', 'role' => 'admin', 'source' => 'explicit',
            'createdAt' => '2026-05-08T10:00:00Z',
        ]]);
        $members = $b->client()->projects->listMembers('p1');
        $this->assertSame('Alice', $members[0]->name);
        $this->assertSame(BoardRole::Admin, $members[0]->role);
    }

    public function testAddMember(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'userId' => 'u1', 'role' => 'contributor', 'createdAt' => '2026-05-08T10:00:00Z',
        ]);
        $grant = $b->client()->projects->addMember('p1', 'u1', BoardRole::Contributor);
        $this->assertSame(BoardRole::Contributor, $grant->role);
        $this->assertSame(
            ['userId' => 'u1', 'role' => 'contributor'],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testUpdateMemberUrlAndBody(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [
            'userId' => 'u1', 'role' => 'admin', 'createdAt' => '2026-05-08T10:00:00Z',
        ]);
        $b->client()->projects->updateMember('p1', 'u1', BoardRole::Admin);
        $this->assertSame(self::BASE . '/projects/p1/members/u1', $b->stub()->lastCall()['url']);
        $this->assertSame(['role' => 'admin'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testRemoveMember(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $b->client()->projects->removeMember('p1', 'u1');
        $this->assertSame(self::BASE . '/projects/p1/members/u1', $b->stub()->lastCall()['url']);
    }

    public function testExport(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [
            'version' => 1,
            'exportedAt' => '2026-05-08T10:00:00Z',
            'project' => ['id' => 'p1', 'name' => 'Demo', 'isFavorite' => false,
                'createdAt' => '2026-05-08T10:00:00Z', 'updatedAt' => '2026-05-08T10:00:00Z'],
            'columns' => [],
            'cards' => [],
        ]);
        $export = $b->client()->projects->export('p1');
        $this->assertSame(1, $export->version);
        $this->assertSame('Demo', $export->project->name);
    }

    public function testIdSegmentIsUrlEncoded(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->project());
        $b->client()->projects->get('weird/id');
        $this->assertSame(self::BASE . '/projects/weird%2Fid', $b->stub()->lastCall()['url']);
    }

    public function testRebalanceCardsPostsIdsInOrder(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['cards' => []]);
        $b->client()->projects->rebalanceCards('p1', ['c3', 'c1', 'c2'], 'doing');
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/projects/p1/cards/rebalance', $call['url']);
        // Request order IS the resulting order, so it must survive verbatim.
        $this->assertSame(
            ['ids' => ['c3', 'c1', 'c2'], 'column' => 'doing'],
            json_decode($call['body'], true),
        );
    }

    public function testRebalanceCardsRejectsAnEmptyListBeforeTheRequest(): void
    {
        $b = new ClientBuilder();
        $this->expectException(\InvalidArgumentException::class);
        try {
            $b->client()->projects->rebalanceCards('p1', [], 'doing');
        } finally {
            $this->assertSame(0, $b->stub()->callCount());
        }
    }

    public function testUploadBackgroundSendsMultipart(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->project());
        $b->client()->projects->uploadBackground('p1', "\x89PNG\r\n", 'bg.png', 'image/png');
        $call = $b->stub()->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/projects/p1/background-image', $call['url']);
        $this->assertStringContainsString('multipart/form-data', implode("\n", $call['headers']));
    }

    public function testUploadBackgroundRejectsANonImageType(): void
    {
        // The server sniffs the bytes too, but failing here saves a round
        // trip and gives a clearer message than a bare 400.
        $b = new ClientBuilder();
        $this->expectException(CraaftError::class);
        $this->expectExceptionMessageMatches('/not one of/');
        try {
            $b->client()->projects->uploadBackground('p1', 'hello', 'notes.txt', 'text/plain');
        } finally {
            $this->assertSame(0, $b->stub()->callCount());
        }
    }

    public function testUploadBackgroundRejectsOver10Mib(): void
    {
        $b = new ClientBuilder();
        $this->expectException(CraaftError::class);
        $this->expectExceptionMessageMatches('/10 MiB/');
        try {
            $b->client()->projects->uploadBackground(
                'p1',
                str_repeat('x', 10 * 1024 * 1024 + 1),
                'bg.png',
                'image/png',
            );
        } finally {
            $this->assertSame(0, $b->stub()->callCount());
        }
    }

    public function testDownloadBackgroundReturnsBytes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(200, "\x89PNG\r\n", contentType: 'image/png');
        $this->assertSame("\x89PNG\r\n", $b->client()->projects->downloadBackground('p1'));
    }

    public function testDeleteBackgroundReturnsTheUpdatedProject(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->project());
        $project = $b->client()->projects->deleteBackground('p1');
        $this->assertSame('DELETE', $b->stub()->lastCall()['method']);
        $this->assertSame('p1', $project->id);
    }

    public function testExportAsksForJson(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [
            'version' => 1,
            'exportedAt' => '2026-05-08T10:00:00Z',
            'project' => [
                'id' => 'p1',
                'name' => 'Demo',
                'isFavorite' => false,
                'createdAt' => '2026-05-08T10:00:00Z',
                'updatedAt' => '2026-05-08T10:00:00Z',
            ],
            'columns' => [],
            'cards' => [],
        ]);
        $b->client()->projects->export('p1');
        $this->assertStringContainsString('format=json', $b->stub()->lastCall()['url']);
    }

    public function testExportCsvReturnsRawBytes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(200, "id,title\n1,Hello\n", contentType: 'text/csv');
        $out = $b->client()->projects->exportCsv('p1');
        $this->assertSame("id,title\n1,Hello\n", $out);
        $this->assertStringContainsString('format=csv', $b->stub()->lastCall()['url']);
    }
}
