<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/channels`: connected channels. Creating one needs the workspace in the body (filled in from the client). */
final class Channels extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/channels', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/channels', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/channels/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        $this->client->assertWorkspace($input, 'channels->create');
        return $this->call('POST', '/channels', $input, $options);
    }

    /** Force-disconnect a channel (kills its session / credentials). */
    public function disconnect(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/channels/{$id}/disconnect", null, $options);
    }

    /** Synchronous health check. */
    public function health(int $id): mixed
    {
        return $this->fetch("/channels/{$id}/health");
    }

    /** Approved message templates (WhatsApp / SMS). */
    public function templates(int $id): mixed
    {
        return $this->fetch("/channels/{$id}/templates");
    }
}
