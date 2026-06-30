<?php

declare(strict_types=1);

namespace Craaft\Tests;

use Craaft\CraaftClient;
use Craaft\Tests\Http\StubExecutor;

/** Builds a CraaftClient wired to a StubExecutor for resource tests. */
final class ClientBuilder
{
    private StubExecutor $stub;

    public function __construct()
    {
        $this->stub = new StubExecutor();
    }

    public function stub(): StubExecutor
    {
        return $this->stub;
    }

    public function client(): CraaftClient
    {
        return new CraaftClient(apiKey: 'cra_x', http: $this->stub);
    }
}
