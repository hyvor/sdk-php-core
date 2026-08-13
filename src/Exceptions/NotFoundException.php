<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Thrown when the requested resource does not exist (HTTP 404).
 */
final class NotFoundException extends HyvorApiException
{
}
