<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Thrown when the API rejects the request's credentials (HTTP 401/403).
 */
final class AuthenticationException extends HyvorApiException
{
}
