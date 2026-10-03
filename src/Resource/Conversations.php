<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/** `/conversations/*`: workspace-scoped conversation operations. */
final class Conversations extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/conversations', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/conversations', $params);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return $this->fetch("/conversations/{$id}");
    }

    /**
     * @param array<string, mixed> $input e.g. ['contact_id' => 1, 'channel_id' => 2]
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/conversations', $input, $options);
    }

    /**
     * Assign or unassign.
     *
     * The endpoint takes `assignee_type` (user | team | ai_agent | none) plus
     * `assignee_id`. The legacy keys `assigned_user_id`, `assigned_team_id`
     * and `assigned_ai_agent_id` are translated. The team distribution
     * override (`assign_logic`, `only_online`, `max_open`) travels as given.
     * An empty input unassigns.
     *
     * @param array<string, mixed> $input
     */
    public function assign(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/conversations/{$id}/assign", self::normaliseAssign($input), $options);
    }

    /**
     * Close. The server reads `resolution` and `notes`; `reason` and `summary`
     * are accepted as aliases.
     *
     * @param array<string, mixed> $input
     */
    public function close(int $id, array $input = [], RequestOptions|array|null $options = null): mixed
    {
        $body = self::compact([
            'resolution' => $input['resolution'] ?? $input['reason'] ?? null,
            'notes' => $input['notes'] ?? $input['summary'] ?? null,
        ]);

        return $this->call('POST', "/conversations/{$id}/close", $body, $options);
    }

    public function reopen(int $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/conversations/{$id}/reopen", null, $options);
    }

    /** Internal note. @param array<string, mixed> $input ['text' => '...'] */
    public function addNote(int $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', "/conversations/{$id}/notes", $input, $options);
    }

    /** @param array<string, mixed> $params */
    public function listMessages(int $id, array $params = []): Page
    {
        return $this->pageAt("/conversations/{$id}/messages", $params);
    }

    /**
     * @param array<string, mixed> $input e.g. ['type' => 'text', 'content_text' => 'Hi']
     * @return array<string, mixed>
     */
    public function sendMessage(int $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', "/conversations/{$id}/messages", $input, $options);
    }

    /** @return array<string, mixed> */
    public function getMessage(int $id, int $messageId): array
    {
        return $this->fetch("/conversations/{$id}/messages/{$messageId}");
    }

    /**
     * Can free-form text still be sent? WhatsApp and TikTok close the window
     * 24 h after the customer's last message; every other channel answers
     * `open: true`. Returns `{open, channel_type, expired_at, reason}`.
     *
     * @return array<string, mixed>
     */
    public function windowStatus(int $id): array
    {
        return $this->fetch("/conversations/{$id}/window-status");
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function normaliseAssign(array $input): array
    {
        $override = array_intersect_key($input, array_flip(['assign_logic', 'only_online', 'max_open']));

        if (isset($input['assignee_type'])) {
            return self::compact([
                'assignee_type' => $input['assignee_type'],
                'assignee_id' => $input['assignee_id'] ?? null,
            ]) + $override;
        }
        foreach (['assigned_user_id' => 'user', 'assigned_team_id' => 'team', 'assigned_ai_agent_id' => 'ai_agent'] as $key => $type) {
            if (isset($input[$key])) {
                return ['assignee_type' => $type, 'assignee_id' => $input[$key]] + $override;
            }
        }

        return ['assignee_type' => 'none'];
    }
}
