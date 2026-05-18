<?php

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Tests\Unit\Platform;

use Aligny\TailwindBuilder\Platform\PlatformDetector;
use PHPUnit\Framework\TestCase;

final class PlatformDetectorTest extends TestCase
{
    public function testReturnsConfiguredPlatformWhenNotAuto(): void
    {
        $detector = new PlatformDetector();

        self::assertSame('linux-x64', $detector->detect('4.0.7', 'linux-x64'));
    }

    public function testReturnsExpectedBinaryNameForWindows(): void
    {
        $detector = new PlatformDetector();

        self::assertSame('tailwindcss-windows-x64.exe', $detector->getBinaryName('4.0.7', 'windows-x64'));
    }

    public function testReturnsExpectedBinaryNameForLinux(): void
    {
        $detector = new PlatformDetector();

        self::assertSame('tailwindcss-linux-x64', $detector->getBinaryName('3.4.17', 'linux-x64'));
    }
}
