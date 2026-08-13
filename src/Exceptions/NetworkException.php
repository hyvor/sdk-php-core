<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Thrown when a request could not be sent at all (connection failure,
 * timeout, DNS error, etc), as opposed to the server returning an error
 * status.
 */
final class NetworkException extends HyvorApiException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, null, null, $previous);
    }
}
