<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Exceptions\CraaftError;
use Craaft\Models\PublicBoard;
use Craaft\Util\Id;

/**
 * Endpoints that need no authentication.
 *
 * These are reachable without a token at all. The SDK still sends the
 * client's bearer header (harmless - the server ignores it here), so the
 * same client instance works for both authenticated and public reads.
 */
final class PublicResource extends BaseResource
{
    /**
     * Avatars and board backgrounds are both images; allow the larger of
     * the two caps plus slack so a legitimate response is never truncated.
     */
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024 + (1 << 20);

    /**
     * Read a board that has public sharing enabled.
     *
     * The share token IS the access check. Revoking sharing, or re-enabling
     * it (which mints a fresh token), invalidates old links immediately and
     * this raises NotFoundError. The snapshot is a trimmed projection: no
     * workspace or ownership fields, and no card metadata beyond priority
     * and assignee.
     */
    public function board(string $token): PublicBoard
    {
        $data = $this->transport->request('GET', '/public/projects/' . Id::segment($token));
        return PublicBoard::fromApi(is_array($data) ? $data : []);
    }

    /** Fetch the background image bytes for a publicly shared board. */
    public function boardBackground(string $token): string
    {
        return $this->image('/public/projects/' . Id::segment($token) . '/background-image');
    }

    /**
     * Fetch a user's uploaded avatar bytes.
     *
     * Raises NotFoundError when the user has never uploaded one - the app
     * renders a generated placeholder in that case, so callers should treat
     * 404 as "use your own fallback" rather than as an error.
     */
    public function avatar(string $userId): string
    {
        return $this->image('/users/' . Id::segment($userId) . '/avatar');
    }

    private function image(string $path): string
    {
        $data = $this->transport->request('GET', $path, null, null, null, false, self::MAX_IMAGE_BYTES);
        if (!is_string($data)) {
            throw new CraaftError('expected binary response body for an image download');
        }
        return $data;
    }
}
