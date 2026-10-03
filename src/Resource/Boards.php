<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\RequestOptions;

/**
 * Boards: the Monday-style project module. Gated by the projects plan flag
 * and, on scoped tokens, `read:projects` / `write:projects`.
 */
final class Boards extends AbstractResource
{
    /**
     * Boards visible to the token's user, favourites first.
     *
     * @param array<string, mixed> $params ['include_archived' => true]
     * @return list<array<string, mixed>>
     */
    public function list(array $params = []): array
    {
        return $this->fetch('/boards', $params) ?? [];
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/boards/{$id}");
    }

    /** The whole table in one call: groups, columns, rows and cell values. */
    public function view(int $id): mixed
    {
        return $this->fetch("/boards/{$id}/view");
    }

    /** @return list<array<string, mixed>> */
    public function columns(int $id): array
    {
        return $this->fetch("/boards/{$id}/columns") ?? [];
    }

    /** Who changed what on the board. @param array<string, mixed> $params */
    public function activity(int $id, array $params = []): Page
    {
        return $this->pageAt("/boards/{$id}/activity", $params);
    }

    /**
     * @param array<string, mixed> $input name (required), description, color, visibility, template (client|sprint|blank)
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/boards', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/boards/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/boards/{$id}", null, $options);
    }

    /** @return array<string, mixed> */
    public function duplicate(int $id, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/boards/{$id}/duplicate", null, $options);
    }

    /**
     * Add a section. The field is `name`, not `title`.
     *
     * @param array<string, mixed> $input
     */
    public function createGroup(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/boards/{$id}/groups", $input, $options);
    }
}
