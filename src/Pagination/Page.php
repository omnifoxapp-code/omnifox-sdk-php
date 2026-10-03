<?php

declare(strict_types=1);

namespace Omnifox\Pagination;

/**
 * One window of a listing plus whatever the endpoint returned to reach the
 * next one.
 *
 * The Omnifox API answers listings with four different envelopes, and a
 * client that only understands one silently truncates results:
 *
 *   1. {success, message, data: [...]}                      plain
 *   2. {success, message, data: [...], meta, links}         paginated()
 *   3. {success, message, data: {data: [...], current_page}} success($paginator)
 *   4. {items: [...], pagination: {next_cursor, has_more}}  ?pagination=cursor
 *
 * {@see Page::fromResponse()} normalises all four. It must receive the RAW
 * body: shape 2 keeps its page meta at the top level.
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param list<array<string, mixed>> $data
     */
    public function __construct(
        public readonly array $data = [],
        public readonly ?int $nextCursor = null,
        public readonly ?int $prevCursor = null,
        public readonly bool $hasMore = false,
        public readonly int $limit = 0,
        /** Page mode only. */
        public readonly ?int $page = null,
        /** Page mode only. */
        public readonly ?int $lastPage = null,
        /** Total rows across all pages, when reported. */
        public readonly ?int $total = null,
    ) {
    }

    public static function fromResponse(mixed $payload): self
    {
        if (!is_array($payload)) {
            return new self();
        }
        if (array_is_list($payload)) {
            return new self(data: $payload, limit: count($payload));
        }

        $dataObj = (isset($payload['data']) && is_array($payload['data']) && !array_is_list($payload['data']))
            ? $payload['data']
            : null;

        // 4: cursor envelope, with or without a data wrapper.
        $cursorHost = null;
        if (isset($payload['items']) && is_array($payload['items'])) {
            $cursorHost = $payload;
        } elseif ($dataObj !== null && isset($dataObj['items']) && is_array($dataObj['items'])) {
            $cursorHost = $dataObj;
        }
        if ($cursorHost !== null) {
            $items = array_values($cursorHost['items']);
            $p = is_array($cursorHost['pagination'] ?? null) ? $cursorHost['pagination'] : [];

            return new self(
                data: $items,
                nextCursor: self::int($p['next_cursor'] ?? null),
                prevCursor: self::int($p['previous_cursor'] ?? null),
                hasMore: (bool) ($p['has_more'] ?? false),
                limit: self::int($p['limit'] ?? null) ?? count($items),
            );
        }

        // 2: page meta beside the rows.
        $meta = $payload['meta'] ?? null;
        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])
            && is_array($meta) && array_key_exists('current_page', $meta)) {
            return self::pageMode($payload['data'], $meta);
        }

        // 3: a Laravel paginator handed straight to success().
        if ($dataObj !== null && isset($dataObj['data']) && is_array($dataObj['data'])
            && array_key_exists('current_page', $dataObj)) {
            return self::pageMode($dataObj['data'], $dataObj);
        }

        // 1: plain envelope, or a bare list one level down.
        if (isset($payload['data']) && is_array($payload['data']) && array_is_list($payload['data'])) {
            return new self(data: $payload['data'], limit: count($payload['data']));
        }
        if ($dataObj !== null && isset($dataObj['data']) && is_array($dataObj['data']) && array_is_list($dataObj['data'])) {
            return new self(data: $dataObj['data'], limit: count($dataObj['data']));
        }

        return new self();
    }

    /**
     * @param array<mixed>         $rows
     * @param array<string, mixed> $meta
     */
    private static function pageMode(array $rows, array $meta): self
    {
        $rows = array_values($rows);
        $page = self::int($meta['current_page'] ?? null);
        $last = self::int($meta['last_page'] ?? null);

        return new self(
            data: $rows,
            hasMore: $page !== null && $last !== null && $page < $last,
            limit: self::int($meta['per_page'] ?? null) ?? count($rows),
            page: $page,
            lastPage: $last,
            total: self::int($meta['total'] ?? null),
        );
    }

    private static function int(mixed $value): ?int
    {
        if ($value === null || $value === '' || is_bool($value) || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /** @return \ArrayIterator<int, array<string, mixed>> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
