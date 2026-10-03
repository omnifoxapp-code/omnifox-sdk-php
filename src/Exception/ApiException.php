<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/**
 * Base class for every non-2xx answer from the Omnifox API.
 *
 * The canonical error envelope (`code`, `message`, `details`) is preserved.
 * Catch this class to handle any HTTP failure; the subclasses narrow the cause
 * by status code.
 */
class ApiException extends \RuntimeException implements OmnifoxException
{
    /**
     * @param array<string, mixed>|null $details
     * @param mixed                     $body    Decoded response body (array, string or null).
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly int $errorCode,
        private readonly ?array $details = null,
        private readonly ?string $requestId = null,
        private readonly mixed $body = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    /** Raw HTTP status, e.g. 404. */
    public function getStatusCode(): int
    {
        return $this->httpStatus;
    }

    /** Machine code from the canonical envelope, e.g. 40400. Defaults to status * 100. */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed>|null Structured details (Laravel `errors` map on validation). */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /** `X-Request-Id` response header, if any. Quote it when contacting support. */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /** The decoded response body, untouched. */
    public function getBody(): mixed
    {
        return $this->body;
    }
}
