<?php

declare(strict_types=1);

namespace Omnifox\Tests\Unit;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;

final class PaginationTest extends TestCase
{
    public function testPlainEnvelope(): void
    {
        $p = Page::fromResponse(['success' => true, 'message' => 'OK', 'data' => [['id' => 1], ['id' => 2]]]);
        self::assertCount(2, $p);
        self::assertFalse($p->hasMore);
    }

    public function testPaginatedWithTopLevelMeta(): void
    {
        $p = Page::fromResponse([
            'success' => true, 'data' => [['id' => 1]],
            'meta' => ['current_page' => 1, 'last_page' => 3, 'per_page' => 1, 'total' => 3],
        ]);
        self::assertSame(1, $p->page);
        self::assertSame(3, $p->lastPage);
        self::assertSame(3, $p->total);
        self::assertTrue($p->hasMore);
    }

    public function testLaravelPaginatorInsideData(): void
    {
        $p = Page::fromResponse(['success' => true, 'data' => [
            'current_page' => 2, 'last_page' => 2, 'per_page' => 20, 'total' => 21, 'data' => [['id' => 21]],
        ]]);
        self::assertSame([['id' => 21]], $p->data);
        self::assertSame(2, $p->page);
        self::assertFalse($p->hasMore);
    }

    public function testCursorEnvelope(): void
    {
        $p = Page::fromResponse(['items' => [['id' => 5]], 'pagination' => ['next_cursor' => 5, 'has_more' => true, 'limit' => 1]]);
        self::assertSame(5, $p->nextCursor);
        self::assertTrue($p->hasMore);
        self::assertSame(1, $p->limit);

        $wrapped = Page::fromResponse(['success' => true, 'data' => ['items' => [['id' => 5]], 'pagination' => ['next_cursor' => null, 'has_more' => false]]]);
        self::assertCount(1, $wrapped);
        self::assertNull($wrapped->nextCursor);
    }

    public function testGarbageIsEmpty(): void
    {
        self::assertCount(0, Page::fromResponse(null));
        self::assertCount(0, Page::fromResponse('oops'));
        self::assertCount(0, Page::fromResponse(['success' => true, 'data' => []]));
        self::assertCount(2, Page::fromResponse([['id' => 1], ['id' => 2]]));
    }

    public function testPaginatorFollowsCursor(): void
    {
        $c = $this->client([
            self::json(['items' => [['id' => 1], ['id' => 2]], 'pagination' => ['next_cursor' => 2, 'has_more' => true]]),
            self::json(['items' => [['id' => 3]], 'pagination' => ['next_cursor' => null, 'has_more' => false]]),
        ]);
        $ids = array_column($c->contacts->list(['limit' => 2])->toArray(), 'id');

        self::assertSame([1, 2, 3], $ids);
        self::assertSame('cursor', $this->query(0)['pagination']);
        self::assertArrayNotHasKey('cursorId', $this->query(0));
        self::assertSame('2', $this->query(1)['cursorId']);
    }

    public function testPaginatorFallsBackToPageMode(): void
    {
        // The endpoint ignores pagination=cursor and answers with page meta.
        $c = $this->client([
            self::json(['success' => true, 'data' => ['data' => [['id' => 1]], 'current_page' => 1, 'last_page' => 2]]),
            self::json(['success' => true, 'data' => ['data' => [['id' => 2]], 'current_page' => 2, 'last_page' => 2]]),
        ]);
        $ids = [];
        foreach ($c->orders->list() as $o) {
            $ids[] = $o['id'];
        }

        self::assertSame([1, 2], $ids);
        self::assertSame('2', $this->query(1)['page']);
    }

    public function testToArrayStopsEarlyWithoutFetchingMore(): void
    {
        $c = $this->client([
            self::json(['items' => [['id' => 1], ['id' => 2]], 'pagination' => ['next_cursor' => 2, 'has_more' => true]]),
        ]);
        self::assertCount(1, $c->contacts->list()->toArray(1));
        self::assertCount(1, $this->history);
    }

    public function testPaginatorStopsOnEmptyPageAndHasGuard(): void
    {
        $calls = 0;
        $p = new Paginator(function () use (&$calls): Page {
            $calls++;

            return new Page(data: [['id' => $calls]], nextCursor: $calls, hasMore: true);
        });
        $n = 0;
        foreach ($p as $_) {
            $n++;
        }
        self::assertSame(Paginator::MAX_PAGES, $n);

        $empty = new Paginator(fn (): Page => new Page(data: [], nextCursor: 1, hasMore: true));
        self::assertSame([], $empty->toArray());
    }
}
