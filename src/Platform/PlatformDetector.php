<?php

/**
 * This file is part of Tailwind Builder.
 *
 * (c) Arnaud Ligny <arnaud@ligny.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Platform;

use RuntimeException;

final class PlatformDetector
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_PLATFORMS = [
        'linux-arm64',
        'linux-arm64-musl',
        'linux-armv7',
        'linux-x64',
        'linux-x64-musl',
        'macos-arm64',
        'macos-x64',
        'windows-x64',
    ];

    public function detect(string $rawVersion, string $configuredPlatform = 'auto'): string
    {
        if ('auto' !== $configuredPlatform) {
            if (!in_array($configuredPlatform, self::ALLOWED_PLATFORMS, true)) {
                throw new RuntimeException(sprintf('Unsupported platform "%s".', $configuredPlatform));
            }

            return $configuredPlatform;
        }

        $os = strtolower(PHP_OS);
        $machine = strtolower(php_uname('m'));

        $system = match (true) {
            str_contains($os, 'win') => 'windows',
            str_contains($os, 'darwin') => 'macos',
            str_contains($os, 'linux') => 'linux',
            default => null,
        };

        $arch = match ($machine) {
            'arm64', 'aarch64' => 'arm64',
            'armv7', 'armv7l' => 'armv7',
            'x86_64', 'amd64' => 'x64',
            default => null,
        };

        if (null === $system || null === $arch) {
            throw new RuntimeException(sprintf('Unable to detect platform from OS=%s arch=%s.', $os, $machine));
        }

        if ('windows' === $system && 'x64' !== $arch) {
            throw new RuntimeException('Only windows-x64 is supported by Tailwind standalone binary.');
        }

        if ('linux' === $system && version_compare($rawVersion, '4.0.0', '>=') && in_array($arch, ['x64', 'arm64'], true)) {
            return sprintf('%s-%s%s', $system, $arch, $this->isMusl() ? '-musl' : '');
        }

        return sprintf('%s-%s', $system, $arch);
    }

    public function getBinaryName(string $rawVersion, string $configuredPlatform = 'auto'): string
    {
        $platform = $this->detect($rawVersion, $configuredPlatform);

        return sprintf('tailwindcss-%s%s', $platform, str_starts_with($platform, 'windows-') ? '.exe' : '');
    }

    private function isMusl(): bool
    {
        if (!function_exists('phpinfo')) {
            return false;
        }

        ob_start();
        phpinfo(INFO_GENERAL);
        $output = ob_get_clean() ?: '';

        return 1 === preg_match('/--build=.*?-linux-musl/', $output);
    }
}
