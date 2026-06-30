<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Models\User;

/** Endpoints under /me. */
final class MeResource extends BaseResource
{
    public function get(): User
    {
        $data = $this->transport->request('GET', '/me');
        return User::fromApi($this->ensureArray($data));
    }

    public function update(
        ?string $name = null,
        ?string $email = null,
        ?string $username = null,
    ): User {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($email !== null) {
            $body['email'] = $email;
        }
        if ($username !== null) {
            $body['username'] = $username;
        }
        $data = $this->transport->request('PATCH', '/me', null, $body);
        return User::fromApi($this->ensureArray($data));
    }

    private function ensureArray(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }
}