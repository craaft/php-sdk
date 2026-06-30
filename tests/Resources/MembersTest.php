<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Enums\WorkspaceRole;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class MembersTest extends TestCase
{
    private function member(): array
    {
        return [
            'userId' => 'u1', 'email' => 'a@b.co', 'name' => 'Alice',
            'role' => 'owner', 'avatarUrl' => '', 'joinedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    private function invitation(): array
    {
        return [
            'id' => 'inv1', 'email' => 'x@y.co', 'role' => 'member',
            'invitedBy' => 'u1', 'invitedByName' => 'Alice',
            'createdAt' => '2026-05-08T10:00:00Z', 'expiresAt' => '2026-05-15T10:00:00Z',
            'boardGrants' => [],
        ];
    }

    public function testList(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [$this->member()]);
        $members = $b->client()->members->list();
        $this->assertSame('Alice', $members[0]->name);
        $this->assertSame(WorkspaceRole::Owner, $members[0]->role);
    }

    public function testListInvitations(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [$this->invitation()]);
        $invitations = $b->client()->members->listInvitations();
        $this->assertSame('x@y.co', $invitations[0]->email);
    }

    public function testCreateInvitation(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->invitation());
        $b->client()->members->createInvitation('x@y.co', 'member', boardGrants: [
            ['projectId' => 'p1', 'role' => BoardRole::Admin],
        ]);
        $this->assertSame(
            [
                'email' => 'x@y.co',
                'role' => 'member',
                'boardGrants' => [['projectId' => 'p1', 'role' => 'admin']],
            ],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }

    public function testCreateInvitationRejectsBadRole(): void
    {
        $b = new ClientBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $b->client()->members->createInvitation('x@y.co', 'superadmin');
    }
}
