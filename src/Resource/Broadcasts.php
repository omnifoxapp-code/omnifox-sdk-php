<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/broadcasts`: bulk campaigns. Plan-gated (403 on plans without campaigns). Creating one needs the workspace in the body. */
final class Broadcasts extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/broadcasts', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/broadcasts', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/broadcasts/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        $this->client->assertWorkspace($input, 'broadcasts->create');
        return $this->call('POST', '/broadcasts', $input, $options);
    }

    public function cancel(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/broadcasts/{$id}/cancel", null, $options);
    }
}
