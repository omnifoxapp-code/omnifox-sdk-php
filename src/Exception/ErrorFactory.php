<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/**
 * Turns a failed HTTP answer into the right exception subclass.
 *
 * @internal
 */
final class ErrorFactory
{
    public static function fromResponse(int $status, mixed $body, ?string $requestId, int $retryAfter = 0): ApiException
    {
        $code = $status * 100;
        $message = 'HTTP ' . $status;
        $details = null;
        $fieldErrors = [];

        if (is_array($body)) {
            // Canonical envelope {code, message, details}.
            if (isset($body['code'], $body['message']) && is_int($body['code']) && is_string($body['message'])) {
                $code = $body['code'];
                $message = $body['message'];
                $details = is_array($body['details'] ?? null) ? $body['details'] : null;
            } elseif (isset($body['message']) && is_string($body['message'])) {
                // Laravel style {message, errors}.
                $message = $body['message'];
                $details = is_array($body['errors'] ?? null) ? $body['errors'] : null;
            }

            $errors = $body['errors'] ?? $details;
            if (is_array($errors)) {
                foreach ($errors as $field => $messages) {
                    if (is_array($messages) && $messages !== [] && array_is_list($messages)
                        && count(array_filter($messages, 'is_string')) === count($messages)) {
                        $fieldErrors[(string) $field] = $messages;
                    }
                }
            }
        } elseif (is_string($body) && $body !== '' && strlen($body) < 300) {
            $message = 'HTTP ' . $status . ': ' . trim($body);
        }

        if ($status === 429 || $code === 42900) {
            return new RateLimitException($message, $status, $code, $details, $requestId, $body, $retryAfter);
        }
        if ($status === 422 || $code === 42200) {
            return new ValidationException($message, $status, $code, $details, $requestId, $body, $fieldErrors);
        }

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 402 => PaymentRequiredException::class,
            $status === 403 => PermissionDeniedException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status >= 500 => ServerException::class,
            default => ApiException::class,
        };

        return new $class($message, $status, $code, $details, $requestId, $body);
    }
}
