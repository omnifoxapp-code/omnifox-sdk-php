<?php

declare(strict_types=1);

namespace Omnifox;

/**
 * Per-call overrides. Every write method takes one as its last argument,
 * either as an instance or as a plain array with the same keys:
 *
 *     $client->contacts->create([...], ['idempotencyKey' => $uuid]);
 */
final class RequestOptions
{
    /**
     * @param string|null               $idempotencyKey Sent as the `Idempotency-Key` header.
     * @param float|null                $timeout        Seconds. Only honoured by the default Guzzle transport.
     * @param int|null                  $maxRetries     Retries on 429 / 5xx / connection errors for this call.
     * @param array<string, string>     $headers        Extra headers merged over the defaults.
     */
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?float $timeout = null,
        public readonly ?int $maxRetries = null,
        public readonly array $headers = [],
    ) {
    }

    /** @param self|array<string, mixed>|null $options */
    public static function from(self|array|null $options): self
    {
        if ($options instanceof self) {
            return $options;
        }
        if ($options === null || $options === []) {
            return new self();
        }

        return new self(
            idempotencyKey: isset($options['idempotencyKey']) ? (string) $options['idempotencyKey'] : null,
            timeout: isset($options['timeout']) ? (float) $options['timeout'] : null,
            maxRetries: isset($options['maxRetries']) ? (int) $options['maxRetries'] : null,
            headers: is_array($options['headers'] ?? null) ? $options['headers'] : [],
        );
    }
}
