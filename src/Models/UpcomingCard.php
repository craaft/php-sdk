<?php

declare(strict_types=1);

namespace Craaft\Models;

/**
 * One GET /cards/upcoming row (also the `due` bucket of GET /cards/focus).
 *
 * Extends `CardSummary` (rather than being a standalone class) so code
 * already typed against `CardSummary` keeps working. `description`,
 * `updatedAt` and `archived` are always their defaults (null / false) here -
 * the upcoming handler never sends them - kept only so old code reading
 * them off a shared `CardSummary` value does not fault on access. `dueDate`
 * is always set; `assignedUserId`, `assignedUserName` and `priority` are
 * omitted from the wire response (not null) when the card doesn't have
 * them, which `fromApi()` already treats the same as an explicit null.
 */
readonly class UpcomingCard extends CardSummary {}
