<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 409 - e.g. booking a calendar slot that is already taken. */
class ConflictException extends ApiException
{
}
