<?php

declare(strict_types=1);

namespace Omnifox\Pagination;

/**
 * Lazy iterator over every item of a listing, fetching pages on demand.
 *
 *     foreach ($client->contacts->list() as $contact) { ... }
 *
 * Prefers cursor mode (stable under concurrent writes) and falls back to
 * `?page=N` on endpoints that ignore `pagination=cursor`. Hard-stops after
 * 500 pages so a server that always claims another page cannot spin forever.
 *
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final class Paginator implements \IteratorAggregate
{
    public const MAX_PAGES = 500;

    /** @var \Closure(array<string, mixed>): Page */
    private \Closure $fetchPage;

    /**
     * @param callable(array<string, mixed>): Page $fetchPage
     * @param array<string, mixed>                 $params
     */
    public function __construct(callable $fetchPage, private readonly array $params = [])
    {
        $this->fetchPage = \Closure::fromCallable($fetchPage);
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function getIterator(): \Generator
    {
        $params = $this->params + ['pagination' => 'cursor'];
        $cursorId = $params['cursorId'] ?? null;
        $page = $params['page'] ?? null;
        $guard = 0;

        while (true) {
            $query = $params;
            if ($cursorId !== null) {
                $query['cursorId'] = $cursorId;
            }
            if ($page !== null) {
                $query['page'] = $page;
            }

            $result = ($this->fetchPage)($query);
            foreach ($result->data as $item) {
                yield $item;
            }

            if (++$guard >= self::MAX_PAGES || $result->data === []) {
                return;
            }
            if ($result->nextCursor !== null && $result->hasMore) {
                $cursorId = $result->nextCursor;
                continue;
            }
            if ($result->hasMore && $result->page !== null) {
                $page = $result->page + 1;
                $cursorId = null;
                unset($params['cursorId']);
                continue;
            }

            return;
        }
    }

    /**
     * Collect up to `$max` items into an array (all of them when null).
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(?int $max = null): array
    {
        $out = [];
        foreach ($this as $item) {
            $out[] = $item;
            if ($max !== null && count($out) >= $max) {
                break;
            }
        }

        return $out;
    }
}
