<?php

declare(strict_types=1);

namespace Omnifox;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\HttpFactory;
use Omnifox\Exception\ErrorFactory;
use Omnifox\Exception\InvalidArgumentException;
use Omnifox\Exception\NetworkException;
use Omnifox\Exception\RateLimitException;
use Omnifox\Resource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point of the Omnifox PHP SDK.
 *
 *     $omnifox = new OmnifoxClient(['apiKey' => getenv('OMNIFOX_API_KEY')]);
 *     $contact = $omnifox->contacts->get('email:ana@example.com');
 *
 * Options:
 *  - apiKey (string, required)  Personal access token from Settings > API keys.
 *  - workspaceId (int)          Optional for tokens pinned to a workspace (the
 *                               server reads it from the token). Required for
 *                               unpinned tokens: the SDK then sends it as the
 *                               `workspace_id` query param on every call and in
 *                               the body of the endpoints that demand it.
 *                               The X-Workspace-Id header is NOT read by the API.
 *  - baseUrl (string)           Default https://app.omnifox.io/api/v1
 *  - timeout (float)            Seconds per request. Default 30.
 *  - maxRetries (int)           Retries on 429 / 5xx / connection errors. Default 3.
 *  - httpClient (PSR-18)        Any PSR-18 client. Default: Guzzle.
 *  - requestFactory, streamFactory (PSR-17)  Default: guzzlehttp/psr7.
 *  - userAgent (string)
 *
 * Every resource returns decoded JSON as associative arrays, already unwrapped
 * from the `{success, message, data}` envelope.
 */
final class OmnifoxClient
{
    public const VERSION = '0.4.1';
    public const DEFAULT_BASE_URL = 'https://app.omnifox.io/api/v1';

    /**
     * Endpoints that validate `workspace_id` as a REQUIRED body field even when
     * the token is pinned. The client fills it in when it knows the workspace.
     */
    private const BODY_NEEDS_WORKSPACE = [
        ['PUT', '#^/agents/[^/]+/status$#'],
        ['POST', '#^/teams$#'],
        ['POST', '#^/channels$#'],
        ['POST', '#^/broadcasts$#'],
    ];

    public readonly Resource\Contacts $contacts;
    public readonly Resource\Conversations $conversations;
    public readonly Resource\Messages $messages;
    public readonly Resource\Channels $channels;
    public readonly Resource\Broadcasts $broadcasts;
    public readonly Resource\CustomFields $customFields;
    public readonly Resource\Tags $tags;
    public readonly Resource\Snippets $snippets;
    public readonly Resource\Webhooks $webhooks;
    public readonly Resource\Workflows $workflows;
    public readonly Resource\Users $users;
    public readonly Resource\Workspaces $workspaces;
    public readonly Resource\Teams $teams;
    public readonly Resource\Companies $companies;
    public readonly Resource\Deals $deals;
    public readonly Resource\Pipelines $pipelines;
    public readonly Resource\CrmActivities $crmActivities;
    public readonly Resource\Boards $boards;
    public readonly Resource\BoardItems $boardItems;
    public readonly Resource\Appointments $appointments;
    public readonly Resource\Products $products;
    public readonly Resource\Orders $orders;
    public readonly Resource\CatalogSettings $catalogSettings;
    public readonly Resource\Quotes $quotes;
    public readonly Resource\Reports $reports;

    private readonly string $apiKey;
    private readonly ?int $workspaceId;
    private readonly string $baseUrl;
    private readonly float $timeout;
    private readonly int $maxRetries;
    private readonly string $userAgent;
    private readonly ClientInterface $http;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    /** @var \Closure(float): void */
    private \Closure $sleeper;

    /**
     * @param array{
     *     apiKey: string,
     *     workspaceId?: int|null,
     *     baseUrl?: string,
     *     timeout?: float|int,
     *     maxRetries?: int,
     *     httpClient?: ClientInterface,
     *     requestFactory?: RequestFactoryInterface,
     *     streamFactory?: StreamFactoryInterface,
     *     userAgent?: string,
     *     sleeper?: callable(float): void
     * }|string $options Options array, or just the API key.
     */
    public function __construct(array|string $options)
    {
        if (is_string($options)) {
            $options = ['apiKey' => $options];
        }
        $apiKey = $options['apiKey'] ?? '';
        if (!is_string($apiKey) || trim($apiKey) === '') {
            throw new InvalidArgumentException('OmnifoxClient: apiKey is required');
        }

        $this->apiKey = trim($apiKey);
        $ws = $options['workspaceId'] ?? null;
        $this->workspaceId = ($ws === null || $ws === '' || (int) $ws <= 0) ? null : (int) $ws;
        $this->baseUrl = rtrim((string) ($options['baseUrl'] ?? self::DEFAULT_BASE_URL), '/');
        $this->timeout = (float) ($options['timeout'] ?? 30);
        $this->maxRetries = max(0, (int) ($options['maxRetries'] ?? 3));
        $this->userAgent = (string) ($options['userAgent'] ?? 'omnifox-sdk-php/' . self::VERSION . ' PHP/' . PHP_VERSION);

        $factory = null;
        if (isset($options['requestFactory']) && $options['requestFactory'] instanceof RequestFactoryInterface) {
            $this->requestFactory = $options['requestFactory'];
        } else {
            $this->requestFactory = $factory ??= new HttpFactory();
        }
        if (isset($options['streamFactory']) && $options['streamFactory'] instanceof StreamFactoryInterface) {
            $this->streamFactory = $options['streamFactory'];
        } else {
            $this->streamFactory = $factory ??= new HttpFactory();
        }
        if (isset($options['httpClient']) && $options['httpClient'] instanceof ClientInterface) {
            $this->http = $options['httpClient'];
        } else {
            $this->http = new GuzzleClient([
                'timeout' => $this->timeout,
                'connect_timeout' => min(10.0, $this->timeout),
                'http_errors' => false,
            ]);
        }
        $sleeper = $options['sleeper'] ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
        $this->sleeper = \Closure::fromCallable($sleeper);

        $this->contacts = new Resource\Contacts($this);
        $this->conversations = new Resource\Conversations($this);
        $this->messages = new Resource\Messages($this);
        $this->channels = new Resource\Channels($this);
        $this->broadcasts = new Resource\Broadcasts($this);
        $this->customFields = new Resource\CustomFields($this);
        $this->tags = new Resource\Tags($this);
        $this->snippets = new Resource\Snippets($this);
        $this->webhooks = new Resource\Webhooks($this);
        $this->workflows = new Resource\Workflows($this);
        $this->users = new Resource\Users($this);
        $this->workspaces = new Resource\Workspaces($this);
        $this->teams = new Resource\Teams($this);
        $this->companies = new Resource\Companies($this);
        $this->deals = new Resource\Deals($this);
        $this->pipelines = new Resource\Pipelines($this);
        $this->crmActivities = new Resource\CrmActivities($this);
        $this->boards = new Resource\Boards($this);
        $this->boardItems = new Resource\BoardItems($this);
        $this->appointments = new Resource\Appointments($this);
        $this->products = new Resource\Products($this);
        $this->orders = new Resource\Orders($this);
        $this->catalogSettings = new Resource\CatalogSettings($this);
        $this->quotes = new Resource\Quotes($this);
        $this->reports = new Resource\Reports($this);
    }

    /** The workspace configured on this client, if any. */
    public function getWorkspaceId(): ?int
    {
        return $this->workspaceId;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // ─── Request helpers (public so you can reach endpoints the SDK does not wrap) ──

    /**
     * Send a request and return the `data` field of the success envelope.
     *
     * Use this as an escape hatch for any `/api/v1` route without a dedicated
     * method: `$client->request('GET', '/me/notifications')`.
     *
     * @param array<string, mixed>|null      $query
     * @param mixed                          $body    Array / object, JSON-encoded. Null = no body.
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function request(string $method, string $path, ?array $query = null, mixed $body = null, RequestOptions|array|null $options = null): mixed
    {
        return self::unwrap($this->requestRaw($method, $path, $query, $body, $options));
    }

    /**
     * Like {@see request()} but returns the whole decoded body, envelope and
     * pagination meta included.
     *
     * @param array<string, mixed>|null      $query
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function requestRaw(string $method, string $path, ?array $query = null, mixed $body = null, RequestOptions|array|null $options = null): mixed
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $opts = RequestOptions::from($options);

        $body = $this->withWorkspaceBody($method, $path, $body);
        $request = $this->requestFactory
            ->createRequest($method, $this->buildUrl($path, $this->withWorkspaceQuery($query)))
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(self::encodeJson($body)));
        }
        if ($opts->idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $opts->idempotencyKey);
        }
        foreach ($opts->headers as $name => $value) {
            $request = $request->withHeader((string) $name, (string) $value);
        }

        $response = $this->send($request, $opts);

        return self::decode((string) $response->getBody());
    }

    /**
     * GET an endpoint that answers with a file (the quote PDF).
     *
     * @param array<string, mixed>|null $query
     */
    public function requestBinary(string $path, ?array $query = null, RequestOptions|array|null $options = null): BinaryResponse
    {
        $path = '/' . ltrim($path, '/');
        $opts = RequestOptions::from($options);
        $request = $this->requestFactory
            ->createRequest('GET', $this->buildUrl($path, $this->withWorkspaceQuery($query)))
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Accept', '*/*')
            ->withHeader('User-Agent', $this->userAgent);
        foreach ($opts->headers as $name => $value) {
            $request = $request->withHeader((string) $name, (string) $value);
        }

        $response = $this->send($request, $opts);
        $fileName = null;
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $response->getHeaderLine('Content-Disposition'), $m)) {
            $fileName = rawurldecode($m[1]);
        }

        return new BinaryResponse(
            (string) $response->getBody(),
            $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
            $fileName,
        );
    }

    // ─── Internals ─────────────────────────────────────────────────────────

    /**
     * Unwrap the `{success, message, data}` envelope. Bodies without it (the
     * cursor envelope `{items, pagination}`, bare lists) come back untouched.
     *
     * @internal
     */
    public static function unwrap(mixed $envelope): mixed
    {
        if (is_array($envelope) && !array_is_list($envelope) && array_key_exists('data', $envelope)
            && (array_key_exists('success', $envelope) || array_key_exists('message', $envelope) || array_key_exists('meta', $envelope))) {
            return $envelope['data'];
        }

        return $envelope;
    }

    /** @internal Assert the workspace is known for endpoints that require it in the body. */
    public function assertWorkspace(mixed $input, string $method): void
    {
        if ($this->workspaceId !== null) {
            return;
        }
        if (is_array($input) && isset($input['workspace_id'])) {
            return;
        }
        throw new InvalidArgumentException(
            "{$method}: this endpoint requires a workspace. Pass 'workspace_id' in the input, "
            . "or set 'workspaceId' once when constructing OmnifoxClient."
        );
    }

    private function send(RequestInterface $request, RequestOptions $opts): ResponseInterface
    {
        $maxRetries = $opts->maxRetries ?? $this->maxRetries;
        $attempt = 0;

        while (true) {
            if ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }
            try {
                $response = $this->dispatch($request, $opts);
            } catch (NetworkExceptionInterface $e) {
                if ($attempt < $maxRetries && !self::isTimeout($e)) {
                    $attempt++;
                    ($this->sleeper)($this->backoff($attempt));
                    continue;
                }
                throw new NetworkException($e->getMessage(), 0, $e);
            } catch (ClientExceptionInterface|GuzzleException $e) {
                throw new NetworkException($e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $response;
            }

            $retryAfter = self::parseRetryAfter($response->getHeaderLine('Retry-After'));
            $error = ErrorFactory::fromResponse(
                $status,
                self::decode((string) $response->getBody()),
                $response->getHeaderLine('X-Request-Id') ?: null,
                $retryAfter,
            );

            $retriable = $status === 429 || ($status >= 500 && $status < 600);
            if ($retriable && $attempt < $maxRetries) {
                $attempt++;
                $wait = ($error instanceof RateLimitException && $error->getRetryAfter() > 0)
                    ? (float) min(60, $error->getRetryAfter())
                    : $this->backoff($attempt);
                ($this->sleeper)($wait);
                continue;
            }

            throw $error;
        }
    }

    private function dispatch(RequestInterface $request, RequestOptions $opts): ResponseInterface
    {
        // With Guzzle we can honour a per-call timeout; plain PSR-18 cannot.
        if ($this->http instanceof GuzzleClientInterface) {
            $guzzleOptions = ['http_errors' => false];
            if ($opts->timeout !== null) {
                $guzzleOptions['timeout'] = $opts->timeout;
            }

            return $this->http->send($request, $guzzleOptions);
        }

        return $this->http->sendRequest($request);
    }

    private static function isTimeout(\Throwable $e): bool
    {
        // cURL error 28 = operation timed out: not retried, like the Node SDK.
        return str_contains($e->getMessage(), 'cURL error 28');
    }

    /** Exponential backoff with full jitter, in seconds, capped at 30 s. */
    private function backoff(int $attempt): float
    {
        $cap = min(30.0, 0.25 * (2 ** max(0, $attempt - 1)));

        return mt_rand(0, 1000) / 1000 * $cap;
    }

    private static function parseRetryAfter(string $header): int
    {
        $header = trim($header);
        if ($header === '') {
            return 0;
        }
        if (ctype_digit($header)) {
            return (int) $header;
        }
        $ts = strtotime($header);

        return $ts === false ? 0 : max(0, $ts - time());
    }

    /**
     * @param array<string, mixed>|null $query
     * @return array<string, mixed>|null
     */
    private function withWorkspaceQuery(?array $query): ?array
    {
        if ($this->workspaceId === null || ($query !== null && array_key_exists('workspace_id', $query))) {
            return $query;
        }

        return ($query ?? []) + ['workspace_id' => $this->workspaceId];
    }

    private function withWorkspaceBody(string $method, string $path, mixed $body): mixed
    {
        if ($this->workspaceId === null || !is_array($body) || ($body !== [] && array_is_list($body))) {
            return $body;
        }
        foreach (self::BODY_NEEDS_WORKSPACE as [$m, $re]) {
            if ($m === $method && preg_match($re, $path) === 1) {
                if (!isset($body['workspace_id'])) {
                    $body['workspace_id'] = $this->workspaceId;
                }

                return $body;
            }
        }

        return $body;
    }

    /** @param array<string, mixed>|null $query */
    private function buildUrl(string $path, ?array $query): string
    {
        $url = $this->baseUrl . $path;
        if ($query === null || $query === []) {
            return $url;
        }
        $qs = http_build_query(self::normaliseQuery($query), '', '&', PHP_QUERY_RFC3986);

        return $qs === '' ? $url : $url . '?' . $qs;
    }

    /**
     * Drop nulls and turn booleans into 1/0 — Laravel's `boolean` rule accepts
     * those, but not the strings "true"/"false" that http_build_query would
     * otherwise never produce anyway (it would send 1 and an EMPTY string).
     *
     * @param array<array-key, mixed> $query
     * @return array<array-key, mixed>
     */
    private static function normaliseQuery(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTimeInterface::ATOM);
            } elseif (is_array($value)) {
                $value = self::normaliseQuery($value);
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * JSON-encode a request body.
     *
     * PHP encodes an empty array as `[]`, never `{}`. A top-level body is
     * always an object for this API, so an empty one is sent as `{}`.
     * Pass `new \stdClass()` / `(object) []` for a nested empty object.
     */
    public static function encodeJson(mixed $body): string
    {
        if ($body === []) {
            $body = new \stdClass();
        }

        try {
            return json_encode(
                self::normaliseBody($body),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Request body is not JSON-encodable: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function normaliseBody(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::normaliseBody($v);
            }
        }

        return $value;
    }

    private static function decode(string $raw): mixed
    {
        if ($raw === '') {
            return null;
        }
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return $raw;
        }
    }
}
