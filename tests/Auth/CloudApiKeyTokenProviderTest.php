<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Tests\Auth;

use Hyvor\Sdk\Auth\CloudApiKeyTokenProvider;
use Hyvor\Sdk\Exceptions\AuthenticationException;
use Hyvor\Sdk\Testing\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CloudApiKeyTokenProviderTest extends TestCase
{
    public function testExchangesCloudApiKeyForToken(): void
    {
        $http = new FakeHttpClient();
        $http->queueResponse(new Response(200, [], json_encode([
            'token' => 'short-lived-jwt',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR)));

        $factory = new Psr17Factory();
        $provider = new CloudApiKeyTokenProvider('cloud-key', 'https://hyvor.com', $http, $factory, $factory, new NullLogger());

        self::assertSame('short-lived-jwt', $provider->getToken());
        self::assertSame('short-lived-jwt', $provider->getToken(), 'should be cached, not re-fetched');
        self::assertCount(1, $http->requests);
        self::assertSame('https://hyvor.com/api/cloud/token', (string) $http->requests[0]->getUri());
    }

    public function testThrowsAuthenticationExceptionOnFailedExchange(): void
    {
        $http = new FakeHttpClient();
        $http->queueResponse(new Response(401, [], json_encode(['message' => 'Invalid API key'], JSON_THROW_ON_ERROR)));

        $factory = new Psr17Factory();
        $provider = new CloudApiKeyTokenProvider('bad-key', 'https://hyvor.com', $http, $factory, $factory, new NullLogger());

        $this->expectException(AuthenticationException::class);
        $provider->getToken();
    }
}
