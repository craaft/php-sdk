<?php

declare(strict_types=1);

namespace Craaft\Exceptions;

/** 400 / 413 / 422 - request body or query parameters are invalid. */
class ValidationError extends CraaftApiError {}
