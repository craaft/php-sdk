<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Enums\Visibility;
use Craaft\Tests\ClientBuilder;
use Craaft\Http\HttpAttempt;
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
}
