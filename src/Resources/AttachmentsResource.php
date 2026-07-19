<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Exceptions\CraaftError;
use Craaft\Models\Attachment;
use Craaft\Util\Id;

/** Endpoints under /cards/{id}/attachments and /attachments. */
final class AttachmentsResource extends BaseResource
{
    /** Server cap on upload size, in bytes. */
    private const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;

    /** Download responses can be as large as a single attachment. */
    private const MAX_DOWNLOAD_BYTES = self::MAX_UPLOAD_BYTES + (1 << 20);

    /** @return list<Attachment> */
    public function listForCard(string $cardId): array
    {
        $data = $this->transport->request('GET', '/cards/' . Id::segment($cardId) . '/attachments');
        return array_map([Attachment::class, 'fromApi'], is_array($data) ? $data : []);
    }

    /**
     * Upload a file to a card.
     *
     * Accepts a filesystem path, a string of bytes, a readable resource, or
     * an instance of SplFileInfo. Requires the card's workspace to be on a
     * paid plan (otherwise 402).
     */
    public function upload(
        string $cardId,
        string|\SplFileInfo $file,
        ?string $filename = null,
        ?string $contentType = null,
    ): Attachment {
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
            $ct = $contentType ?? ($this->guessContentType($name) ?? 'application/octet-stream');
        } else {
            $path = $file;
            if (is_file($path) && is_readable($path)) {
                $contents = file_get_contents($path);
                if ($contents === false) {
                    throw new CraaftError("failed to read file: {$path}");
                }
                $name = $filename ?? basename($path);
                $ct = $contentType ?? ($this->guessContentType($name) ?? 'application/octet-stream');
            } else {
                // Treat the string as raw bytes.
                $contents = $file;
                $name = $filename ?? 'attachment';
                $ct = $contentType ?? ($this->guessContentType($name) ?? 'application/octet-stream');
            }
        }

        if ($contents === '') {
            throw new CraaftError('cannot upload an empty file');
        }
        if (strlen($contents) > self::MAX_UPLOAD_BYTES) {
            throw new CraaftError('file exceeds the 25 MiB upload limit');
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
            '/cards/' . Id::segment($cardId) . '/attachments',
            null,
            null,
            $files,
        );
        return Attachment::fromApi(is_array($data) ? $data : []);
    }

    /** Download attachment bytes. */
    public function download(string $attachmentId): string
    {
        $data = $this->transport->request(
            'GET',
            '/attachments/' . Id::segment($attachmentId),
            null,
            null,
            null,
            false,
            self::MAX_DOWNLOAD_BYTES,
        );
        if (!is_string($data)) {
            throw new CraaftError('expected binary response body for attachment download');
        }
        return $data;
    }

    public function delete(string $attachmentId): void
    {
        $this->transport->request('DELETE', '/attachments/' . Id::segment($attachmentId));
    }

    private function guessContentType(string $filename): ?string
    {
        $info = @mime_content_type($filename);
        return is_string($info) && $info !== '' ? $info : null;
    }
}
