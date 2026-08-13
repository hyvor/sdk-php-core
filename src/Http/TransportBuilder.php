<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Hyvor\Sdk\Auth\CloudApiKeyTokenProvider;
use Hyvor\Sdk\Auth\TokenProviderInterface;
use Hyvor\Sdk\Serialization\SerializerFactory;
use Hyvor\Sdk\Version;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Serializer;

/**
 * Builds a product-scoped {@see Transport} from the friendly constructor
 * parameters every product client accepts (cloudApiKey, httpClient, ...).
 * Each product client's constructor delegates here so the discovery/auth/
 * serializer wiring lives in one place instead of being duplicated per
 * product.
 *
 * @internal
 */
final class TransportBuilder
{
    public static function build(
        string $product,
        ?string $cloudApiKey = null,
        ?TokenProviderInterface $tokenProvider = null,
        ?string $productUrl = null,
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        int $retryMaxAttempts = 3,
        float $retryBackoffFactor = 2.0,
        string $cloudInstance = 'https://hyvor.com',
        ?Serializer $serializer = null,
    ): Transport {
        if ($cloudApiKey !== null && $tokenProvider !== null) {
            throw new \InvalidArgumentException('Provide either cloudApiKey or tokenProvider, not both.');
        }

        $logger ??= new NullLogger();
        $httpClient ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();

        if ($tokenProvider === null && $cloudApiKey !== null) {
            $tokenProvider = new CloudApiKeyTokenProvider(
                $cloudApiKey,
                $cloudInstance,
                $httpClient,
                $requestFactory,
                $streamFactory,
                $logger,
            );
        }

        $serializer ??= SerializerFactory::create();

        return new Transport(
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            logger: $logger,
            tokenProvider: $tokenProvider,
            baseUrl: $productUrl ?? ProductBaseUrl::resolve($cloudInstance, $product),
            defaultRetryMaxAttempts: $retryMaxAttempts,
            defaultRetryBackoffFactor: $retryBackoffFactor,
            userAgent: 'hyvor/sdk-php-' . $product . '/' . Version::VERSION,
            serializer: $serializer,
        );
    }
}
