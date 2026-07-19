# Craaft PHP SDK

A small, synchronous PHP client for the [Craaft API](https://craaft.io). It wraps the REST
endpoints with readonly typed DTOs, a sensible retry policy, and a friendly exception
hierarchy. Mirrors the feature set of the official Python SDK.

## Install

```bash
composer require craaft/craaft
```

PHP 8.2 or newer. Only `ext-curl`, `ext-json`, and `ext-mbstring` are required - no
 Guzzle, no PSR-18, nothing else to install.

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

// upcoming() and search() return CardSummary previews, not full cards.
foreach ($client->cards->upcoming() as $summary) {
    echo "{$summary->title} — {$summary->dueDate?->format('c')} ({$summary->projectName})\n";
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
development — anything else must be `https://` to avoid leaking the bearer token.

## Resources

| Sub-client           | Methods |
|----------------------|---------|
| `$client->me`        | `get()`, `update(name:, email:, username:)` |
| `$client->projects`  | `list()`, `get($id)`, `create($name, $description)`, `update($id, ...)`, `delete($id)`, `export($id)`, `listTags($id)`, `enableShare($id)`, `disableShare($id)`, `listCards($id)`, `createCard($id, ...)`, `createCards($id, $cards)`, `addColumn($id, $title)`, `listMilestones($id)`, `addMilestone($id, $name, $dueOn)`, `listMembers($id)`, `addMember($id, $userId, $role)`, `updateMember($id, $userId, $role)`, `removeMember($id, $userId)` |
| `$client->cards`     | `update($id, ...)`, `bulkUpdate($cards)`, `bulkMove($ids, $column, $targetProjectId)`, `delete($id)`, `move($id, $targetProjectId, $column)`, `upcoming()`, `focus()`, `hygiene($type)`, `listEvents($id)`, `search($q, $limit=20)`, `listComments($id)`, `addComment($id, $body)`, `listChecklist($id)`, `addChecklistItem($id, $text)` |
| `$client->attachments` | `listForCard($cardId)`, `upload($cardId, $file, $filename, $contentType)`, `download($attachmentId)`, `delete($attachmentId)` |
| `$client->comments`  | `update($id, $body)`, `delete($id)` |
| `$client->checklist` | `update($id, text:, done:)`, `delete($id)` |
| `$client->columns`   | `update($id, ...)`, `delete($id)`, `archive($id)` |
| `$client->milestones` | `update($id, name:, dueOn:, achieved:)`, `delete($id)` |
| `$client->members`   | `list()`, `listInvitations()`, `createInvitation($email, $role, $boardGrants)` |

`upcoming()` and `search()` return `array<CardSummary>` - lightweight previews.
`focus()` returns a `FocusResponse` with `due`, `attention`, and `hygiene` buckets.

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
    ['id' => $a->id, 'title' => 'Renamed', 'priority' => Priority::Urgent],
    ['id' => $b->id, 'dueDate' => null],
]);

// Bulk move: same board by default; pass targetProjectId to change boards.
$client->cards->bulkMove([$a->id, $b->id], column: 'done');
```

DateTimeInterface values and `Priority` enums inside bulk items are serialized
for you; everything else is sent as-is.

## Models

Readonly value objects, one class per schema. Highlights:

- `User`, `Project`, `Column`, `Card`, `Comment`, `Attachment`
- `ChecklistItem`, `Milestone`
- `CardSummary`, `AttentionCard`, `FocusResponse`, `HygieneCounts`, `CardEvent`
- `BoardMember`, `BoardMemberGrant`, `WorkspaceMember`, `Invitation`
- `ProjectExport` (+ nested export types)

Finite string fields are backed enums: `Priority` (`low`/`medium`/`high`/`urgent`),
`BoardRole` (`admin`/`contributor`), `WorkspaceRole`, `Visibility`, `TextColor`,
`BoardMemberSource`, `HygieneType`, `CardEventType`. Unknown enum values from the
server resolve to `null` (forward-compatible).

`Card->size` is an optional **integer** estimate. Set metadata via `cards->update()`
after `createCard()` - the create endpoint only accepts `title`, `column`,
`position`, and optional `description`. (Bulk `createCards()` accepts the full
metadata set per item.)

`Milestone->dueOn` is a plain `YYYY-MM-DD` string (no time component);
`Milestone->achievedAt` is a nullable `DateTimeImmutable`. Any board member may
read milestones, but writes are board-admin only - non-admin members get a
`PermissionError` (403). Checklist items may be added, edited, and deleted by
any board member.

`attachments->upload()` sends multipart form data (max **25 MiB** per file) and
requires a Pro/Team workspace; check `Project->canUploadAttachments` first. It
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

## Development

```bash
composer install
composer test           # or: vendor/bin/phpunit
```

## License

MIT
