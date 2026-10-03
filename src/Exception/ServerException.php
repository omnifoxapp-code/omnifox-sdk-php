<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 5xx - the server failed. Retried automatically before being thrown. */
class ServerException extends ApiException
{
}
