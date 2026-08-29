<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Enums\WorkspaceRole;
use Craaft\Models\Invitation;
use Craaft\Models\WorkspaceMember;
use Craaft\Util\Id;

/** Workspace member and invitation endpoints. */
final class MembersResource extends BaseResource
{
    /** @return list<WorkspaceMember> */
    public function list(): array
    {
        $data = $this->transport->request('GET', '/members');
        return array_map([WorkspaceMember::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /** @return list<Invitation> */
    public function listInvitations(): array
    {
        $data = $this->transport->request('GET', '/invitations');
        return array_map([Invitation::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /**
     * Invite a user by email.
     *
     * @param list<array{projectId: string, role: BoardRole}> $boardGrants
     */
    public function createInvitation(
        string $email,
        string $role,
        ?array $boardGrants = null,
    ): Invitation {
        if (!in_array($role, ['admin', 'member'], true)) {
            throw new \InvalidArgumentException('role must be "admin" or "member"');
        }
        $body = ['email' => $email, 'role' => $role];
        if ($boardGrants !== null) {
            $body['boardGrants'] = array_map(
                static fn(array $g) => ['projectId' => $g['projectId'], 'role' => $g['role']->value],
                $boardGrants,
            );
        }
        $data = $this->transport->request('POST', '/invitations', null, $body);
        return Invitation::fromApi(is_array($data) ? $data : []);
    }

    /**
     * Change a member's workspace role. Owner/admin only.
     *
     * Only Admin and Member are settable: ownership is a property of the
     * workspace, not a role you can hand out, so the API rejects Owner
     * here. This is also the workspace-wide role, not a board grant - for
     * per-board access use ProjectsResource::updateMember().
     */
    public function updateRole(string $userId, WorkspaceRole $role): WorkspaceMember
    {
        if ($role === WorkspaceRole::Owner) {
            throw new \InvalidArgumentException('role must be Admin or Member; owner cannot be assigned');
        }
        $data = $this->transport->request(
            'PATCH',
            '/members/' . Id::segment($userId),
            null,
            ['role' => $role->value],
        );
        return WorkspaceMember::fromApi(is_array($data) ? $data : []);
    }

    /**
     * Remove a member from the workspace. Owner/admin only.
     *
     * Also closes any SSE streams that member has open, so their session
     * stops receiving board updates immediately.
     */
    public function remove(string $userId): void
    {
        $this->transport->request('DELETE', '/members/' . Id::segment($userId));
    }

    /**
     * Delete a pending invite so its accept link stops working.
     *
     * Owner/admin only. Has no effect on a member who already accepted -
     * use remove() for that.
     */
    public function revokeInvitation(string $invitationId): void
    {
        $this->transport->request('DELETE', '/invitations/' . Id::segment($invitationId));
    }
}
