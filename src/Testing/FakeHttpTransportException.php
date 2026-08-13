<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Testing;

use Psr\Http\Client\ClientExceptionInterface;

final class FakeHttpTransportException extends \RuntimeException implements ClientExceptionInterface
{
}
