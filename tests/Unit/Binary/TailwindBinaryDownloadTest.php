<?php

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Tests\Unit\Binary;

use Aligny\TailwindBuilder\Binary\TailwindBinary;
use Aligny\TailwindBuilder\Platform\PlatformDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

final class TailwindBinaryDownloadTest extends TestCase
{
    public function testDownloadToFileWritesStreamContentToTargetPath(): void
    {
        $cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tailwind-binary-download-test-' . bin2hex(random_bytes(4));
        mkdir($cacheDir, 0777, true);

        $targetPath = $cacheDir . DIRECTORY_SEPARATOR . 'tailwindcss-test-bin';
        $expectedContent = str_repeat('tailwind-binary-content-', 1024);
        $url = 'data://text/plain;base64,' . base64_encode($expectedContent);

        $binary = new TailwindBinary(
            $cacheDir,
            new PlatformDetector(),
            new NullOutput()
        );

        $method = new \ReflectionMethod(TailwindBinary::class, 'downloadToFile');
        $method->setAccessible(true);
        $method->invoke($binary, $url, $targetPath);

        self::assertFileExists($targetPath);
        $actualContent = file_get_contents($targetPath);
        self::assertIsString($actualContent);
        self::assertSame($expectedContent, $actualContent);

        @unlink($targetPath);
        @rmdir($cacheDir);
    }
}
