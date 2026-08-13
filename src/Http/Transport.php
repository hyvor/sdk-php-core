<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Http;

use Hyvor\Sdk\Auth\TokenProviderInterface;
use Hyvor\Sdk\Exceptions\AuthenticationException;
use Hyvor\Sdk\Exceptions\HyvorApiException;
use Hyvor\Sdk\Exceptions\NetworkException;
use Hyvor\Sdk\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Executes API requests: builds the HTTP request, authenticates it, retries
 * on transient failures with exponential backoff, and maps error responses
 * to typed exceptions.
 *
 * @internal
 */
final class Transport
{
    private const BASE_RETRY_DELAY_MS = 200;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
        private readonly ?TokenProviderInterface $tokenProvider,
        private readonly string $baseUrl,
        private readonly int $defaultRetryMaxAttempts,
        private readonly float $defaultRetryBackoffFactor,
        private readonly string $userAgent,
        private readonly Serializer $serializer,
    ) {
    }

    /**
     * @template T of object
     * @param array<mixed> $data
     * @param class-string<T> $class
     * @return T
     */
    public function denormalize(array $data, string $class): object
    {
        return $this->serializer->denormalize($data, $class, null);
    }

    /**
     * @template T of object
     * @param array<mixed> $data
     * @param class-string<T> $class
     * @return T[]
     */
    public function denormalizeList(array $data, string $class): array
    {
        /** @var T[] $list */
        $list = $this->serializer->denormalize($data, $class . '[]', null);

        return $list;
    }

    /**
     * Normalizes a request DTO to a JSON-ready array. Null properties are
     * skipped: throughout the Console API, a null field on a request means
     * "leave unchanged" (updates) or "no filter" (list queries), and API
     * keys created before a field existed should not have it reset by an
     * SDK upgrade that merely adds the property.
     *
     * @return array<mixed>
     */
    public function normalize(object $data): array
    {
        /** @var array<mixed> $normalized */
        $normalized = $this->serializer->normalize($data, null, [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        return $normalized;
    }

    /**
     * @param array<mixed>|null $jsonBody
     * @param array<string, string> $extraHeaders
     * @return array<mixed>
     *
     * @throws HyvorApiException
     */
    public function request(
        string $method,
        string $path,
        ?array $jsonBody = null,
        ?RequestOptions $options = null,
        ?string $apiKeyOverride = null,
        array $extraHeaders = [],
    ): array {
        return $this->execute(
            $options,
            fn () => $this->buildJsonRequest($method, $path, $jsonBody, $apiKeyOverride, $options, $extraHeaders),
        );
    }

    /**
     * Sends a `multipart/form-data` request, for endpoints that accept file
     * uploads (e.g. `POST /media/image`).
     *
     * @param array<string, scalar|null> $fields
     * @param array<string, UploadedFile> $files
     * @param array<string, string> $extraHeaders
     * @return array<mixed>
     *
     * @throws HyvorApiException
     */
    public function requestMultipart(
        string $method,
        string $path,
        array $fields,
        array $files,
        ?RequestOptions $options = null,
        ?string $apiKeyOverride = null,
        array $extraHeaders = [],
    ): array {
        return $this->execute(
            $options,
            fn () => $this->buildMultipartRequest($method, $path, $fields, $files, $apiKeyOverride, $options, $extraHeaders),
        );
    }

    /**
     * @param callable(): RequestInterface $buildRequest
     * @return array<mixed>
     *
     * @throws HyvorApiException
     */
    private function execute(?RequestOptions $options, callable $buildRequest): array
    {
        $maxAttempts = max(1, $options->retryMaxAttempts ?? $this->defaultRetryMaxAttempts);
        $backoffFactor = $options->retryBackoffFactor ?? $this->defaultRetryBackoffFactor;

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $request = $buildRequest();

            try {
                $response = $this->send($request);
            } catch (ClientExceptionInterface $e) {
                $lastException = new NetworkException($e->getMessage(), $e);

                if ($attempt < $maxAttempts) {
                    $this->waitBeforeRetry($attempt, $backoffFactor, null, $e->getMessage());
                    continue;
                }

                throw $lastException;
            }

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return self::decodeSuccessBody($response);
            }

            $exception = ErrorMapper::fromResponse($response, $request);

            if (ErrorMapper::isRetryable($response->getStatusCode()) && $attempt < $maxAttempts) {
                $lastException = $exception;
                $this->waitBeforeRetry($attempt, $backoffFactor, $response, $exception->getMessage());
                continue;
            }

            throw $exception;
        }

        throw $lastException ?? new NetworkException('Request failed after retries.');
    }

    /**
     * @param array<mixed>|null $jsonBody
     * @param array<string, string> $extraHeaders
     */
    private function buildJsonRequest(
        string $method,
        string $path,
        ?array $jsonBody,
        ?string $apiKeyOverride,
        ?RequestOptions $options,
        array $extraHeaders,
    ): RequestInterface {
        $request = $this->baseRequest($method, $path, $apiKeyOverride, $options, $extraHeaders);

        if ($jsonBody !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($jsonBody, JSON_THROW_ON_ERROR)));
        }

        return $request;
    }

    /**
     * @param array<string, scalar|null> $fields
     * @param array<string, UploadedFile> $files
     * @param array<string, string> $extraHeaders
     */
    private function buildMultipartRequest(
        string $method,
        string $path,
        array $fields,
        array $files,
        ?string $apiKeyOverride,
        ?RequestOptions $options,
        array $extraHeaders,
    ): RequestInterface {
        [$boundary, $body] = MultipartBody::build($fields, $files);

        return $this->baseRequest($method, $path, $apiKeyOverride, $options, $extraHeaders)
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($this->streamFactory->createStream($body));
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    private function baseRequest(
        string $method,
        string $path,
        ?string $apiKeyOverride,
        ?RequestOptions $options,
        array $extraHeaders,
    ): RequestInterface {
        $request = $this->requestFactory
            ->createRequest($method, $this->baseUrl . $path)
            ->withHeader('Authorization', 'Bearer ' . $this->resolveToken($apiKeyOverride))
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        // $extraHeaders carries structural headers (client-level default
        // headers from TalkClient::website(), and endpoint-specific ones
        // like X-ID-Type); $options->headers is the caller's per-call
        // override and is applied last so it always wins.
        foreach ([...$extraHeaders, ...($options->headers ?? [])] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        $this->logger->debug('Hyvor SDK: sending request', ['method' => $request->getMethod(), 'url' => (string) $request->getUri()]);

        $response = $this->httpClient->sendRequest($request);

        $this->logger->debug('Hyvor SDK: received response', [
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    /**
     * @throws AuthenticationException
     */
    private function resolveToken(?string $apiKeyOverride): string
    {
        if ($apiKeyOverride !== null) {
            return $apiKeyOverride;
        }

        if ($this->tokenProvider !== null) {
            return $this->tokenProvider->getToken();
        }

        throw new AuthenticationException(
            'No credentials configured. Provide a cloudApiKey/tokenProvider to HyvorClient, '
            . 'or pass a resource-level API key when accessing the resource (e.g. $client->talk->website($id, $apiKey)).',
        );
    }

    /**
     * @return array<mixed>
     */
    private static function decodeSuccessBody(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        /** @var array<mixed> $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function waitBeforeRetry(int $attempt, float $backoffFactor, ?ResponseInterface $response, string $reason): void
    {
        $delayMs = $this->computeBackoffDelayMs($attempt, $backoffFactor, $response);

        $this->logger->warning('Hyvor SDK: retrying request', [
            'attempt' => $attempt,
            'delayMs' => $delayMs,
            'reason' => $reason,
        ]);

        usleep($delayMs * 1000);
    }

    private function computeBackoffDelayMs(int $attempt, float $backoffFactor, ?ResponseInterface $response): int
    {
        $retryAfter = $response?->getHeaderLine('retry-after');
        if ($retryAfter !== null && $retryAfter !== '' && is_numeric($retryAfter)) {
            return (int) $retryAfter * 1000;
        }

        return (int) (self::BASE_RETRY_DELAY_MS * ($backoffFactor ** ($attempt - 1)));
    }
}
