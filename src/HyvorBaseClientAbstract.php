<?php

declare(strict_types=1);

namespace Hyvor\Sdk;

use Hyvor\Sdk\Auth\TokenProviderInterface;
use Hyvor\Sdk\Http\Transport;
use Hyvor\Sdk\Http\TransportBuilder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class for every product's entry-point client (`TalkClient`,
 * `PostClient`, ...). Builds the shared `Transport` from the friendly
 * constructor parameters every product client accepts, so that wiring
 * lives in one place instead of being duplicated per product.
 *
 * If `httpClient`/`requestFactory`/`streamFactory` are not given, they are
 * auto-discovered via php-http/discovery from whatever PSR-18/17
 * implementation is installed (e.g. guzzlehttp/guzzle, nyholm/psr7).
 *
 * @internal Depend on the concrete product client (e.g. `TalkClient`), not
 *  this class.
 */
abstract class HyvorBaseClientAbstract
{
    protected readonly Transport $transport;

    public function __construct(
        ?string $cloudApiKey = null,
        ?TokenProviderInterface $tokenProvider = null,
        /**
         * Overrides the product URL derived from `cloudInstance` - set this
         * for a self-hosted instance (e.g. `https://talk.example.com`).
         */
        ?string $productUrl = null,
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        int $retryMaxAttempts = 3,
        float $retryBackoffFactor = 2.0,
        /**
         * Only relevant for hyvor.com-hosted (cloud) usage - self-hosted
         * users should set `productUrl` instead.
         */
        string $cloudInstance = 'https://hyvor.com',
    ) {
        $this->transport = TransportBuilder::build(
            product: $this->product(),
            cloudApiKey: $cloudApiKey,
            tokenProvider: $tokenProvider,
            productUrl: $productUrl,
            logger: $logger,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            retryMaxAttempts: $retryMaxAttempts,
            retryBackoffFactor: $retryBackoffFactor,
            cloudInstance: $cloudInstance,
        );
    }

    /**
     * The product slug passed to {@see TransportBuilder::build()} (e.g.
     * `'talk'`), used to resolve the product's base URL and User-Agent.
     */
    abstract protected function product(): string;
}
