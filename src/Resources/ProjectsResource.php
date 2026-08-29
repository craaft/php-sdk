<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Enums\BoardRole;
use Craaft\Enums\Visibility;
use Craaft\Exceptions\CraaftError;
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
    /**
     * Server cap for board backgrounds, in bytes. Lower than the 25 MiB
     * attachment cap because a background is decoded on every board load.
     */
    private const MAX_BACKGROUND_BYTES = 10 * 1024 * 1024;

    private const MAX_BACKGROUND_DOWNLOAD_BYTES = self::MAX_BACKGROUND_BYTES + (1 << 20);

    /**
     * The server both checks the declared type against this set AND sniffs
     * the leading bytes, so a renamed file is rejected rather than stored.
     *
     * @var list<string>
     */
    private const BACKGROUND_CONTENT_TYPES = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];

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

    /** Export the board as structured JSON. */
    public function export(string $projectId): ProjectExport
    {
        $data = $this->transport->request(
            'GET',
            '/projects/' . Id::segment($projectId) . '/export',
            ['format' => 'json'],
        );
        return ProjectExport::fromApi($this->ensureArray($data));
    }

    /**
     * Export the board as CSV.
     *
     * The CSV is the flat card list only - it cannot carry the nested
     * columns, comments and attachment metadata that export() returns, so
     * prefer JSON unless a spreadsheet is the destination.
     */
    public function exportCsv(string $projectId): string
    {
        $data = $this->transport->request(
            'GET',
            '/projects/' . Id::segment($projectId) . '/export',
            ['format' => 'csv'],
            null,
            null,
            false,
        );
        if (!is_string($data)) {
            throw new CraaftError('expected a raw response body for a CSV export');
        }
        return $data;
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
     * Renumber cards onto one column as positions 1, 2, 3, ... in order.
     *
     * This is board maintenance, not an import path: it exists for when
     * repeated midpoint inserts have squeezed the gap between two
     * neighbours down to nothing, so a drop has no representable position
     * left. Every id must already be on this board (NotFoundError
     * otherwise), and the whole call is one all-or-nothing transaction
     * that sends no notification emails. Sized for a real column - up to
     * 10 000 ids. To create cards, use createCards().
     *
     * @param list<string> $ids
     * @return list<Card>
     */
    public function rebalanceCards(string $projectId, array $ids, string $column): array
    {
        $count = count($ids);
        if ($count < 1 || $count > 10000) {
            throw new \InvalidArgumentException('ids must contain between 1 and 10000 items');
        }
        $data = $this->transport->request(
            'POST',
            '/projects/' . Id::segment($projectId) . '/cards/rebalance',
            null,
            ['ids' => array_values($ids), 'column' => $column],
        );
        $rows = $this->ensureArray($data)['cards'] ?? [];
        return array_map([Card::class, 'fromApi'], is_array($rows) ? $rows : []);
    }

    /**
     * Set the board's background image. Board admins only.
     *
     * Accepts a filesystem path, a string of bytes, or an SplFileInfo,
     * capped at 10 MiB. The type must be PNG, JPEG, WebP or GIF, and the
     * server additionally sniffs the leading bytes - a mislabelled file is
     * rejected, not stored. Setting a background clears any
     * backgroundColor; the two are mutually exclusive.
     */
    public function uploadBackground(
        string $projectId,
        string|\SplFileInfo $file,
        ?string $filename = null,
        ?string $contentType = null,
    ): Project {
        [$name, $contents, $ct] = $this->resolveUpload($file, $filename, $contentType);

        if ($contents === '') {
            throw new CraaftError('cannot upload an empty file');
        }
        if (strlen($contents) > self::MAX_BACKGROUND_BYTES) {
            throw new CraaftError('file exceeds the 10 MiB upload limit');
        }
        if (!in_array($ct, self::BACKGROUND_CONTENT_TYPES, true)) {
            $allowed = implode(', ', self::BACKGROUND_CONTENT_TYPES);
            throw new CraaftError("background content type {$ct} is not one of: {$allowed}");
        }

        $files = [
            'file' => [
                'name' => $name,
                'contents' => $contents,
                'contentType' => $ct,
                'filename' => $name,
            ],
        ];
        $data = $this->transport->request(
            'POST',
            '/projects/' . Id::segment($projectId) . '/background-image',
            null,
            null,
            $files,
        );
        return Project::fromApi($this->ensureArray($data));
    }

    /**
     * Fetch the board background bytes.
     *
     * Raises NotFoundError when the board has no background set, which is
     * the same status you get for a board you cannot reach.
     */
    public function downloadBackground(string $projectId): string
    {
        $data = $this->transport->request(
            'GET',
            '/projects/' . Id::segment($projectId) . '/background-image',
            null,
            null,
            null,
            false,
            self::MAX_BACKGROUND_DOWNLOAD_BYTES,
        );
        if (!is_string($data)) {
            throw new CraaftError('expected binary response body for a background download');
        }
        return $data;
    }

    /**
     * Remove the board background. Board admins only.
     *
     * Returns the updated project rather than 204, so the caller can see
     * the cleared state without a re-fetch.
     */
    public function deleteBackground(string $projectId): Project
    {
        $data = $this->transport->request(
            'DELETE',
            '/projects/' . Id::segment($projectId) . '/background-image',
        );
        return Project::fromApi($this->ensureArray($data));
    }

    /**
     * Normalize an upload argument into [name, bytes, contentType].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveUpload(
        string|\SplFileInfo $file,
        ?string $filename,
        ?string $contentType,
    ): array {
        if ($file instanceof \SplFileInfo) {
            $path = $file->getPathname();
            if (!is_file($path) || !is_readable($path)) {
                throw new CraaftError("cannot read file: {$path}");
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new CraaftError("failed to read file: {$path}");
            }
            $name = $filename ?? $file->getFilename();
        } elseif (is_file($file) && is_readable($file)) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new CraaftError("failed to read file: {$file}");
            }
            $name = $filename ?? basename($file);
        } else {
            // Treat the string as raw bytes.
            $contents = $file;
            $name = $filename ?? 'background';
        }

        $guessed = @mime_content_type($name);
        $ct = $contentType ?? (is_string($guessed) && $guessed !== '' ? $guessed : 'application/octet-stream');
        return [$name, $contents, $ct];
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
