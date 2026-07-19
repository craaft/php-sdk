<?php

declare(strict_types=1);

namespace Craaft\Exceptions;

/** Network failure (DNS, TLS, connection refused, broken pipe). */
class ConnectionError extends CraaftError {}
