<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\OmnifoxClient;
use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/**
 * Shared base for every resource.
 *
 * Conventions across the SDK:
 *  - `list()` returns a lazy {@see Paginator} that walks every page;
 *  - `listPage()` returns one {@see Page};
 *  - write methods take an input array and an optional {@see RequestOptions}
 *    (or array) as their last argument;
 *  - results are associative arrays, unwrapped from the success envelope.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly OmnifoxClient $client)
    {
    }

    /** @param array<string, mixed> $params */
    protected function pageAt(string $path, array $params = []): Page
    {
        // Page::fromResponse understands all four envelopes, including the
        // one that keeps its page meta at the TOP level, so it gets the raw body.
        return Page::fromResponse($this->client->requestRaw('GET', $path, $params));
    }

    /** @param array<string, mixed> $params */
    protected function paginate(string $path, array $params = []): Paginator
    {
        return new Paginator(fn (array $query): Page => $this->pageAt($path, $query), $params);
    }

    /**
     * @param array<string, mixed>|null $query
     * @param RequestOptions|array<string, mixed>|null $options
     */
    protected function fetch(string $path, ?array $query = null, RequestOptions|array|null $options = null): mixed
    {
        return $this->client->request('GET', $path, $query, null, $options);
    }

    /** @param RequestOptions|array<string, mixed>|null $options */
    protected function call(string $method, string $path, mixed $body = null, RequestOptions|array|null $options = null): mixed
    {
        return $this->client->request($method, $path, null, $body, $options);
    }

    /**
     * Encode a flexible identifier for a URL segment:
     * 42 => "42", "email:ana@x.com" => "email%3Aana%40x.com".
     */
    protected static function id(int|string $id): string
    {
        return rawurlencode((string) $id);
    }

    /**
     * Drop keys whose value is null so optional arguments are omitted from
     * the body instead of being sent as explicit nulls.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    protected static function compact(array $values): array
    {
        return array_filter($values, static fn ($v) => $v !== null);
    }
}
