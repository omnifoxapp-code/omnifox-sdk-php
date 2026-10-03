<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 429: too many requests. Retried automatically (honouring Retry-After) before being thrown. */
class RateLimitException extends ApiException
{
    /** @param array<string, mixed>|null $details */
    public function __construct(
        string $message,
        int $httpStatus,
        int $errorCode,
        ?array $details = null,
        ?string $requestId = null,
        mixed $body = null,
        private readonly int $retryAfter = 0,
    ) {
        parent::__construct($message, $httpStatus, $errorCode, $details, $requestId, $body);
    }

    /** Seconds the server asked us to wait (0 when it did not say). */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
