<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/**
 * CRM companies (`/crm/companies`).
 *
 * All CRM routes are gated twice: the plan needs CRM, and a scoped token
 * needs `read:crm` / `write:crm`. Either gate answers 403.
 */
final class Companies extends AbstractResource
{
    /** @param array<string, mixed> $params owner_user_id, domain, with_contacts_count, ... */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/crm/companies', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/crm/companies', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/crm/companies/{$id}");
    }

    /**
     * @param array<string, mixed> $input `name` is required.
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/crm/companies', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/crm/companies/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/crm/companies/{$id}", null, $options);
    }

    public function attachContact(int $id, int $contactId, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/crm/companies/{$id}/contacts", ['contact_id' => $contactId], $options);
    }

    public function detachContact(int $id, int $contactId, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/crm/companies/{$id}/contacts/{$contactId}", null, $options);
    }
}
