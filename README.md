# omnifox/sdk

Official PHP SDK for the [Omnifox.io](https://omnifox.io) REST API.

Omnifox is an omnichannel customer messaging platform (WhatsApp, Instagram, Messenger, Telegram, TikTok, email, webchat, SMS) with a shared inbox, AI agents, CRM, boards, calendar, product catalog, orders and quotes. This SDK wraps the v1 REST API documented at <https://omnifox.io/docs>.

- PHP 8.1+, PSR-18 transport (Guzzle by default), PSR-4 autoloading.
- Same 25 resources and method names as the [Node](https://www.npmjs.com/package/@omnifox/sdk) and [Python](https://pypi.org/project/omnifox/) SDKs, in idiomatic camelCase PHP.
- Typed exceptions, automatic retries on 429 / 5xx, and a lazy iterator for every listing.
- Verified end to end against a live workspace: **212 calls (every public method except the irreversible `channels->create/disconnect`, `broadcasts->create` and `workspaces->create/delete`), 0 failures** before release (see `tests/e2e`).

## Install

```bash
composer require omnifox/sdk
```

The package is on Packagist: <https://packagist.org/packages/omnifox/sdk>. If Packagist is not reachable from your network, install it straight from GitHub:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/omnifoxapp-code/omnifox-sdk-php" }
    ],
    "require": { "omnifox/sdk": "^0.4" }
}
```

## Quickstart (plain PHP)

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Omnifox\OmnifoxClient;

$omnifox = new OmnifoxClient([
    'apiKey' => getenv('OMNIFOX_API_KEY'),
    'workspaceId' => 1,                                  // optional, see "Workspaces"
    // 'baseUrl' => 'https://app.omnifox.io/api/v1',     // default
    // 'timeout' => 30,                                  // seconds
    // 'maxRetries' => 3,                                // on 429 / 5xx / connection errors
]);

// Look a contact up by id, email, phone or external id.
$contact = $omnifox->contacts->get('email:ada@example.com');

// Send a WhatsApp text.
$omnifox->messages->send([
    'contact' => 'phone:+593987654321',
    'channel_id' => 5,
    'text' => 'Hello from the Omnifox PHP SDK',
]);

// Walk every open conversation; pages are fetched on demand.
foreach ($omnifox->conversations->list(['status' => 'open']) as $conversation) {
    echo $conversation['id'], PHP_EOL;
}
```

Results are associative arrays, already unwrapped from the API's `{success, message, data}` envelope.

## Laravel

Add the credentials to `config/services.php`:

```php
'omnifox' => [
    'key' => env('OMNIFOX_API_KEY'),
    'workspace_id' => env('OMNIFOX_WORKSPACE_ID'),
],
```

Bind one client in `App\Providers\AppServiceProvider::register()`:

```php
use Omnifox\OmnifoxClient;

$this->app->singleton(OmnifoxClient::class, fn () => new OmnifoxClient([
    'apiKey' => config('services.omnifox.key'),
    'workspaceId' => config('services.omnifox.workspace_id'),
]));
```

Then inject it anywhere:

```php
use Omnifox\Exception\ValidationException;
use Omnifox\OmnifoxClient;

class SyncLeadToOmnifox
{
    public function __construct(private OmnifoxClient $omnifox) {}

    public function handle(Lead $lead): void
    {
        try {
            $contact = $this->omnifox->contacts->upsert([
                'unique_by' => ['email'],
                'email' => $lead->email,
                'display_name' => $lead->name,
            ], ['idempotencyKey' => "lead-{$lead->id}"]);

            $this->omnifox->contacts->attachTagsByName($contact['id'], ['web-lead']);
        } catch (ValidationException $e) {
            logger()->warning('Omnifox rejected the lead', $e->getFieldErrors());
        }
    }
}
```

## Authentication

Create a token in Omnifox under **Settings > API keys** and pass it as `apiKey`. The SDK sends `Authorization: Bearer <token>` on every request.

## Workspaces

Tokens from the API-keys screen are pinned to one workspace, and the server resolves it from the token, so `workspaceId` is optional for them. If you do set it, it must match the token's workspace (a mismatch is a deliberate 403).

Set it anyway when you can: four endpoints validate `workspace_id` as a **required body field** however the token is scoped: `users->setAgentStatus()`, `teams->create()`, `channels->create()` and `broadcasts->create()`. With `workspaceId` configured the SDK fills it in; without it those four throw `Omnifox\Exception\InvalidArgumentException` before any request is sent, instead of an opaque 422.

The workspace travels as the `workspace_id` query parameter. The `X-Workspace-Id` header is not read by the API, so the SDK never sends it.

## Identifiers

Every contact method accepts a numeric id or a `kind:value` selector, URL-encoded for you:

```php
$omnifox->contacts->get(42);
$omnifox->contacts->get('email:ada@example.com');
$omnifox->contacts->get('phone:+593987654321');
$omnifox->contacts->get('external:crm-123');
```

## Pagination

`list()` returns a lazy `Omnifox\Pagination\Paginator`; iterate it and pages are fetched as needed. `listPage()` returns one `Omnifox\Pagination\Page`.

```php
foreach ($omnifox->contacts->list(['per_page' => 100]) as $contact) {
    // every contact of the workspace
}

$first25 = $omnifox->contacts->list()->toArray(25);

$page = $omnifox->contacts->listPage(['page' => 2]);
$page->data;      // list of rows
$page->hasMore;   // bool
$page->page;      // page mode: current page / lastPage / total
$page->nextCursor; // cursor mode
```

The API answers listings with four envelopes (plain, `paginated()` with top-level `meta`, a Laravel paginator inside `data`, and `?pagination=cursor`). The paginator understands all four: it asks for cursor mode and falls back to `?page=N` on endpoints that ignore it, so it never stops after the first page. It hard-stops at 500 pages.

## Errors

Every exception implements `Omnifox\Exception\OmnifoxException`.

| Exception | When |
|---|---|
| `AuthenticationException` | 401: token missing, wrong or revoked |
| `PaymentRequiredException` | 402: the plan or wallet does not cover it |
| `PermissionDeniedException` | 403: plan gate, token ability (`read:crm`, `write:projects`...), workspace mismatch |
| `NotFoundException` | 404: does not exist, or belongs to another workspace |
| `ConflictException` | 409: e.g. booking a calendar slot that is taken |
| `ValidationException` | 422: `getFieldErrors()` returns Laravel's field => messages map |
| `RateLimitException` | 429 after retries: `getRetryAfter()` in seconds |
| `ServerException` | 5xx after retries |
| `ApiException` | base class of all of the above: `getStatusCode()`, `getErrorCode()`, `getDetails()`, `getRequestId()`, `getBody()` |
| `NetworkException` | no HTTP response (DNS, TLS, refused, timeout) |
| `InvalidArgumentException` | rejected locally, before any request |

```php
use Omnifox\Exception\ConflictException;

try {
    $omnifox->appointments->book(['calendar_id' => 3, 'starts_at' => '2026-10-20T15:00:00Z']);
} catch (ConflictException $e) {
    // the slot was taken in the meantime: offer another one
}
```

429 and 5xx answers and connection failures are retried with exponential backoff and jitter (honouring `Retry-After`), `maxRetries` times (default 3). Timeouts are not retried.

## Per-call options

Every write method takes a last `$options` argument, as an array or a `RequestOptions`:

```php
$omnifox->orders->create($order, [
    'idempotencyKey' => $uuid,   // Idempotency-Key header
    'maxRetries' => 0,
    'timeout' => 10,             // seconds (default Guzzle transport only)
    'headers' => ['X-Trace-Id' => $traceId],
]);
```

For any route without a dedicated method there is an escape hatch with the same auth, workspace and retry handling: `$omnifox->request('GET', '/me/notifications')` (unwrapped) or `$omnifox->requestRaw(...)` (whole body).

## Resources

| Property | Methods |
|---|---|
| `contacts` | `list`, `listPage`, `get`, `search`, `listViaDsl`, `create`, `upsert`, `update`, `delete`, `merge`, `openConversation`, `closeConversation`, `assignConversation`, `setConversationStatus`, `comment`, `channels`, `attachTag`, `detachTag`, `attachTagsByName`, `detachTagsByName`, `sendMessage`, `listMessages`, `getMessage` |
| `conversations` | `list`, `listPage`, `get`, `create`, `assign`, `close`, `reopen`, `addNote`, `listMessages`, `sendMessage`, `getMessage`, `windowStatus` |
| `messages` | `send` |
| `channels` | `list`, `listPage`, `get`, `create`, `disconnect`, `health`, `templates` |
| `broadcasts` | `list`, `listPage`, `get`, `create`, `cancel` |
| `customFields` | `list`, `listPage`, `get`, `create`, `update`, `delete` |
| `tags`, `snippets`, `workspaces` | `list`, `listPage`, `get`, `create`, `update`, `delete` |
| `webhooks` | `list`, `listPage`, `get`, `create`, `update`, `delete`, `rotateSecret` |
| `workflows` | `list`, `listPage`, `get`, `activate`, `deactivate`, `trigger` |
| `users` | `me`, `logout`, `listAgents`, `listAgentsPage`, `getAgent`, `setAgentStatus` |
| `teams` | `list`, `listPage`, `get`, `create`, `update`, `delete`, `addMember`, `removeMember` |
| `companies` | `list`, `listPage`, `get`, `create`, `update`, `delete`, `attachContact`, `detachContact` |
| `deals` | `list`, `listPage`, `get`, `create`, `update`, `delete`, `moveToStage`, `close`, `timeline`, `board`, `bulk` |
| `pipelines` | `list`, `get`, `create`, `update`, `delete`, `createStage`, `updateStage`, `deleteStage` |
| `crmActivities` | `list`, `listPage`, `create`, `get`, `update`, `complete`, `delete` |
| `boards` | `list`, `get`, `view`, `columns`, `activity`, `create`, `update`, `delete`, `duplicate`, `createGroup` |
| `boardItems` | `create`, `get`, `update`, `delete`, `duplicate`, `move`, `setCellValue`, `listUpdates`, `addUpdate` |
| `appointments` | `listCalendars`, `listServices`, `slots`, `list`, `book`, `update`, `cancel` |
| `products` | `list`, `listPage`, `get`, `create`, `update`, `delete` |
| `orders` | `list`, `listPage`, `get`, `create`, `update`, `setStatus`, `recordPayment`, `delete` |
| `catalogSettings` | `get`, `update`, `applyPreset` |
| `quotes` | `list`, `listPage`, `get`, `create`, `update`, `delete`, `send`, `markViewed`, `accept`, `reject`, `pdf`, `billingEntities` |
| `reports` | `overview`, `conversations`, `agents`, `heatmap`, `crmFunnel`, `crmForecast`, `crmWinLossReasons` |

### Conversations

```php
// Assignment is an assignee_type + assignee_id pair: user | team | ai_agent | none.
$omnifox->conversations->assign($id, ['assignee_type' => 'team', 'assignee_id' => 3, 'only_online' => true]);
$omnifox->conversations->assign($id, []);   // unassign

// Closing reads `resolution` and `notes`.
$omnifox->conversations->close($id, ['resolution' => 'resolved', 'notes' => 'Refund sent']);

// WhatsApp / TikTok only allow free text for 24 h after the customer's last message.
if (!$omnifox->conversations->windowStatus($id)['open']) {
    // send an approved template instead
}
```

### Contacts

```php
$contact = $omnifox->contacts->create(['display_name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
$omnifox->contacts->merge(['primary_contact_id' => $keep, 'merged_contact_id' => $duplicate]);
$omnifox->contacts->attachTagsByName($contact['id'], ['vip', 'newsletter']);
$omnifox->contacts->detachTagsByName($contact['id'], ['newsletter']);   // a DELETE with a body
$omnifox->contacts->sendMessage($contact['id'], [
    'channelId' => 5,
    'message' => ['type' => 'text', 'text' => 'Hi Ada'],
]);
```

### CRM

```php
$pipeline = $omnifox->pipelines->create(['name' => 'Sales']);
$stage = $omnifox->pipelines->createStage($pipeline['id'], ['name' => 'Won', 'is_won' => true]);
$deal = $omnifox->deals->create(['title' => 'ACME renewal', 'pipeline_id' => $pipeline['id'], 'amount' => 1500]);
$omnifox->deals->moveToStage($deal['id'], $stage['id']);
$omnifox->crmActivities->create('deal', $deal['id'], ['type' => 'call', 'title' => 'Follow-up']);
$omnifox->deals->close($deal['id'], ['result' => 'won']);
```

CRM routes need the CRM plan feature and, on scoped tokens, `read:crm` / `write:crm`.

### Calendar and video meetings

```php
$slots = $omnifox->appointments->slots(['calendar_id' => 3, 'from' => '2026-10-20', 'to' => '2026-10-27']);

$appointment = $omnifox->appointments->book([
    'calendar_id' => 3,
    'starts_at' => '2026-10-21T15:00:00Z',
    'contact_id' => 42,
    'location_type' => 'video_omnifox',          // Omnifox video room, created on the fly
    'video_options' => ['translate_mode' => 'voice', 'host_lang' => 'es', 'guest_lang' => 'en'],
]);
$appointment['video_guest_url'];
```

### Catalog, orders and quotes

```php
$product = $omnifox->products->create(['name' => 'Espresso', 'unit_price' => 2.5, 'is_orderable' => true]);

// Send product + quantity: the server prices each line from the catalog and the
// workspace tax settings. (An explicit unit_price is honoured as a manual override.)
$order = $omnifox->orders->create([
    'items' => [['product_id' => $product['id'], 'quantity' => 2]],
    'fulfillment' => 'pickup',
    'customer_name' => 'Ada',
]);
$omnifox->orders->setStatus($order['id'], 'confirmed');
$omnifox->orders->recordPayment($order['id'], 5.0, 'cash');

$quote = $omnifox->quotes->create(['currency' => 'USD', 'items' => [['product_id' => $product['id'], 'quantity' => 10]]]);
$omnifox->quotes->pdf($quote['id'])->saveTo('/tmp/quote.pdf');   // binary, not JSON
$omnifox->quotes->send($quote['id'], ['channels' => ['email' => true]]);
```

### Boards

```php
$board = $omnifox->boards->create(['name' => 'Onboarding', 'template' => 'blank']);
$item = $omnifox->boardItems->create($board['id'], ['title' => 'Kick-off call']);
$omnifox->boardItems->setCellValue($item['id'], $columnId, 'Done');
```

## Notes for PHP specifically

- PHP encodes an empty array as `[]`, never `{}`. A top-level empty body is sent as `{}`; for a nested empty object pass `new \stdClass()`.
- Query booleans are sent as `1` / `0`, which Laravel's `boolean` rule accepts.
- Decoded JSON objects come back as arrays, so an empty object from the server (`settings: []`) and an empty list look the same.

## Development

```bash
composer install
composer test                       # unit tests (no network)

OMNIFOX_TOKEN=... OMNIFOX_WORKSPACE_ID=1 \
OMNIFOX_E2E_WORKFLOW_ID=12 OMNIFOX_E2E_BROADCAST_ID=1 \
php tests/e2e/e2e.php --write       # live run; deletes what it creates
```

## License

MIT
