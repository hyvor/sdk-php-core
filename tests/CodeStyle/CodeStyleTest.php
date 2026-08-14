<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Tests\CodeStyle;

use PHPUnit\Framework\TestCase;

/**
 * Runs PHP_CodeSniffer against every package's `src/` and `tests/`
 * directories, using the shared ruleset at the monorepo root
 * (`phpcs.xml.dist`), so a style violation fails the test suite instead of
 * relying on a separate CI job to catch it.
 *
 * Only runs inside the hyvor/sdk monorepo, where the root ruleset and
 * sibling packages are available: a standalone install of this package
 * (see `.github/workflows/php-publish.yml`) doesn't carry the monorepo
 * root, so the test skips itself there instead of failing.
 */
final class CodeStyleTest extends TestCase
{
    public function testCodeStyle(): void
    {
        $monorepoRoot = dirname(__DIR__, 4);
        $phpcsBinary = $monorepoRoot . '/vendor/bin/phpcs';
        $ruleset = $monorepoRoot . '/phpcs.xml.dist';

        if (!is_file($phpcsBinary) || !is_file($ruleset)) {
            self::markTestSkipped('Not running inside the hyvor/sdk monorepo (needs the root vendor/bin/phpcs and phpcs.xml.dist).');
        }

        $process = proc_open(
            [$phpcsBinary, '--standard=' . $ruleset],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $monorepoRoot,
        );

        self::assertIsResource($process, 'Failed to start PHP_CodeSniffer.');

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, "PHP_CodeSniffer found code style violations:\n" . $output . $errorOutput);
    }
}
