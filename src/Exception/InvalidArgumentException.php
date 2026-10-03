<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/** Thrown before any request is sent when the input cannot be valid. */
class InvalidArgumentException extends \InvalidArgumentException implements OmnifoxException
{
}
