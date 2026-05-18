<?php

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Tests\Unit\Config;

use Aligny\TailwindBuilder\Config\BuildOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class BuildOptionsTest extends TestCase
{
    public function testDerivesDefaultOutputFromInputBasename(): void
    {
        $definition = new InputDefinition([
            new InputArgument('input', InputArgument::OPTIONAL),
            new InputOption('output', 'o', InputOption::VALUE_REQUIRED),
            new InputOption('watch', 'w', InputOption::VALUE_NONE),
            new InputOption('minify', 'm', InputOption::VALUE_NONE),
            new InputOption('config', 'c', InputOption::VALUE_REQUIRED),
            new InputOption('tailwind-version', null, InputOption::VALUE_REQUIRED),
            new InputOption('platform', null, InputOption::VALUE_REQUIRED),
            new InputOption('bin-path', null, InputOption::VALUE_REQUIRED),
            new InputOption('checksum', null, InputOption::VALUE_REQUIRED),
            new InputOption('insecure-skip-checksum-verification', null, InputOption::VALUE_NONE),
        ]);

        $input = new ArrayInput(['input' => 'assets/tailwind.css'], $definition);
        $options = BuildOptions::fromInput($input);

        self::assertSame('assets/styles.css', $options->output);
        self::assertSame('v4.3.0', $options->tailwindVersion);
        self::assertSame('auto', $options->platform);
        self::assertNull($options->checksum);
        self::assertTrue($options->verifyChecksum);
    }

    public function testKeepsExplicitOutputOption(): void
    {
        $definition = new InputDefinition([
            new InputArgument('input', InputArgument::OPTIONAL),
            new InputOption('output', 'o', InputOption::VALUE_REQUIRED),
            new InputOption('watch', 'w', InputOption::VALUE_NONE),
            new InputOption('minify', 'm', InputOption::VALUE_NONE),
            new InputOption('config', 'c', InputOption::VALUE_REQUIRED),
            new InputOption('tailwind-version', null, InputOption::VALUE_REQUIRED),
            new InputOption('platform', null, InputOption::VALUE_REQUIRED),
            new InputOption('bin-path', null, InputOption::VALUE_REQUIRED),
            new InputOption('checksum', null, InputOption::VALUE_REQUIRED),
            new InputOption('insecure-skip-checksum-verification', null, InputOption::VALUE_NONE),
        ]);

        $input = new ArrayInput([
            'input' => 'src/app.css',
            '--output' => 'public/app.css',
        ], $definition);

        $options = BuildOptions::fromInput($input);

        self::assertSame('public/app.css', $options->output);
    }
}
