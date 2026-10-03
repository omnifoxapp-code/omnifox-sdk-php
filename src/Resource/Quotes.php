<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\BinaryResponse;
use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** Quotes (proformas), their delivery and their PDF. */
final class Quotes extends AbstractResource
{
    /** @param array<string, mixed> $params status, deal_id, contact_id */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/quotes', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/quotes', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/quotes/{$id}");
    }

    /**
     * @param array<string, mixed> $input contact_id, deal_id, board_item_id, billing_entity_id, currency,
     *                                    valid_until, notes, number_prefix, items
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/quotes', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/quotes/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/quotes/{$id}", null, $options);
    }

    /**
     * Deliver the quote: `['channels' => ['email' => true, 'whatsapp' => false], 'dry_run' => true]`.
     * Sending is what registers the public link; `dry_run` reports without sending.
     *
     * @param array<string, mixed> $input
     */
    public function send(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->client->request('POST', "/quotes/{$id}/send", null, $input, $options);
    }

    /** @return array<string, mixed> */
    public function markViewed(int $id, RequestOptions|array|null $options = null): array
    {
        return $this->client->request('POST', "/quotes/{$id}/mark-viewed", null, null, $options);
    }

    /** @return array<string, mixed> */
    public function accept(int $id, RequestOptions|array|null $options = null): array
    {
        return $this->client->request('POST', "/quotes/{$id}/accept", null, null, $options);
    }

    /** @return array<string, mixed> */
    public function reject(int $id, RequestOptions|array|null $options = null): array
    {
        return $this->client->request('POST', "/quotes/{$id}/reject", null, null, $options);
    }

    /** The rendered PDF. This endpoint answers with bytes, not JSON. */
    public function pdf(int $id): BinaryResponse
    {
        return $this->client->requestBinary("/quotes/{$id}/pdf");
    }

    /** Billing entities usable as the issuer of a quote. */
    public function billingEntities(): mixed
    {
        return $this->fetch('/billing-entities');
    }
}
