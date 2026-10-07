<?php

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Tests\Unit\Binary;

use Aligny\TailwindBuilder\Binary\TailwindBinary;
use Aligny\TailwindBuilder\Platform\PlatformDetector;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\NullOutput;

final class TailwindBinaryCacheTest extends TestCase
{
    private const VERSION = 'v4.3.3';
    private const PLATFORM = 'linux-x64';
    private const BINARY_NAME = 'tailwindcss-linux-x64';

    private string $cacheDir;
    private TailwindBinary $binary;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tailwind-binary-cache-test-' . bin2hex(random_bytes(4));
        mkdir($this->cacheDir, 0777, true);

        $this->binary = new TailwindBinary($this->cacheDir, new PlatformDetector(), new NullOutput());
    }

    protected function tearDown(): void
    {
        putenv('GITHUB_TOKEN');
        putenv('GH_TOKEN');
        $this->deleteDirectory($this->cacheDir);
    }

    public function testResolveLatestVersionUsesFreshCacheWithoutCallingApi(): void
    {
        file_put_contents(
            $this->cacheDir . DIRECTORY_SEPARATOR . 'latest-version.json',
            json_encode(['tag' => 'v4.9.9', 'checked_at' => time()])
        );

        self::assertSame('v4.9.9', $this->binary->resolveVersion('latest'));
        self::assertSame('v4.9.9', $this->binary->resolveVersion(''));
    }

    public function testResolveExplicitVersionIsNormalized(): void
    {
        self::assertSame('v4.2.0', $this->binary->resolveVersion(' 4.2.0 '));
        self::assertSame('v4.2.0', $this->binary->resolveVersion('V4.2.0'));
    }

    public function testIsLatestAlias(): void
    {
        self::assertTrue(TailwindBinary::isLatestAlias('latest'));
        self::assertTrue(TailwindBinary::isLatestAlias(' LATEST '));
        self::assertTrue(TailwindBinary::isLatestAlias(''));
        self::assertFalse(TailwindBinary::isLatestAlias('v4.3.3'));
    }

    public function testCacheHitIsVerifiedAgainstStoredChecksumWithoutCallingApi(): void
    {
        $binaryPath = $this->createCachedBinary('binary-content');
        file_put_contents($binaryPath . '.sha256', hash('sha256', 'binary-content') . PHP_EOL);

        self::assertSame($binaryPath, $this->binary->resolvePath(null, self::VERSION, self::PLATFORM));
    }

    public function testCacheHitFailsWhenStoredChecksumDoesNotMatch(): void
    {
        $binaryPath = $this->createCachedBinary('tampered-content');
        file_put_contents($binaryPath . '.sha256', hash('sha256', 'binary-content'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Checksum mismatch');

        $this->binary->resolvePath(null, self::VERSION, self::PLATFORM);
    }

    public function testVerifiedChecksumIsStoredNextToCachedBinary(): void
    {
        $binaryPath = $this->createCachedBinary('binary-content');
        $checksum = hash('sha256', 'binary-content');

        $this->binary->resolvePath(null, self::VERSION, self::PLATFORM, 'sha256:' . strtoupper($checksum));

        self::assertFileExists($binaryPath . '.sha256');
        self::assertSame($checksum, trim((string) file_get_contents($binaryPath . '.sha256')));
    }

    public function testGitHubApiHeadersAreAnonymousWithoutToken(): void
    {
        putenv('GITHUB_TOKEN');
        putenv('GH_TOKEN');

        self::assertSame(['Accept: application/vnd.github+json'], TailwindBinary::buildGitHubApiHeaders());
    }

    public function testGitHubApiHeadersUseGitHubTokenFirst(): void
    {
        putenv('GITHUB_TOKEN=github-token');
        putenv('GH_TOKEN=gh-token');

        self::assertContains('Authorization: Bearer github-token', TailwindBinary::buildGitHubApiHeaders());
    }

    public function testGitHubApiHeadersFallBackToGhToken(): void
    {
        putenv('GITHUB_TOKEN');
        putenv('GH_TOKEN=gh-token');

        self::assertContains('Authorization: Bearer gh-token', TailwindBinary::buildGitHubApiHeaders());
    }

    private function createCachedBinary(string $content): string
    {
        $directory = $this->cacheDir . DIRECTORY_SEPARATOR . self::VERSION;
        mkdir($directory, 0777, true);

        $binaryPath = $directory . DIRECTORY_SEPARATOR . self::BINARY_NAME;
        file_put_contents($binaryPath, $content);

        return $binaryPath;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($itemPath) ? $this->deleteDirectory($itemPath) : @unlink($itemPath);
        }

        @rmdir($path);
    }
}
