<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** HTTP 403 - plan gate, token ability (read:crm, write:projects...) or a workspace mismatch. */
class PermissionDeniedException extends ApiException
{
}
