<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 422: the payload failed server-side validation. */
class ValidationException extends ApiException
{
    /**
     * @param array<string, mixed>|null    $details
     * @param array<string, list<string>>  $fieldErrors
     */
    public function __construct(
        string $message,
        int $httpStatus,
        int $errorCode,
        ?array $details = null,
        ?string $requestId = null,
        mixed $body = null,
        private readonly array $fieldErrors = [],
    ) {
        parent::__construct($message, $httpStatus, $errorCode, $details, $requestId, $body);
    }

    /**
     * Field path => list of messages, as Laravel reports them.
     *
     * @return array<string, list<string>>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
