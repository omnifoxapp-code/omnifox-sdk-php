<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/teams`: teams and their members. Creating one needs the workspace in the body (filled in from the client). */
final class Teams extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/teams', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/teams', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/teams/{$id}");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        $this->client->assertWorkspace($input, 'teams->create');
        return $this->call('POST', '/teams', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', "/teams/{$id}", $input, $options);
    }

    public function delete(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/teams/{$id}", null, $options);
    }

    /**
     * Add a user to the team: a bare user id, or `['user_id' => 5, 'role' => 'lead']`.
     *
     * @param int|array<string, mixed> $input
     */
    public function addMember(int $id, int|array $input, RequestOptions|array|null $options = null): mixed
    {
        $body = is_int($input) ? ['user_id' => $input] : $input;

        return $this->call('POST', "/teams/{$id}/members", $body, $options);
    }

    public function removeMember(int $id, int $userId, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', "/teams/{$id}/members/{$userId}", null, $options);
    }
}
