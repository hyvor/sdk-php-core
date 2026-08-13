<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Http;

/**
 * Resolves a product's own base URL (e.g. `https://talk.hyvor.com`) from the
 * configured cloud instance (e.g. `https://hyvor.com`), by prepending the
 * product's subdomain to the cloud instance's host.
 *
 * @internal
 */
final class ProductBaseUrl
{
    public static function resolve(string $cloudInstance, string $product): string
    {
        $parts = parse_url($cloudInstance);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $cloudInstance;
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return "{$scheme}://{$product}.{$host}{$port}";
    }
}
