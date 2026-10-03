<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** Auth (`/auth/*`) and agents (`/agents/*`): who is acting. */
final class Users extends AbstractResource
{
    /** The authenticated user (`GET /auth/me`). @return array<string, mixed> */
    public function me(): array
    {
        return $this->fetch('/auth/me');
    }

    /** Revoke the current token. Every later call with it answers 401. */
    public function logout(RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/auth/logout', null, $options);
    }

    /** Every agent of the workspace. @param array<string, mixed> $params */
    public function listAgents(array $params = []): Paginator
    {
        return $this->paginate('/agents', $params);
    }

    /** @param array<string, mixed> $params */
    public function listAgentsPage(array $params = []): Page
    {
        return $this->pageAt('/agents', $params);
    }

    /** @return array<string, mixed> */
    public function getAgent(int $id): array
    {
        return $this->fetch("/agents/{$id}");
    }

    /**
     * Set an agent's availability: `available`, `busy` or `offline`.
     * (`away` passes validation but, as of 2026-10, the server stores it in a
     * column that does not accept it and answers 500.)
     * The endpoint needs `workspace_id` in the body: filled in from the client.
     * Only the agent themself or an org admin may change it.
     *
     * @param string|array<string, mixed> $input status, or ['status' => ..., 'workspace_id' => ...]
     */
    public function setAgentStatus(int $id, string|array $input, RequestOptions|array|null $options = null): mixed
    {
        $body = is_string($input) ? ['status' => $input] : $input;
        $this->client->assertWorkspace($body, 'users->setAgentStatus');

        return $this->call('PUT', "/agents/{$id}/status", $body, $options);
    }
}
