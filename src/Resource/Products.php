<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/**
 * Product catalog. Transversal: not behind the CRM plan flag.
 *
 * Fields: name, sku, description, unit_price, currency, tax_rate, category,
 * is_active, image_url, stock (null = not tracked, which is not zero),
 * stock_alert_at, unit, is_orderable.
 */
final class Products extends AbstractResource
{
    /** @param array<string, mixed> $params category, active, ... */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/products', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/products', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/products/{$id}");
    }

    /**
     * @param array<string, mixed> $input `name` and `unit_price` are required.
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/products', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/products/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/products/{$id}", null, $options);
    }
}
