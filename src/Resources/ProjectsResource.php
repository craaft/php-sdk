<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Enums\Visibility;
use Craaft\Models\BoardMember;
use Craaft\Models\BoardMemberGrant;
use Craaft\Models\Card;
use Craaft\Models\Column;
use Craaft\Models\Milestone;
use Craaft\Models\Project;
use Craaft\Models\ProjectExport;
use Craaft\Util\BulkCards;
use Craaft\Util\Dates;
use Craaft\Util\Id;
use DateTimeInterface;

/** Endpoints under /projects and /projects/{id}/.... */
final class ProjectsResource extends BaseResource
{
    /** @return list<Project> */
    public function list(): array
    {
        $data = $this->transport->request('GET', '/projects');
        $rows = is_array($data) ? $data : [];
        return array_map([Project::class, 'fromApi'], $rows);
    }

    public function get(string $projectId): Project
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId));
        return Project::fromApi($this->ensureArray($data));
    }

    public function create(string $name, ?string $description = null): Project
    {
        $body = ['name' => $name];
        if ($description !== null) {
            $body['description'] = $description;
        }
        $data = $this->transport->request('POST', '/projects', null, $body);
        return Project::fromApi($this->ensureArray($data));
    }

    public function update(
        string $projectId,
        ?string $name = null,
        ?string $description = null,
        ?bool $isFavorite = null,
        ?string $backgroundImage = null,
        ?string $backgroundColor = null,
        ?string $colorScheme = null,
        ?string $textColor = null,
        ?Visibility $visibility = null,
    ): Project {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($description !== null) {
            $body['description'] = $description;
        }
        if ($isFavorite !== null) {
            $body['isFavorite'] = $isFavorite;
        }
        if ($backgroundImage !== null) {
            $body['backgroundImage'] = $backgroundImage;
        }
        if ($backgroundColor !== null) {
            $body['backgroundColor'] = $backgroundColor;
        }
        if ($colorScheme !== null) {
            $body['colorScheme'] = $colorScheme;
        }
        if ($textColor !== null) {
            $body['textColor'] = $textColor;
        }
        if ($visibility !== null) {
            $body['visibility'] = $visibility->value;
        }
        $data = $this->transport->request('PATCH', '/projects/' . Id::segment($projectId), null, $body);
        return Project::fromApi($this->ensureArray($data));
    }

    public function delete(string $projectId): void
    {
        $this->transport->request('DELETE', '/projects/' . Id::segment($projectId));
    }

    public function export(string $projectId): ProjectExport
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId) . '/export');
        return ProjectExport::fromApi($this->ensureArray($data));
    }

    /** @return list<string> */
    public function listTags(string $projectId): array
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId) . '/tags');
        if (!is_array($data)) {
            return [];
        }
        return array_map('strval', $data);
    }

    public function enableShare(string $projectId): string
    {
        $data = $this->transport->request('POST', '/projects/' . Id::segment($projectId) . '/share');
        return (string) ($this->ensureArray($data)['publicToken'] ?? '');
    }

    public function disableShare(string $projectId): void
    {
        $this->transport->request('DELETE', '/projects/' . Id::segment($projectId) . '/share');
    }

    /** @return list<Card> */
    public function listCards(string $projectId): array
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId) . '/cards');
        return array_map([Card::class, 'fromApi'], is_array($data) ? $data : []);
    }

    public function createCard(
        string $projectId,
        string $title,
        string $column,
        float $position,
        ?string $description = null,
    ): Card {
        $body = [
            'title' => $title,
            'column' => $column,
            'position' => $position,
        ];
        if ($description !== null) {
            $body['description'] = $description;
        }
        $data = $this->transport->request('POST', '/projects/' . Id::segment($projectId) . '/cards', null, $body);
        return Card::fromApi($this->ensureArray($data));
    }

    /**
     * Create up to 100 cards in one transaction.
     *
     * Each item is an associative array with required `title` and
     * `column` keys, plus optional `description`, `position` (float;
     * omit to append to the end of the column in request order),
     * `dueDate` (DateTimeInterface or RFC 3339 string),
     * `assignedUserId`, `size` (int), `priority` (Priority enum or
     * string), and `tags` (list of strings). Unlike `createCard()`, the
     * assignee is NOT defaulted to the caller - omit `assignedUserId`
     * for an unassigned card.
     *
     * All-or-nothing: one invalid item rolls back the whole batch and
     * the server's ValidationError names the offending index
     * (`cards[3]: title is required`). Bulk requests never send
     * notification emails.
     *
     * @param list<array<string, mixed>> $cards
     * @return list<Card> Created cards, in request order
     */
    public function createCards(string $projectId, array $cards): array
    {
        $body = ['cards' => BulkCards::normalize($cards)];
        $data = $this->transport->request('POST', '/projects/' . Id::segment($projectId) . '/cards/bulk', null, $body);
        $rows = $this->ensureArray($data)['cards'] ?? [];
        return array_map([Card::class, 'fromApi'], is_array($rows) ? $rows : []);
    }

    /**
     * List a project's milestones, ordered by dueOn then creation.
     *
     * @return list<Milestone>
     */
    public function listMilestones(string $projectId): array
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId) . '/milestones');
        return array_map([Milestone::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /**
     * Add a milestone (board admins only; PermissionError for other
     * board members). `$name` max 200 chars; `$dueOn` accepts a
     * DateTimeInterface or a plain `YYYY-MM-DD` string.
     */
    public function addMilestone(string $projectId, string $name, DateTimeInterface|string $dueOn): Milestone
    {
        $body = ['name' => $name, 'dueOn' => Dates::serializeDate($dueOn)];
        $data = $this->transport->request('POST', '/projects/' . Id::segment($projectId) . '/milestones', null, $body);
        return Milestone::fromApi($this->ensureArray($data));
    }

    public function addColumn(string $projectId, string $title): Column
    {
        $data = $this->transport->request('POST', '/projects/' . Id::segment($projectId) . '/columns', null, ['title' => $title]);
        return Column::fromApi($this->ensureArray($data));
    }

    /** @return list<BoardMember> */
    public function listMembers(string $projectId): array
    {
        $data = $this->transport->request('GET', '/projects/' . Id::segment($projectId) . '/members');
        return array_map([BoardMember::class, 'fromApi'], is_array($data) ? $data : []);
    }

    public function addMember(string $projectId, string $userId, BoardRole $role): BoardMemberGrant
    {
        $body = ['userId' => $userId, 'role' => $role->value];
        $data = $this->transport->request('POST', '/projects/' . Id::segment($projectId) . '/members', null, $body);
        return BoardMemberGrant::fromApi($this->ensureArray($data));
    }

    public function updateMember(string $projectId, string $userId, BoardRole $role): BoardMemberGrant
    {
        $path = '/projects/' . Id::segment($projectId) . '/members/' . Id::segment($userId);
        $data = $this->transport->request('PATCH', $path, null, ['role' => $role->value]);
        return BoardMemberGrant::fromApi($this->ensureArray($data));
    }

    public function removeMember(string $projectId, string $userId): void
    {
        $this->transport->request('DELETE', '/projects/' . Id::segment($projectId) . '/members/' . Id::segment($userId));
    }

    private function ensureArray(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }
}
