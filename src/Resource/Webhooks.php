<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/webhooks`: outbound HMAC-signed webhook subscriptions. Creating one requires an admin token and an https URL. */
final class Webhooks extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/webhooks', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/webhooks', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/webhooks/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/webhooks', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/webhooks/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/webhooks/{$id}", null, $options);
    }

    /** Rotate the HMAC secret; the new one comes back in `hmac_secret`. @return array<string, mixed> */
    public function rotateSecret(int $id, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/webhooks/{$id}/rotate-secret", null, $options);
    }
}
