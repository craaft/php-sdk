<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Models\Column;
use Craaft\Util\Id;

/** Endpoints under /columns. */
final class ColumnsResource extends BaseResource
{
    public function update(
        string $columnId,
        ?string $title = null,
        ?string $color = null,
        ?float $position = null,
        ?bool $isDone = null,
        ?int $cardLimit = null,
    ): Column {
        $body = [];
        if ($title !== null) {
            $body['title'] = $title;
        }
        if ($color !== null) {
            $body['color'] = $color;
        }
        if ($position !== null) {
            $body['position'] = $position;
        }
        if ($isDone !== null) {
            $body['isDone'] = $isDone;
        }
        if ($cardLimit !== null) {
            $body['cardLimit'] = $cardLimit;
        }
        $data = $this->transport->request('PATCH', '/columns/' . Id::segment($columnId), null, $body);
        return Column::fromApi(is_array($data) ? $data : []);
    }

    public function delete(string $columnId): void
    {
        $this->transport->request('DELETE', '/columns/' . Id::segment($columnId));
    }

    public function archive(string $columnId): int
    {
        $data = $this->transport->request('POST', '/columns/' . Id::segment($columnId) . '/archive');
        return (int) (is_array($data) ? ($data['archived'] ?? 0) : 0);
    }
}