<?php

declare(strict_types=1);

namespace Craaft\Tests\Resources;

use Craaft\Tests\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class MeTest extends TestCase
{
    private function user(): array
    {
        return [
            'id' => 'u1', 'email' => 'a@b.co', 'name' => 'Alice', 'username' => 'alice',
            'avatarUrl' => null, 'hasPassword' => true,
        ];
    }

    public function testGet(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->user());
        $u = $b->client()->me->get();
        $this->assertSame('Alice', $u->name);
    }

    public function testUpdateOmitsUnspecified(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->user());
        $b->client()->me->update(name: 'Bob');
        $this->assertSame(['name' => 'Bob'], json_decode($b->stub()->lastCall()['body'], true));
        $this->assertSame('PATCH', $b->stub()->lastCall()['method']);
    }

    public function testUpdateAllFields(): void
    {
        $b = new ClientBuilder();
        $b->stub()->enqueueJson(200, $this->user());
        $b->client()->me->update(name: 'Bob', email: 'b@b.co', username: 'bob');
        $this->assertSame(
            ['name' => 'Bob', 'email' => 'b@b.co', 'username' => 'bob'],
            json_decode($b->stub()->lastCall()['body'], true),
        );
    }
}
