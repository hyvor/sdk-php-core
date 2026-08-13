<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Auth;

/**
 * Resolves the bearer token used to authenticate requests to the Hyvor API.
 *
 * Implement this to plug in a custom way of obtaining a token (e.g. one
 * issued by an internal integration).
 */
interface TokenProviderInterface
{
    public function getToken(): string;
}
