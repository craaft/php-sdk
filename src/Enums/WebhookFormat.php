<?php

declare(strict_types=1);

namespace Craaft\Enums;

/** Outbound webhook delivery format. */
enum WebhookFormat: string
{
    /** Signed JSON envelope; verify with X-Craaft-Signature. */
    case Craaft = 'craaft';
    /** A Slack Incoming Webhook message, unsigned. */
    case Slack = 'slack';
    /** A Discord webhook message, unsigned. */
    case Discord = 'discord';
}
