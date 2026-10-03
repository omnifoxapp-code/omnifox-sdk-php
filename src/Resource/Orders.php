<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/**
 * Orders.
 *
 * Send product + quantity and leave the money to the server: a line without
 * `unit_price` is priced from the catalog, taxes come from the workspace
 * settings, and totals sent by a client are ignored. (An explicit
 * `unit_price` on a line IS honoured as a manual price override.)
 * Status and payment are their own endpoints; illegal transitions answer 422.
 */
final class Orders extends AbstractResource
{
    public const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'shipped', 'delivered', 'cancelled'];

    /** @param array<string, mixed> $params status, payment_status, contact_id */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/orders', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/orders', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/orders/{$id}");
    }

    /**
     * @param array<string, mixed> $input items (required: product_id or name, quantity, options; unit_price only to override),
     *                                    contact_id, conversation_id, fulfillment (pickup|delivery|digital),
     *                                    delivery_address, customer_name, customer_phone, customer_email,
     *                                    customer_tax_id, notes, customer_notes
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/orders', $input, $options);
    }

    /**
     * Only while the workspace edit policy allows it, otherwise 422.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/orders/{$id}", $input, $options);
    }

    /** @return array<string, mixed> */
    public function setStatus(int $id, string $status, ?string $reason = null, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/orders/{$id}/status", self::compact(['status' => $status, 'reason' => $reason]), $options);
    }

    /** Full or partial payment; the payment status is derived. @return array<string, mixed> */
    public function recordPayment(int $id, float|int $amount, ?string $method = null, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/orders/{$id}/payment", self::compact(['amount' => $amount, 'method' => $method]), $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/orders/{$id}", null, $options);
    }
}
