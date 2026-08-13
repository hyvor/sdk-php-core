<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Base class for all errors returned by the Hyvor API.
 */
abstract class HyvorApiException extends \RuntimeException
{
    /**
     * @param array<mixed>|null $responseBody The decoded JSON error response body, if any.
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
