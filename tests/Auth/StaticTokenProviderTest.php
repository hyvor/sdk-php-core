<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Tests\Auth;

use Hyvor\Sdk\Auth\StaticTokenProvider;
use PHPUnit\Framework\TestCase;

final class StaticTokenProviderTest extends TestCase
{
    public function testReturnsTheGivenTokenEveryTime(): void
    {
        $provider = new StaticTokenProvider('static-jwt');

        self::assertSame('static-jwt', $provider->getToken());
        self::assertSame('static-jwt', $provider->getToken());
    }
}
