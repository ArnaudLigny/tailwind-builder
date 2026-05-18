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

namespace Aligny\TailwindBuilder\Command;

use Aligny\TailwindBuilder\Binary\TailwindBinary;
use Aligny\TailwindBuilder\Config\BuildOptions;
use Aligny\TailwindBuilder\Platform\PlatformDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Throwable;

final class TailwindBuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('tailwind:build')
            ->setDescription('Build Tailwind CSS using the standalone CLI binary.')
            ->addArgument('input', InputArgument::OPTIONAL, 'Input CSS file path', 'assets/tailwind.css')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output CSS file path')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch for file changes')
            ->addOption('minify', 'm', InputOption::VALUE_NONE, 'Minify output CSS')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Tailwind config path', 'tailwind.config.js')
            ->addOption('tailwind-version', null, InputOption::VALUE_REQUIRED, 'Tailwind version to use', 'v4.3.0')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED, 'Tailwind platform override', 'auto')
            ->addOption('bin-path', null, InputOption::VALUE_REQUIRED, 'Custom Tailwind binary path (skip auto-download)')
            ->addOption('checksum', null, InputOption::VALUE_REQUIRED, 'Expected SHA-256 checksum for the binary')
            ->addOption('insecure-skip-checksum-verification', null, InputOption::VALUE_NONE, 'Skip checksum verification (not recommended)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = BuildOptions::fromInput($input);

        if (!is_file($options->input)) {
            $output->writeln(sprintf('<error>Input CSS file not found:</error> %s', $options->input));

            return self::FAILURE;
        }

        $outputDir = dirname($options->output);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            $output->writeln(sprintf('<error>Unable to create output directory:</error> %s', $outputDir));

            return self::FAILURE;
        }

        try {
            $cacheDir = getcwd() . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR . 'tailwind';
            $binaryResolver = new TailwindBinary($cacheDir, new PlatformDetector(), $output);
            $binaryPath = $binaryResolver->resolvePath(
                $options->binPath,
                $options->tailwindVersion,
                $options->platform,
                $options->checksum,
                $options->verifyChecksum,
            );

            $arguments = $this->buildTailwindArguments($options, $output);
            $process = new Process(array_merge([$binaryPath], $arguments), getcwd());

            if ($options->watch) {
                $process->setTimeout(null);
                $inputStream = new InputStream();
                $process->setInput($inputStream);
            }

            if ($output->isVerbose()) {
                $output->writeln('<info>[tailwind]</info> command: ' . $process->getCommandLine());
            }

            $process->run(static function (string $type, string $buffer) use ($output): void {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Tailwind build failed.</error>');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function buildTailwindArguments(BuildOptions $options, OutputInterface $output): array
    {
        $arguments = ['-i', $options->input, '-o', $options->output];
        $rawVersion = ltrim($options->tailwindVersion, 'v');

        if (version_compare($rawVersion, '4.0.0', '<')) {
            if (null !== $options->config && is_file($options->config)) {
                $arguments = ['-c', $options->config, ...$arguments];
            } elseif (null !== $options->config && $output->isVerbose()) {
                $output->writeln(sprintf('<comment>[tailwind]</comment> config file not found, skipping: %s', $options->config));
            }
        }

        if ($options->watch) {
            $arguments[] = '--watch';
        }

        if ($options->minify) {
            $arguments[] = '--minify';
        }

        return $arguments;
    }
}
