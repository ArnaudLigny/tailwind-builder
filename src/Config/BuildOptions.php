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

namespace Aligny\TailwindBuilder\Config;

use Symfony\Component\Console\Input\InputInterface;

final class BuildOptions
{
    public function __construct(
        public readonly string $input,
        public readonly string $output,
        public readonly bool $watch,
        public readonly bool $minify,
        public readonly ?string $config,
        public readonly string $tailwindVersion,
        public readonly string $platform,
        public readonly ?string $binPath,
        public readonly ?string $checksum,
        public readonly bool $verifyChecksum,
    ) {
    }

    public static function fromInput(InputInterface $input): self
    {
        $inputPath = (string) ($input->getArgument('input') ?? 'assets/tailwind.css');
        $outputPath = $input->getOption('output');

        if (!$input->hasParameterOption(['--output', '-o']) || !is_string($outputPath) || '' === trim($outputPath)) {
            $outputPath = preg_replace('~[^/\\\\]+$~', 'styles.css', $inputPath);

            if (null === $outputPath || '' === $outputPath) {
                $outputPath = 'styles.css';
            }
        }

        $tailwindVersion = (string) ($input->getOption('tailwind-version') ?? 'v4.3.0');
        $tailwindVersion = 'v' . ltrim($tailwindVersion, "vV \t\n\r\0\x0B");

        $config = $input->getOption('config');
        if (!is_string($config) || '' === trim($config)) {
            $config = 'tailwind.config.js';
        }

        $binPath = $input->getOption('bin-path');
        if (!is_string($binPath) || '' === trim($binPath)) {
            $binPath = null;
        }

        $platform = (string) ($input->getOption('platform') ?? 'auto');

        $checksum = $input->getOption('checksum');
        if (!is_string($checksum) || '' === trim($checksum)) {
            $checksum = null;
        }

        return new self(
            input: $inputPath,
            output: $outputPath,
            watch: (bool) $input->getOption('watch'),
            minify: (bool) $input->getOption('minify'),
            config: $config,
            tailwindVersion: $tailwindVersion,
            platform: $platform,
            binPath: $binPath,
            checksum: $checksum,
            verifyChecksum: !(bool) $input->getOption('insecure-skip-checksum-verification'),
        );
    }
}
