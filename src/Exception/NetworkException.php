<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/**
 * The request never produced an HTTP response: DNS failure, refused
 * connection, TLS error or timeout. Retriable connection failures are retried
 * automatically before this is thrown.
 */
class NetworkException extends \RuntimeException implements OmnifoxException
{
}
