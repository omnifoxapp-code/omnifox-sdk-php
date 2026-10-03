<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 401 - the token is missing, wrong, expired or revoked. */
class AuthenticationException extends ApiException
{
}
