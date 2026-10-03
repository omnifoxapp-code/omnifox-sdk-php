<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 404 - the record does not exist or belongs to another workspace. */
class NotFoundException extends ApiException
{
}
