<?php

declare(strict_types=1);

namespace Omnifox\Exception;

/**
 * Marker interface implemented by every exception the SDK throws.
 *
 * `catch (OmnifoxException $e)` catches API errors, transport errors and
 * client-side argument errors alike.
 */
interface OmnifoxException extends \Throwable
{
}
