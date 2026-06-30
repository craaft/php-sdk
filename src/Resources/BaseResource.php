<?php

declare(strict_types=1);

namespace Craaft\Resources;

use Craaft\Http\Transport;

/** Shared base for resource sub-clients. */
abstract class BaseResource
{
    public function __construct(
        protected readonly Transport $transport,
    ) {}
}