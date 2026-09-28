# Craaft PHP SDK

The official PHP client for [Craaft](https://craaft.io), the kanban board for one
person or a small team who wants to see the work, not administer the tool.

Drive the same boards, cards, comments and members you see in the app: readonly
typed DTOs instead of associative arrays, retries that honour `Retry-After`, and
exceptions you can catch by failure type instead of by status code. Every call
runs as the user who minted the token, with exactly the permissions they have in
the UI.

## Install

```bash
composer require craaft/craaft
```

PHP 8.2 or newer. `ext-curl`, `ext-json` and `ext-mbstring` are the entire
dependency list: no Guzzle, no PSR-18, nothing else to install.

## Get a token

Open **Settings → API keys** in Craaft, or go straight to
[craaft.io/settings/api-keys](https://craaft.io/settings/api-keys).

A token looks like `cra_...` and is shown exactly once, so copy it before you
close the dialog. The `cra_` prefix is deliberate: it makes an accidental commit
scannable by tooling like GitHub Push Protection.

```bash
export CRAAFT_API_TOKEN=cra_...
```

The client reads that variable by default, so nothing in your code has to hold
the token.

## Quickstart

```php
use Craaft\CraaftClient;
use Craaft\Enums\Priority;

// Reads CRAAFT_API_TOKEN (and optionally CRAAFT_BASE_URL) from the environment.
$client = new CraaftClient();

$me = $client->me->get();
echo "Hi {$me->name}\n";

$project = $client->projects->create('Demo', description: 'A new board');

$card = $client->projects->createCard(
    $project->id,
    title: 'Ship the SDK',
    column: 'todo',
    position: 1.0,
    description: 'all the bits',
);

// Metadata (priority, due date, size, tags) is set via PATCH after create.
$client->cards->update(
    $card->id,
    priority: Priority::High,
    size: 3,
    dueDate: new DateTimeImmutable('+7 days', new DateTimeZone('UTC')),
);

$client->cards->addComment($card->id, body: 'lgtm');

// upcoming() and search() return lightweight previews, not full cards.
foreach ($client->cards->upcoming() as $summary) {
    echo "{$summary->title} - {$summary->dueDate?->format('c')} ({$summary->projectName})\n";
}

$client->close();
```

## Configuration

```php
use Craaft\CraaftClient;
use Craaft\Http\RetryConfig;

$client = new CraaftClient(
    apiKey: 'cra_...',                     // or CRAAFT_API_TOKEN env var
    baseUrl: 'https://craaft.io/api/v1',   // or CRAAFT_BASE_URL env var (default: prod)
    timeout: 30.0,                         // seconds, or [connect, read] tuple
    retry: new RetryConfig(maxAttempts: 5), // or new RetryConfig(maxAttempts: 1) to disable
    userAgent: 'my-app/1.0',
);
```

`http://` base URLs are only allowed for `localhost` / `127.0.0.1` / `::1` during
development - anything else must be `https://` to avoid leaking the bearer token.

## Resources

| Sub-client           | Methods |
|----------------------|---------|
| `$client->me`        | `get()`, `update(name:, email:, username:)` |
| `$client->projects`  | `list()`, `get($id)`, `create($name, $description, $template)`, `listTemplates()`, `update($id, ...)`, `delete($id)`, `export($id)`, `exportCsv($id)`, `listTags($id)`, `enableShare($id)`, `disableShare($id)`, `listCards($id)`, `listArchivedCards($id)`, `createCard($id, ...)`, `createCards($id, $cards)`, `rebalanceCards($id, $ids, $column)`, `uploadBackground($id, $file)`, `downloadBackground($id)`, `deleteBackground($id)`, `addColumn($id, $title)`, `listMilestones($id)`, `addMilestone($id, $name, $dueOn)`, `listMembers($id)`, `addMember($id, $userId, $role)`, `updateMember($id, $userId, $role)`, `removeMember($id, $userId)`, `listWebhooks($id)`, `createWebhook($id, $url, ...)` |
| `$client->cards`     | `get($id)`, `detail($id)`, `update($id, ...)`, `bulkUpdate($cards)`, `bulkMove($ids, $column, $targetProjectId)`, `delete($id)`, `move($id, $targetProjectId, $column)`, `archive($id)`, `restore($id)`, `follow($id)`, `unfollow($id)`, `upcoming()`, `focus()`, `hygiene($type)`, `listEvents($id)`, `search($q, $limit=20)`, `listComments($id)`, `addComment($id, $body)`, `listChecklist($id)`, `addChecklistItem($id, $text)` |
| `$client->attachments` | `listForCard($cardId)`, `upload($cardId, $file, $filename, $contentType)`, `download($attachmentId)`, `delete($attachmentId)` |
| `$client->comments`  | `update($id, $body)`, `delete($id)` |
| `$client->checklist` | `update($id, text:, done:)`, `delete($id)` |
| `$client->columns`   | `update($id, ...)`, `delete($id)`, `archive($id)`, `archiveWithIds($id)` |
| `$client->milestones` | `update($id, name:, dueOn:, achieved:)`, `delete($id)` |
| `$client->members`   | `list()`, `updateRole($userId, $role)`, `remove($userId)`, `listInvitations()`, `createInvitation($email, $role, $boardGrants)`, `revokeInvitation($id)` |
| `$client->webhooks`  | `update($id, ...)`, `delete($id)` |
| `$client->inboundEmail` | `get($projectId)`, `enable($projectId, $targetColumn)`, `update($projectId, ...)`, `disable($projectId)` |
| `$client->public`    | `board($token)`, `boardBackground($token)`, `avatar($userId)` - no auth required |

`search()` returns `array<SearchResult>` and `upcoming()` (and `focus()->due`)
return `array<UpcomingCard>` - both are lightweight, endpoint-specific previews
that extend the older shared `CardSummary` type, so code already typed against
`CardSummary` keeps working.
`focus()` returns a `FocusResponse` with `due`, `attention`, and `hygiene` buckets.
`$client->version()` returns the server's build info and needs no auth, which
makes it a cheap liveness probe.

The one endpoint the SDK deliberately does not wrap is `GET /projects/{id}/events`.
That is the realtime SSE stream, not an activity log: it opens a
`text/event-stream` that never completes, so a request/response client would
simply hang on it. Per-card history is `cards->listEvents($id)`, which is
ordinary JSON.

### Following and board maintenance

```php
$card = $client->cards->get($cardId);      // single fetch, no board scan
$detail = $client->cards->detail($cardId); // card + comments + events + checklist + attachments, one call
if (!$card->following) {
    $client->cards->follow($cardId);       // idempotent, returns void
}

// Only when repeated midpoint inserts have run out of room between two
// neighbours. Request order becomes positions 1, 2, 3, ...
$client->projects->rebalanceCards($project->id, $orderedIds, 'doing');
```

### Board templates

```php
foreach ($client->projects->listTemplates() as $template) {
    echo "{$template->key}: {$template->name} - {$template->description}\n";
}

$project = $client->projects->create('Sprint 12', template: 'sprint');
```

`template` is optional; omit it (or pass an empty string) for the default
Kanban layout (`To Do` / `In Progress` / `Done`). An unknown key raises
`ValidationError`. Any authenticated user may call `listTemplates()` - it is
a fixed, code-defined catalogue, not per-workspace state. The create response doesn't include the board's columns; call `$client->projects->get()` on the new id to read their keys (only the default template uses `todo` / `doing` / `done`).

### Archiving cards

```php
$client->cards->archive($card->id);   // off the board, still searchable
$restored = $client->cards->restore($card->id); // back where it was archived from

foreach ($client->projects->listArchivedCards($project->id) as $row) {
    echo "{$row->card->title} archived at {$row->archivedAt->format('c')}\n";
}

// Bulk-archive every live card in a Done column - just the count.
$count = $client->columns->archive($doneColumn->id);

// Same call, but with the archived card ids so you can offer an undo.
$result = $client->columns->archiveWithIds($doneColumn->id);
foreach ($result->ids as $id) {
    // $client->cards->restore($id) to undo
}
```

`archive()` is not idempotent - archiving an already-archived card raises
`NotFoundError`, same as a card that does not exist or is on a board you
cannot reach. `columns->archive()` and `columns->archiveWithIds()` only
affect a column flagged `isDone`; any other column answers with a count of
`0` (and no ids) rather than an error.

### Board backgrounds

```php
$project = $client->projects->uploadBackground($project->id, 'hero.png');
$raw = $client->projects->downloadBackground($project->id);
$client->projects->deleteBackground($project->id);
```

Board admins only, max 10 MiB, and PNG / JPEG / WebP / GIF only - the server
sniffs the leading bytes as well as the declared type, so a renamed file is
rejected. A background and a `backgroundColor` are mutually exclusive.

### Outbound webhooks

```php
use Craaft\Enums\WebhookFormat;

$webhook = $client->projects->createWebhook(
    $project->id,
    'https://example.com/hooks/craaft',
    description: 'Ops alerts',
    events: ['card.created', 'card.archived'], // omit for every event
);
echo $webhook->secret; // signs the payload via X-Craaft-Signature

$list = $client->projects->listWebhooks($project->id);
echo count($list->webhooks), ' of ', count($list->eventCatalogue), ' event types subscribed';

$client->webhooks->update($webhook->id, active: false);
$client->webhooks->delete($webhook->id);
```

Board-admin only, Pro boards only (`listWebhooks()`/`createWebhook()` and
`WebhooksResource::update()` raise `PlanLimitError` on a Free board; `delete()`
is not plan-gated so a downgraded board can still clean up). `$format`
defaults to `WebhookFormat::Craaft` (a signed JSON envelope); `Slack` and
`Discord` POST an unsigned message to webhook URLs registered with those
services. `secret` is returned on every read, not shown once - `craaft`-format
deliveries carry it as `X-Craaft-Signature`; Slack/Discord deliveries ignore
it. `WebhookSubscription->recentDeliveries` holds the last few attempts,
most recent first.

### Email to card

```php
$status = $client->inboundEmail->get($project->id);
if (!$status->enabled) {
    $address = $client->inboundEmail->enable($project->id, targetColumn: 'todo');
    echo "Forward mail to {$address->email}\n";
}

$client->inboundEmail->update($project->id, rotate: true); // old address stops working
$client->inboundEmail->disable($project->id);
```

Board-admin only, Pro boards only (`get()`/`enable()`/`update()` raise
`PlanLimitError` on a Free board; `disable()` is not plan-gated). `enable()`
raises `ConflictError` (409) if the board already has an address - use
`update()` to change it. `update($projectId, targetColumn: '')` clears the
target column back to the board's first column.

### Public boards

```php
$board = $client->public->board($shareToken);   // no auth needed
echo $board->project->name, ' ', count($board->cards);
```

The share token is the access check. Revoking sharing, or re-enabling it (which
mints a fresh token), invalidates old links immediately and raises
`NotFoundError`. The snapshot is trimmed: no workspace or ownership fields, and
no card metadata beyond priority and assignee.

## Bulk operations

Three endpoints batch up to 100 cards into one all-or-nothing transaction.
One invalid item rolls back the whole batch and the server's `ValidationError`
names the offending index (`cards[3]: title is required`). Bulk requests never
send notification emails.

```php
// Bulk create: title + column required per item; position omitted = append.
// Unlike createCard(), the assignee is NOT defaulted to the caller.
$cards = $client->projects->createCards($project->id, [
    ['title' => 'Ship it', 'column' => 'todo'],
    ['title' => 'Review copy', 'column' => 'doing', 'priority' => Priority::High,
     'dueDate' => new DateTimeImmutable('+3 days'), 'tags' => ['launch']],
]);

// Bulk update: items are passed through verbatim, so all three field states
// work - a present key is applied, `'dueDate' => null` clears the field, and
// an absent key leaves it alone. (The single-card update() treats null args
// as "omit", so use bulkUpdate() when you need to clear a field.)
$client->cards->bulkUpdate([
    ['id' => $cards[0]->id, 'title' => 'Renamed', 'priority' => Priority::Urgent],
    ['id' => $cards[1]->id, 'dueDate' => null],
]);

// Bulk move: same board by default; pass targetProjectId to change boards.
$client->cards->bulkMove([$cards[0]->id, $cards[1]->id], column: 'done');
```

DateTimeInterface values and `Priority` enums inside bulk items are serialized
for you; everything else is sent as-is.

## Models

Readonly value objects, one class per schema. Highlights:

- `User`, `Project`, `Column`, `Card`, `CardDetail`, `Comment`, `Attachment`
- `ChecklistItem`, `Milestone`
- `CardSummary` (base of `SearchResult` and `UpcomingCard`), `AttentionCard`,
  `FocusResponse`, `HygieneCounts`, `CardEvent`, `ArchivedCard`
- `BoardTemplate` (+ `BoardTemplateColumn`)
- `BoardMember`, `BoardMemberGrant`, `WorkspaceMember`, `Invitation`
- `ColumnArchiveResult`
- `WebhookSubscription` (+ `WebhookDelivery`), `WebhookList`
- `InboundAddress`, `InboundEmailStatus`
- `ProjectExport` (+ nested export types)
- `PublicBoard` (+ `PublicBoardProject`, `PublicBoardColumn`, `PublicBoardCard`)

Finite string fields are backed enums: `Priority` (`low`/`medium`/`high`/`urgent`),
`BoardRole` (`admin`/`contributor`), `WorkspaceRole`, `Visibility`, `TextColor`,
`BoardMemberSource`, `HygieneType`, `CardEventType`, `WebhookFormat`
(`craaft`/`slack`/`discord`). Unknown enum values from the server resolve to
`null` (forward-compatible) for the optional fields that used that pattern
before (`Priority`, etc.); a required role-like field (`BoardRole`,
`WorkspaceRole`, `CardEventType`, `WebhookFormat`) raises
`InvalidArgumentException` instead, since there is no sensible default to
fall back to.

`SearchResult` (from `cards->search()`) and `UpcomingCard` (from
`cards->upcoming()` and `focus()->due`) extend `CardSummary` rather than
replacing it, so code already typed against the shared `CardSummary` class
keeps compiling. Each carries every `CardSummary` field, but only some are
ever populated for a given endpoint: `SearchResult->dueDate`,
`->assignedUserId`, `->assignedUserName` and `->priority` are always null;
`UpcomingCard->description`, `->updatedAt` and `->archived` are always their
defaults (null / false). Read the field that matches the endpoint you called.

`Invitation->consumed` is `true` when `createInvitation()` found an existing
verified account with that email and added it to the workspace immediately,
instead of leaving a pending invite - it is always `false` on
`listInvitations()`, which only lists pending ones.

`Card->following` is whether **the authenticated caller** follows the card, so
it differs per token for the same card. `Card->size` is an optional **integer**
estimate. Set metadata via `cards->update()`
after `createCard()` - the create endpoint only accepts `title`, `column`,
`position`, and optional `description`. (Bulk `createCards()` accepts the full
metadata set per item.)

`Milestone->dueOn` is a plain `YYYY-MM-DD` string (no time component);
`Milestone->achievedAt` is a nullable `DateTimeImmutable`. Any board member may
read milestones, but writes are board-admin only - non-admin members get a
`PermissionError` (403). Checklist items may be added, edited, and deleted by
any board member.

`attachments->upload()` sends multipart form data (max **25 MiB** per file) and
requires a Pro workspace; check `Project->canUploadAttachments` first. It
accepts a filesystem path, a `SplFileInfo`, or a string of raw bytes.

## Errors

```php
use Craaft\Exceptions\CraaftApiError;
use Craaft\Exceptions\NotFoundError;
use Craaft\Exceptions\RateLimitError;

try {
    $client->projects->get('missing');
} catch (NotFoundError $e) {
    // 404
} catch (RateLimitError $e) {
    usleep((int) (($e->retryAfter ?? 1.0) * 1_000_000));
} catch (CraaftApiError $e) {
    echo $e->statusCode, ' ', $e->getMessage(), ' ', $e->requestId ?? '';
}
```

Hierarchy: `CraaftError` is the root. API failures raise `CraaftApiError` or one of
its subclasses (`AuthenticationError`, `PermissionError`, `NotFoundError`,
`ConflictError`, `PlanLimitError`, `ValidationError`, `RateLimitError`,
`ServerError`). Network failures raise `ConnectionError` or `TimeoutError`.

`CraaftApiError` exposes `statusCode`, `responseBody`, `requestId`, and
`getMessage()` returns the server's parsed `error` string. `RateLimitError` adds
`retryAfter` (seconds, possibly fractional; null when not supplied).

## Retries

The client retries `429`, `502`, `503`, `504`, and network errors with exponential
backoff and `Retry-After`-aware pauses. Writes (`POST`/`PATCH`/`PUT`/`DELETE`) skip
5xx retries by default, since the server may have applied the change before
responding. Pass `new RetryConfig(retryWritesOn5xx: true)` if your workload is safe
to retry. To disable retries entirely, pass `new RetryConfig(maxAttempts: 1)`.

## Reference

The API this client wraps is documented at
[craaft.io/openapi.yaml](https://craaft.io/openapi.yaml), which the running
server publishes directly, so it always matches the deployment you are talking
to. There is a Python client at
[github.com/craaft/python-sdk](https://github.com/craaft/python-sdk) covering the
same surface.

Found a bug or a missing endpoint?
[Open an issue](https://github.com/craaft/php-sdk/issues).

## Development

```bash
composer install
composer test           # or: vendor/bin/phpunit
composer cs-check       # php-cs-fixer, dry run; cs-fix to apply
```

## License

MIT
