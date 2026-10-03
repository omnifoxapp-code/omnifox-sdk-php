<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Exception\InvalidArgumentException;
use Omnifox\Pagination\Page;
use Omnifox\Pagination\Paginator;
use Omnifox\RequestOptions;

/**
 * `/contacts/*`: the full respond.io-parity contact surface.
 *
 * Most methods accept a flexible identifier: an integer id, or a `kind:value`
 * selector such as `email:ana@example.com`, `phone:+593987654321` or
 * `external:crm-123`.
 */
final class Contacts extends AbstractResource
{
    /** @param array<string, mixed> $params */
    public function list(array $params = []): Paginator
    {
        return $this->paginate('/contacts', $params);
    }

    /** @param array<string, mixed> $params */
    public function listPage(array $params = []): Page
    {
        return $this->pageAt('/contacts', $params);
    }

    /** @return array<string, mixed> */
    public function get(int|string $id): array
    {
        return $this->fetch('/contacts/' . self::id($id));
    }

    /** Free-text search (`?q=`). @param array<string, mixed> $params */
    public function search(string $query, array $params = []): Page
    {
        return $this->pageAt('/contacts/search', ['q' => $query] + $params);
    }

    /** Filter-DSL listing (`POST /contacts/list`). @param array<string, mixed> $input */
    public function listViaDsl(array $input = []): Page
    {
        return Page::fromResponse($this->client->requestRaw('POST', '/contacts/list', null, $input));
    }

    /**
     * Create a contact. `display_name` is required by the server.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/contacts', $input, $options);
    }

    /**
     * Create-or-update keyed by `unique_by` (e.g. `['email']`).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function upsert(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('POST', '/contacts/upsert', $input, $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int|string $id, array $input, RequestOptions|array|null $options = null): array
    {
        return $this->call('PUT', '/contacts/' . self::id($id), $input, $options);
    }

    public function delete(int|string $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', '/contacts/' . self::id($id), null, $options);
    }

    /**
     * Merge two contacts. `primary_contact_id` survives; `merged_contact_id`
     * is folded into it. Both must be numeric ids.
     *
     * `target_id` / `source_id` are accepted as aliases (target => primary,
     * source => merged).
     *
     * @param array<string, mixed> $input
     */
    public function merge(array $input, RequestOptions|array|null $options = null): mixed
    {
        $primary = $input['primary_contact_id'] ?? $input['target_id'] ?? null;
        $merged = $input['merged_contact_id'] ?? $input['source_id'] ?? null;
        if ($primary === null || $merged === null) {
            throw new InvalidArgumentException('contacts->merge: primary_contact_id and merged_contact_id are required');
        }

        return $this->call('POST', '/contacts/merge', [
            'primary_contact_id' => (int) $primary,
            'merged_contact_id' => (int) $merged,
        ], $options);
    }

    // ─── Conversation actions ───────────────────────────────────────────

    /** Open / re-open the contact's most recent conversation. */
    public function openConversation(int|string $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/open', null, $options);
    }

    public function closeConversation(int|string $id, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/close', null, $options);
    }

    /**
     * Assign / unassign the open conversation. `assignee` accepts null, a user
     * id, an email, or `team:<id>`.
     *
     * @param array<string, mixed> $input
     */
    public function assignConversation(int|string $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        // `['assignee' => null]` must reach the server as {"assignee":null}.
        return $this->call('POST', '/contacts/' . self::id($id) . '/assignee', $input, $options);
    }

    /** `status`: open | close | closed | resolved. @param array<string, mixed> $input */
    public function setConversationStatus(int|string $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/conversation/status', $input, $options);
    }

    /** Add an internal note. @param array<string, mixed> $input e.g. ['text' => '...'] */
    public function comment(int|string $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/comment', $input, $options);
    }

    /** Channels the contact is reachable on. */
    public function channels(int|string $id): mixed
    {
        return $this->fetch('/contacts/' . self::id($id) . '/channels');
    }

    // ─── Tags ───────────────────────────────────────────────────────────

    public function attachTag(int|string $id, int $tagId, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/tags', ['tag_id' => $tagId], $options);
    }

    public function detachTag(int|string $id, int $tagId, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', '/contacts/' . self::id($id) . '/tags/' . $tagId, null, $options);
    }

    /**
     * Attach tags by name, creating the missing ones.
     *
     * @param array{names: list<string>}|list<string> $input
     */
    public function attachTagsByName(int|string $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/tags-by-name', self::names($input), $options);
    }

    /**
     * Detach tags by name. This DELETE carries a JSON body.
     *
     * @param array{names: list<string>}|list<string> $input
     */
    public function detachTagsByName(int|string $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('DELETE', '/contacts/' . self::id($id) . '/tags-by-name', self::names($input), $options);
    }

    // ─── Contact-scoped messaging ───────────────────────────────────────

    /**
     * Send a message in the contact's implicit conversation, respond.io envelope:
     * `['channelId' => 1, 'message' => ['type' => 'text', 'text' => 'Hi']]`.
     *
     * @param array<string, mixed> $input
     */
    public function sendMessage(int|string $id, array $input, RequestOptions|array|null $options = null): mixed
    {
        return $this->call('POST', '/contacts/' . self::id($id) . '/message', $input, $options);
    }

    /** @param array<string, mixed> $params */
    public function listMessages(int|string $id, array $params = []): Page
    {
        return $this->pageAt('/contacts/' . self::id($id) . '/message/list', $params);
    }

    public function getMessage(int|string $id, int $messageId): mixed
    {
        return $this->fetch('/contacts/' . self::id($id) . '/message/' . $messageId);
    }

    /**
     * @param array<mixed> $input
     * @return array<string, mixed>
     */
    private static function names(array $input): array
    {
        return array_is_list($input) ? ['names' => array_values($input)] : $input;
    }
}
