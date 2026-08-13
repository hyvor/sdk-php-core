<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Generic fallback for API error responses that don't map to a more
 * specific exception type.
 */
final class ApiException extends HyvorApiException
{
}
