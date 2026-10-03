<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/snippets`: saved replies (canned messages). */
final class Snippets extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/snippets', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/snippets', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/snippets/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/snippets', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/snippets/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/snippets/{$id}", null, $options);
    }
}
