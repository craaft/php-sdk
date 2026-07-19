<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Exceptions\PermissionError;
use Craaft\Http\HttpAttempt;
use Craaft\Tests\ClientBuilder;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class MilestonesTest extends TestCase
{
    private const BASE = 'https://craaft.io/api/v1';

    private function milestone(): array
    {
        return [
            'id' => 'm1',
            'projectId' => 'p1',
            'name' => 'Beta launch',
            'dueOn' => '2026-08-01',
            'achievedAt' => null,
            'createdAt' => '2026-05-08T10:00:00Z',
            'updatedAt' => '2026-05-08T10:00:00Z',
        ];
    }

    public function testUpdateName(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->milestone(), ['name' => 'GA launch']));
        $m = $b->client()->milestones->update('m1', name: 'GA launch');
        $call = $b->stub()->lastCall();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame(self::BASE . '/milestones/m1', $call['url']);
        $this->assertSame(['name' => 'GA launch'], json_decode($call['body'], true));
        $this->assertSame('GA launch', $m->name);
    }

    public function testUpdateDueOnFromDatetime(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->milestone());
        $b->client()->milestones->update(
            'm1',
            dueOn: new DateTimeImmutable('2026-08-01T15:30:00', new DateTimeZone('UTC')),
        );
        $this->assertSame(['dueOn' => '2026-08-01'], json_decode($b->stub()->lastCall()['body'], true));
    }

    public function testUpdateAchievedStampsAchievedAt(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, array_merge($this->milestone(), [
            'achievedAt' => '2026-07-18T09:00:00Z',
        ]));
        $m = $b->client()->milestones->update('m1', achieved: true);
        $this->assertSame(['achieved' => true], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertNotNull($m->achievedAt);
    }

    public function testUpdateAchievedFalseClearsStamp(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->milestone());
        $m = $b->client()->milestones->update('m1', achieved: false);
        $this->assertSame(['achieved' => false], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertNull($m->achievedAt);
    }

    public function testUpdate403RaisesPermissionError(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(403, ['error' => 'board admin required']);
        $this->expectException(PermissionError::class);
        $b->client()->milestones->update('m1', achieved: true);
    }

    public function testDelete(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueue(new HttpAttempt(204, "HTTP/1.1 204 No Content\r\n\r\n", ''));
        $this->assertNull($b->client()->milestones->delete('m1'));
        $call = $b->stub()->lastCall();
        $this->assertSame('DELETE', $call['method']);
        $this->assertSame(self::BASE . '/milestones/m1', $call['url']);
    }
}
