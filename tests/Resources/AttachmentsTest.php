<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Exceptions\NotFoundError;
use Craaft\Exceptions\PlanLimitError;
use Craaft\Exceptions\ValidationError;
use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class AttachmentsTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function att(): array
    {
        return [
            'id' => 'att1',
            'cardId' => 'card1',
            'name' => 'screenshot.png',
            'size' => 1234,
            'contentType' => 'image/png',
            'uploadedBy' => 'u1',
            'uploadedByName' => 'Alice',
            'createdAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testListForCard(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, [$this->att()]);
        $rows = $b->client()->attachments->listForCard('card1');
        $this->assertSame('screenshot.png', $rows[0]->name);
        $this->assertSame(1234, $rows[0]->size);
    }

    public function testUploadBytes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(201, $this->att());
        $att = $b->client()->attachments->upload('card1', 'png-bytes', filename: 'screenshot.png');
        $this->assertSame('att1', $att->id);
        $call = $b->stub()->lastCall();
        $hasMultipartHeader = false;
        foreach ($call['headers'] as $h) {
            if (str_starts_with($h, 'Content-Type: multipart/form-data')) {
                $hasMultipartHeader = true;
            }
        }
        $this->assertTrue($hasMultipartHeader);
        $this->assertStringContainsString('screenshot.png', $call['body']);
    }

    public function testUpload402RaisesPlanLimit(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(402, ['error' => 'uploads are a paid feature']);
        $this->expectException(PlanLimitError::class);
        $b->client()->attachments->upload('card1', 'x', filename: 'a.txt');
    }

    public function testUploadEmptyRaises(): void
    {
        $b = new ClientBuilder();
        $this->expectException(\Craaft\Exceptions\CraaftError::class);
        $this->expectExceptionMessageMatches('/empty/');
        $b->client()->attachments->upload('card1', '', filename: 'a.txt');
    }

    public function testUploadTooLargeRaises(): void
    {
        $b = new ClientBuilder();
        $this->expectException(\Craaft\Exceptions\CraaftError::class);
        $this->expectExceptionMessageMatches('/25 MiB/');
        $b->client()->attachments->upload('card1', str_repeat('x', 25 * 1024 * 1024 + 1), filename: 'big.bin');
    }

    public function testDownloadReturnsBytes(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(200, "HTTP/1.1 200 OK\r\nContent-Type: image/png\r\n\r\n", 'file-bytes'));
        $data = $b->client()->attachments->download('att1');
        $this->assertSame('file-bytes', $data);
    }

    public function testDownload404RaisesNotFound(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(404, ['error' => 'gone']);
        $this->expectException(NotFoundError::class);
        $b->client()->attachments->download('missing');
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $this->assertNull($b->client()->attachments->delete('att1'));
    }

    public function testUpload413RaisesValidation(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(413, ['error' => 'file exceeds 25 MiB limit']);
        $this->expectException(ValidationError::class);
        $b->client()->attachments->upload('card1', 'x', filename: 'big.bin');
    }
}
