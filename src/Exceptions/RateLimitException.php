<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Exceptions;

/**
 * Thrown when the API rate limit has been exceeded (HTTP 429).
 */
final class RateLimitException extends HyvorApiException
{
    /**
     * @param array<mixed>|null $responseBody
     */
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?array $responseBody = null,
    ) {
        parent::__construct($message, 429, $responseBody);
    }
}
