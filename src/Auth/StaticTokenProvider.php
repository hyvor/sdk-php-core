<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Auth;

/**
 * Returns a fixed, pre-obtained token (e.g. a JWT issued by an internal
 * integration) on every call.
 */
final class StaticTokenProvider implements TokenProviderInterface
{
    public function __construct(private readonly string $token)
    {
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
