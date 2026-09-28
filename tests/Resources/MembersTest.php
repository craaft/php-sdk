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
        // listInvitations() only ever lists pending invitations, so consumed
        // is always false here (the field is absent from the payload).
        $this->assertFalse($invitations[0]->consumed);
    }

    public function testCreateInvitation(): void
    {
        // Tolerates a bare invitation object (no {invitation, consumed}
        // wrapper) - and such a response carries no `consumed` field, so it
        // defaults to false.
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->invitation());
        $inv = $b->client()->members->createInvitation('x@y.co', 'member', boardGrants: [
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
        $this->assertFalse($inv->consumed);
    }

    public function testCreateInvitationUnwrapsTheEnvelope(): void
    {
        // The server actually wraps the created invitation as
        // {invitation, consumed}, not a bare Invitation - a real spec bug
        // that used to leave every field empty.
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'invitation' => $this->invitation(),
            'consumed' => false,
        ]);
        $inv = $b->client()->members->createInvitation('x@y.co', 'member');
        $this->assertSame('inv1', $inv->id);
        $this->assertSame('x@y.co', $inv->email);
        $this->assertFalse($inv->consumed);
    }

    public function testCreateInvitationSurfacesConsumedTrue(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, [
            'invitation' => $this->invitation(),
            'consumed' => true,
        ]);
        $inv = $b->client()->members->createInvitation('x@y.co', 'member');
        $this->assertTrue($inv->consumed);
    }

    public function testCreateInvitationRejectsBadRole(): void
    {
        $b = new ClientBuilder();
        $this->expectException(\InvalidArgumentException::class);
        $b->client()->members->createInvitation('x@y.co', 'superadmin');
    }

    public function testUpdateRolePatchesTheWorkspaceRole(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, ['role' => 'admin'] + $this->member());
        $member = $b->client()->members->updateRole('u2', WorkspaceRole::Admin);
        $call = $b->stub()->lastCall();
        $this->assertSame('PATCH', $call['method']);
        $this->assertStringEndsWith('/members/u2', $call['url']);
        $this->assertSame(['role' => 'admin'], json_decode($call['body'], true));
        $this->assertSame(WorkspaceRole::Admin, $member->role);
    }

    public function testUpdateRoleRefusesOwner(): void
    {
        // Ownership is a property of the workspace, not a role to hand out.
        $b = new ClientBuilder();
        $this->expectException(\InvalidArgumentException::class);
        try {
            $b->client()->members->updateRole('u2', WorkspaceRole::Owner);
        } finally {
            $this->assertSame(0, $b->stub()->callCount());
        }
    }

    public function testRemoveMemberDeletes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(204);
        $b->client()->members->remove('u2');
        $this->assertSame('DELETE', $b->stub()->lastCall()['method']);
    }

    public function testRevokeInvitationDeletes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(204);
        $b->client()->members->revokeInvitation('inv1');
        $this->assertStringEndsWith('/invitations/inv1', $b->stub()->lastCall()['url']);
    }

    public function testMemberIdsAreEscaped(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueResponse(204);
        $b->client()->members->remove('../admin');
        $this->assertStringEndsWith('/members/..%2Fadmin', $b->stub()->lastCall()['url']);
    }
}
