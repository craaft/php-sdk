<?php

declare(strict_types=1);

namespace Craaft\Tests\Models;

use Craaft\Enums\Priority;
use Craaft\Enums\TextColor;
use Craaft\Enums\Visibility;
use Craaft\Enums\WorkspaceRole;
use Craaft\Models\Card;
use Craaft\Models\CardSummary;
use Craaft\Models\Column;
use Craaft\Models\Comment;
use Craaft\Models\Project;
use Craaft\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ModelsTest extends TestCase
{
    public function testUserFromApi(): void
    {
        $u = User::fromApi([
            'id' => 'u1',
            'email' => 'a@b.co',
            'name' => 'Alice',
            'username' => 'alice',
            'avatarUrl' => 'https://example.com/a.png',
            'hasPassword' => true,
        ]);
        $this->assertSame('u1', $u->id);
        $this->assertSame('https://example.com/a.png', $u->avatarUrl);
        $this->assertTrue($u->hasPassword);
    }

    public function testColumnFromApi(): void
    {
        $c = Column::fromApi([
            'id' => 'col1',
            'key' => 'todo',
            'title' => 'To Do',
            'color' => '#aaa',
            'position' => 1.5,
            'isDone' => false,
            'cardLimit' => null,
        ]);
        $this->assertSame('todo', $c->key);
        $this->assertSame(1.5, $c->position);
        $this->assertNull($c->cardLimit);
    }

    public function testCardFromApiFull(): void
    {
        $c = Card::fromApi([
            'id' => 'card1',
            'projectId' => 'proj1',
            'column' => 'todo',
            'title' => 'Do thing',
            'position' => 2.0,
            'description' => 'do it',
            'dueDate' => '2026-06-01T00:00:00+00:00',
            'assignedUserId' => 'u2',
            'assignedUserName' => 'Bob',
            'size' => 3,
            'priority' => 'high',
            'createdBy' => 'u1',
            'createdByName' => 'Alice',
            'updatedBy' => 'u1',
            'updatedByName' => 'Alice',
            'attachmentCount' => 3,
            'tags' => ['api', 'sdk'],
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T11:00:00+00:00',
        ]);
        $this->assertSame('u2', $c->assignedUserId);
        $this->assertSame(3, $c->size);
        $this->assertSame(Priority::High, $c->priority);
        $this->assertSame(['api', 'sdk'], $c->tags);
        $this->assertEquals(
            new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
            $c->dueDate,
        );
    }

    public function testCardFromApiLegacyAssigneeIdKey(): void
    {
        $c = Card::fromApi([
            'id' => 'card1',
            'projectId' => 'proj1',
            'column' => 'todo',
            'title' => 'x',
            'position' => 1.0,
            'assigneeId' => 'u2',
            'attachmentCount' => 0,
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ]);
        $this->assertSame('u2', $c->assignedUserId);
    }

    public function testCardSummaryFromSearchShape(): void
    {
        $c = CardSummary::fromApi([
            'id' => 'card1',
            'projectId' => 'proj1',
            'projectName' => 'Launch board',
            'columnKey' => 'doing',
            'columnTitle' => 'In Progress',
            'title' => 'Ship',
            'description' => 'do it',
            'updatedAt' => '2026-05-08T10:00:00Z',
            'archived' => true,
        ]);
        $this->assertTrue($c->archived);
        $this->assertSame('do it', $c->description);
    }

    public function testCommentFromApi(): void
    {
        $c = Comment::fromApi([
            'id' => 'cm1',
            'cardId' => 'card1',
            'authorId' => 'u1',
            'body' => 'hi',
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ]);
        $this->assertSame('hi', $c->body);
    }

    public function testProjectFromApiWithColumns(): void
    {
        $p = Project::fromApi([
            'id' => 'p1',
            'workspaceId' => 'w1',
            'workspaceName' => 'Personal',
            'name' => 'Demo',
            'description' => 'desc',
            'isFavorite' => true,
            'publicToken' => 'tok',
            'backgroundImage' => null,
            'backgroundColor' => '#aabbcc',
            'colorScheme' => null,
            'textColor' => 'dark',
            'myRole' => 'owner',
            'visibility' => 'private',
            'myBoardRole' => 'admin',
            'canUploadAttachments' => true,
            'totalCards' => 5,
            'columnCounts' => ['todo' => 2, 'doing' => 3],
            'columns' => [
                [
                    'id' => 'c1',
                    'key' => 'todo',
                    'title' => 'To Do',
                    'color' => '#aaa',
                    'position' => 1.0,
                    'isDone' => false,
                    'cardLimit' => null,
                ],
            ],
            'members' => [['id' => 'u1', 'name' => 'Alice', 'avatarUrl' => '']],
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ]);
        $this->assertSame('Personal', $p->workspaceName);
        $this->assertSame('#aabbcc', $p->backgroundColor);
        $this->assertSame(Visibility::Private, $p->visibility);
        $this->assertSame(WorkspaceRole::Owner, $p->myRole);
        $this->assertSame(TextColor::Dark, $p->textColor);
        $this->assertTrue($p->canUploadAttachments);
        $this->assertCount(1, $p->members);
        $this->assertSame('Alice', $p->members[0]->name);
        $this->assertSame(['todo' => 2, 'doing' => 3], $p->columnCounts);
    }
}
