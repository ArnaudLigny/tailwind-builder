<?php

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Tests\Integration\Command;

use Aligny\TailwindBuilder\Command\TailwindBuildCommand;
use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class TailwindBuildCommandIntegrationTest extends TestCase
{
    private string $originalCwd;
    private string $projectRoot;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->originalCwd = getcwd() ?: __DIR__;
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tailwind-builder-' . bin2hex(random_bytes(6));

        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpDir . DIRECTORY_SEPARATOR . 'assets', 0777, true);

        file_put_contents(
            $this->tmpDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'tailwind.css',
            "@import 'tailwindcss';\nbody { color: red; }\n"
        );

        chdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->deleteDirectory($this->tmpDir);
    }

    public function testBuildRunsWithMockedBinaryAndCreatesOutput(): void
    {
        $binaryPath = $this->createMockTailwindBinary();

        $command = new TailwindBuildCommand();
        $tester = new CommandTester($command);
        $statusCode = $tester->execute([
            'input' => 'assets/tailwind.css',
            '--bin-path' => $binaryPath,
            '--output' => 'assets/styles.css',
            '--minify' => true,
        ]);

        self::assertSame(0, $statusCode);

        $outputPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'styles.css';
        self::assertFileExists($outputPath);

        $compiledCss = file_get_contents($outputPath);
        self::assertIsString($compiledCss);
        self::assertStringContainsString('mock tailwind output', $compiledCss);
    }

    public function testBuildFailsWhenUserProvidedChecksumDoesNotMatchCustomBinary(): void
    {
        $binaryPath = $this->createMockTailwindBinary();

        $command = new TailwindBuildCommand();
        $tester = new CommandTester($command);
        $statusCode = $tester->execute([
            'input' => 'assets/tailwind.css',
            '--bin-path' => $binaryPath,
            '--checksum' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);

        self::assertSame(1, $statusCode);
        self::assertStringContainsString('Checksum mismatch', $tester->getDisplay());
    }

    public function testNativeVersionDisplaysPackageVersion(): void
    {
        $process = new Process([
            PHP_BINARY,
            $this->projectRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tailwind-builder',
            '--version',
        ], $this->tmpDir);
        $process->run();

        self::assertSame(0, $process->getExitCode());
        $output = preg_replace('/\x1b\[[0-9;]*m/', '', $process->getOutput());
        self::assertStringContainsString(
            'Tailwind Builder ' . InstalledVersions::getRootPackage()['pretty_version'],
            trim($output)
        );
    }

    public function testCliTailwindVersionOptionIsPassedToTheDefaultCommand(): void
    {
        $binaryPath = $this->createMockTailwindBinary();
        $process = new Process([
            PHP_BINARY,
            $this->projectRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tailwind-builder',
            'assets/tailwind.css',
            '--bin-path=' . $binaryPath,
            '--output=assets/styles.css',
            '--tailwind-version=v4.2.0',
            '--minify',
        ], $this->tmpDir);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
        self::assertFileExists($this->tmpDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'styles.css');
    }

    public function testLegacyTailwindBuildAliasStillWorks(): void
    {
        $process = new Process([
            PHP_BINARY,
            $this->projectRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'tailwind-build',
            '--version',
        ], $this->tmpDir);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
        $output = preg_replace('/\x1b\[[0-9;]*m/', '', $process->getOutput());
        self::assertStringContainsString('Tailwind Builder', trim((string) $output));
    }

    private function createMockTailwindBinary(): string
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $binaryPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'mock-tailwind.cmd';
            $script = <<<'BAT'
@echo off
setlocal enableextensions enabledelayedexpansion
set input=
set output=

:loop
if "%~1"=="" goto done
if "%~1"=="-i" (
  set input=%~2
  shift
  shift
  goto loop
)
if "%~1"=="-o" (
  set output=%~2
  shift
  shift
  goto loop
)
if "%~1"=="-c" (
  shift
  shift
  goto loop
)
if "%~1"=="--watch" (
  shift
  goto loop
)
if "%~1"=="--minify" (
  shift
  goto loop
)
shift
goto loop

:done
if "%output%"=="" exit /b 3
> "%output%" echo /* mock tailwind output */
if not "%input%"=="" type "%input%" >> "%output%"
exit /b 0
BAT;
            file_put_contents($binaryPath, $script);

            return $binaryPath;
        }

        $binaryPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'mock-tailwind.sh';
        $script = <<<'SH'
#!/usr/bin/env sh
input=""
output=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    -i)
      input="$2"
      shift 2
      ;;
    -o)
      output="$2"
      shift 2
      ;;
    -c)
      shift 2
      ;;
    --watch|--minify)
      shift
      ;;
    *)
      shift
      ;;
  esac
done

if [ -z "$output" ]; then
  exit 3
fi

echo "/* mock tailwind output */" > "$output"
if [ -n "$input" ] && [ -f "$input" ]; then
  cat "$input" >> "$output"
fi
SH;
        file_put_contents($binaryPath, $script);
        chmod($binaryPath, 0755);

        return $binaryPath;
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
                continue;
            }

            @unlink($itemPath);
        }

        @rmdir($path);
    }
}
