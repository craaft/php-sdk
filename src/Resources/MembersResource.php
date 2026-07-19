<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Models\Invitation;
use Craaft\Models\WorkspaceMember;

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
}
