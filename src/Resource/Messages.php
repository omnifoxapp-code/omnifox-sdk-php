<?php

declare(strict_types=1);

namespace Omnifox\Resource;

use Omnifox\Exception\InvalidArgumentException;
use Omnifox\RequestOptions;

/** `/messages`: send without resolving a conversation first. */
final class Messages extends AbstractResource
{
    /**
     * Send a message to a contact.
     *
     * Convenience shape:
     *   ['contact' => 42 | 'phone:+593...', 'channel_id' => 1, 'text' => 'Hi']
     *   ['contact' => 42, 'template' => ['name' => 'x', 'language' => 'es']]
     * The wire shape (`contact_id` + `type` + `content_text` ...) is sent as is.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function send(array $input, RequestOptions|array|null $options = null): array
    {
        return $this->client->request('POST', '/messages', null, self::normalise($input), $options);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function normalise(array $input): array
    {
        if (isset($input['type'], $input['contact_id'])) {
            return $input;
        }
        $contactId = $input['contact_id'] ?? $input['contact'] ?? null;
        if ($contactId === null) {
            throw new InvalidArgumentException("messages->send: 'contact' or 'contact_id' is required");
        }
        $template = $input['whatsapp_template'] ?? $input['template'] ?? null;
        if ($template !== null) {
            return self::compact([
                'contact_id' => $contactId,
                'channel_id' => $input['channel_id'] ?? null,
                'type' => 'template',
                'template' => $template,
            ]);
        }
        if (isset($input['text'])) {
            return self::compact([
                'contact_id' => $contactId,
                'channel_id' => $input['channel_id'] ?? null,
                'type' => 'text',
                'content_text' => $input['text'],
            ]);
        }

        return self::compact([
            'contact_id' => $contactId,
            'channel_id' => $input['channel_id'] ?? null,
            'type' => $input['type'] ?? 'text',
            'content_text' => $input['content_text'] ?? null,
            'content_payload' => $input['content_payload'] ?? null,
        ]);
    }
}
