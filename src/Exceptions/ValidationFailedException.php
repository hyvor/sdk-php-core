<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Thrown when the API rejects a request due to invalid input (HTTP 422).
 */
final class ValidationFailedException extends HyvorApiException
{
    /**
     * @param array<string, array<int, string>> $errors Field name => list of error messages.
     * @param array<mixed>|null $responseBody
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        ?array $responseBody = null,
    ) {
        parent::__construct($message, 422, $responseBody);
    }
}
