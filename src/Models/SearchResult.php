<?php

declare(strict_types=1);

namespace Craaft\Models;

/**
 * One GET /search hit.
 *
 * Extends `CardSummary` (rather than being a standalone class) so code
 * already typed against `CardSummary` keeps working. `dueDate`,
 * `assignedUserId`, `assignedUserName` and `priority` are always null here -
 * the search handler never sends them - kept only so old code reading them
 * off a shared `CardSummary` value does not fault on access. What is
 * actually populated: `description` (a snippet, at most 180 characters,
 * not the full body), `updatedAt`, and `archived`.
 */
readonly class SearchResult extends CardSummary {}
