<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/workflows`: automation workflows. */
final class Workflows extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/workflows', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/workflows', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/workflows/{$id}");
    }

    public function activate(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/workflows/{$id}/activate", null, $options);
    }

    public function deactivate(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/workflows/{$id}/deactivate", null, $options);
    }

    /**
     * Run the workflow by hand, e.g. `['context' => ['contact_id' => 5], 'force' => true]`.
     * An inactive workflow without `force` answers 422.
     *
     * @param array<string, mixed> $input
     */
    public function trigger(int $id, array $input = [], RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/workflows/{$id}/trigger", $input, $options);
    }
}
