<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Models\Milestone;
use Craaft\Util\Dates;
use Craaft\Util\Id;
use DateTimeInterface;

/**
 * Endpoints under /milestones.
 *
 * Project-scoped listing and creation live on ProjectsResource
 * (`listMilestones`, `addMilestone`). Any board member may read, but
 * writes are board-admin only - the server raises PermissionError (403)
 * for non-admin board members.
 */
final class MilestonesResource extends BaseResource
{
    /**
     * Partial update. Setting `achieved: true` stamps `achievedAt` once
     * (re-sending true keeps the original stamp); `achieved: false`
     * clears it. `$dueOn` accepts a DateTimeInterface or a plain
     * `YYYY-MM-DD` string.
     */
    public function update(
        string $milestoneId,
        ?string $name = null,
        DateTimeInterface|string|null $dueOn = null,
        ?bool $achieved = null,
    ): Milestone {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($dueOn !== null) {
            $body['dueOn'] = Dates::serializeDate($dueOn);
        }
        if ($achieved !== null) {
            $body['achieved'] = $achieved;
        }
        $data = $this->transport->request('PATCH', '/milestones/' . Id::segment($milestoneId), null, $body);
        return Milestone::fromApi(is_array($data) ? $data : []);
    }

    public function delete(string $milestoneId): void
    {
        $this->transport->request('DELETE', '/milestones/' . Id::segment($milestoneId));
    }
}
