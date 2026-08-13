<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Testing;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements ClientInterface
{
    /** @var array<int, ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    /** @var RequestInterface[] */
    public array $requests = [];

    public function queueResponse(ResponseInterface $response): void
    {
        $this->queue[] = $response;
    }

    public function queueException(ClientExceptionInterface $exception): void
    {
        $this->queue[] = $exception;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new FakeHttpTransportException('FakeHttpClient: no queued response left.');
        }

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }
}
