<?php

declare(strict_types=1);

namespace Hyvor\Sdk;

/**
 * Per-request overrides for retries and idempotency. Timeouts are configured
 * on the PSR-18 HTTP client passed to HyvorClient, not here.
 * Any field left null falls back to the client's default configuration.
 *
 * `headers` are merged into the request, on top of any default headers set
 * via `TalkClient::website()`. Useful, for example, to authenticate as a
 * specific moderator instead of the website owner (see the Console API's
 * "User Authentication" docs) by setting X-AUTH-USER-EMAIL or
 * X-AUTH-USER-SSO-ID.
 */
final class RequestOptions
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly ?int $retryMaxAttempts = null,
        public readonly ?float $retryBackoffFactor = null,
        public readonly array $headers = [],
    ) {
    }
}
