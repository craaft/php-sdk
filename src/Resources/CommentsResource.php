<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Models\Comment;
use Craaft\Util\Id;

/** Endpoints under /comments. */
final class CommentsResource extends BaseResource
{
    public function update(string $commentId, string $body): Comment
    {
        $data = $this->transport->request('PATCH', '/comments/' . Id::segment($commentId), null, ['body' => $body]);
        return Comment::fromApi(is_array($data) ? $data : []);
    }

    public function delete(string $commentId): void
    {
        $this->transport->request('DELETE', '/comments/' . Id::segment($commentId));
    }
}