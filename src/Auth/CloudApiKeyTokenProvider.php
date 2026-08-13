<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Auth;

use Hyvor\Sdk\Exceptions\AuthenticationException;
use Hyvor\Sdk\Version;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Exchanges a Cloud API key for a short-lived JWT, caching it until shortly
 * before it expires.
 */
final class CloudApiKeyTokenProvider implements TokenProviderInterface
{
    private const TOKEN_EXCHANGE_PATH = '/api/cloud/token';
    private const EXPIRY_LEEWAY_SECONDS = 30;

    private ?string $cachedToken = null;
    private ?int $cachedTokenExpiresAt = null;

    public function __construct(
        private readonly string $cloudApiKey,
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getToken(): string
    {
        if ($this->cachedToken !== null && $this->cachedTokenExpiresAt > time() + self::EXPIRY_LEEWAY_SECONDS) {
            return $this->cachedToken;
        }

        return $this->exchangeToken();
    }

    private function exchangeToken(): string
    {
        $this->logger->debug('Hyvor SDK: exchanging cloud API key for a JWT token.');

        $request = $this->requestFactory
            ->createRequest('POST', $this->baseUrl . self::TOKEN_EXCHANGE_PATH)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'hyvor/sdk-php/' . Version::VERSION)
            ->withBody($this->streamFactory->createStream(
                json_encode(['api_key' => $this->cloudApiKey], JSON_THROW_ON_ERROR)
            ));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new AuthenticationException(
                'Failed to exchange the cloud API key for a JWT token: ' . $e->getMessage(),
                null,
                null,
                $e,
            );
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new AuthenticationException(
                "Failed to exchange the cloud API key for a JWT token (HTTP {$response->getStatusCode()}).",
                $response->getStatusCode(),
            );
        }

        $raw = (string) $response->getBody();
        /** @var array<mixed> $data */
        $data = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $token = $data['token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new AuthenticationException('Token exchange response did not include a token.');
        }

        $this->cachedToken = $token;
        $this->cachedTokenExpiresAt = time() + (is_int($expiresIn) ? $expiresIn : 3600);

        return $token;
    }
}
