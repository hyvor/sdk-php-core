<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Thrown when the API responds with a server error (HTTP 5xx).
 */
final class ServerErrorException extends HyvorApiException
{
}
