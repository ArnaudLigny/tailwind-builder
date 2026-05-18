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

namespace Aligny\TailwindBuilder\Binary;

use Aligny\TailwindBuilder\Platform\PlatformDetector;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

final class TailwindBinary
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly PlatformDetector $platformDetector,
        private readonly OutputInterface $output,
    ) {
    }

    public function resolvePath(
        ?string $customBinaryPath,
        string $version,
        string $configuredPlatform = 'auto',
        ?string $expectedChecksum = null,
        bool $verifyChecksum = true,
    ): string
    {
        if (null !== $customBinaryPath && '' !== trim($customBinaryPath)) {
            if (!is_file($customBinaryPath)) {
                throw new RuntimeException(sprintf('Custom binary path not found: %s', $customBinaryPath));
            }

            if ($verifyChecksum && null !== $expectedChecksum) {
                $this->assertChecksum($customBinaryPath, $expectedChecksum, 'custom binary');
            }

            return $customBinaryPath;
        }

        $normalizedVersion = 'v' . ltrim($version, "vV \t\n\r\0\x0B");
        $rawVersion = ltrim($normalizedVersion, 'v');
        $binaryName = $this->platformDetector->getBinaryName($rawVersion, $configuredPlatform);
        $targetDirectory = $this->cacheDir . DIRECTORY_SEPARATOR . $normalizedVersion;
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $binaryName;

        if (is_file($targetPath)) {
            $this->writeVerbose(sprintf('<info>[tailwind]</info> cache hit: %s', $targetPath));

            if ($verifyChecksum) {
                $this->verifyBinaryChecksum($targetPath, $normalizedVersion, $binaryName, $expectedChecksum);
            }

            return $targetPath;
        }

        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf('Unable to create cache directory: %s', $targetDirectory));
        }

        $lockFilePath = $this->cacheDir . DIRECTORY_SEPARATOR . '.tailwind-download.lock';
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0777, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException(sprintf('Unable to create cache root directory: %s', $this->cacheDir));
        }

        $lockHandle = fopen($lockFilePath, 'c+');
        if (false === $lockHandle) {
            throw new RuntimeException(sprintf('Unable to open lock file: %s', $lockFilePath));
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire download lock.');
            }

            if (is_file($targetPath)) {
                if ($verifyChecksum) {
                    $this->verifyBinaryChecksum($targetPath, $normalizedVersion, $binaryName, $expectedChecksum);
                }

                return $targetPath;
            }

            $this->download($normalizedVersion, $binaryName, $targetPath);

            if ($verifyChecksum) {
                $this->verifyBinaryChecksum($targetPath, $normalizedVersion, $binaryName, $expectedChecksum);
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        if (!is_file($targetPath)) {
            throw new RuntimeException(sprintf('Binary download failed: %s', $targetPath));
        }

        return $targetPath;
    }

    public static function extractChecksumFromReleasePayload(string $payload, string $binaryName): ?string
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || !isset($decoded['assets']) || !is_array($decoded['assets'])) {
            return null;
        }

        foreach ($decoded['assets'] as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $assetName = $asset['name'] ?? null;
            $digest = $asset['digest'] ?? null;

            if (!is_string($assetName) || $assetName !== $binaryName || !is_string($digest)) {
                continue;
            }

            if (preg_match('/^sha256:([a-fA-F0-9]{64})$/', trim($digest), $matches) === 1) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    private function download(string $version, string $binaryName, string $targetPath): void
    {
        $url = sprintf('https://github.com/tailwindlabs/tailwindcss/releases/download/%s/%s', $version, $binaryName);
        $this->output->writeln(sprintf('<info>[tailwind]</info> downloading: %s', $url));

        $maxAttempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $this->downloadToFile($url, $targetPath);

                if (!str_ends_with($targetPath, '.exe')) {
                    @chmod($targetPath, 0755);
                }

                return;
            } catch (\Throwable $exception) {
                $lastError = $exception;
                $this->writeVerbose(sprintf('<comment>[tailwind]</comment> download attempt %d/%d failed: %s', $attempt, $maxAttempts, $exception->getMessage()));
                if ($attempt < $maxAttempts) {
                    usleep(300000 * $attempt);
                }
            }
        }

        throw new RuntimeException(
            sprintf('Unable to download Tailwind binary after %d attempts. %s', $maxAttempts, $lastError ? $lastError->getMessage() : ''),
            previous: $lastError
        );
    }

    private function downloadToFile(string $url, string $targetPath): void
    {
        $targetDirectory = dirname($targetPath);
        $temporaryPath = tempnam($targetDirectory, 'tailwind-');
        if (false === $temporaryPath) {
            throw new RuntimeException(sprintf('Unable to create temporary file in %s.', $targetDirectory));
        }

        try {
            $source = $this->openDownloadStream($url);

            try {
                $destination = fopen($temporaryPath, 'wb');
                if (false === $destination) {
                    throw new RuntimeException(sprintf('Unable to open temporary file for writing: %s', $temporaryPath));
                }

                try {
                    $copiedBytes = stream_copy_to_stream($source, $destination);
                    if (false === $copiedBytes) {
                        throw new RuntimeException('Unable to copy downloaded binary to temporary file.');
                    }
                } finally {
                    fclose($destination);
                }
            } finally {
                fclose($source);
            }

            if (!@rename($temporaryPath, $targetPath)) {
                throw new RuntimeException(sprintf('Unable to move downloaded binary to final path: %s', $targetPath));
            }
        } catch (\Throwable $exception) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $exception;
        }
    }

    /**
     * @return resource
     */
    private function openDownloadStream(string $url)
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 120,
                'header' => "User-Agent: composer-tailwind\r\n",
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if (false === $stream) {
            throw new RuntimeException('Unable to open remote stream for binary download.');
        }

        return $stream;
    }

    private function verifyBinaryChecksum(string $binaryPath, string $version, string $binaryName, ?string $expectedChecksum): void
    {
        $normalizedExpected = null;
        if (null !== $expectedChecksum) {
            $normalizedExpected = $this->normalizeChecksum($expectedChecksum);
            if (null === $normalizedExpected) {
                throw new RuntimeException('Invalid checksum format. Expected 64 hexadecimal chars, optionally prefixed with "sha256:".');
            }
        }

        $checksum = $normalizedExpected ?? $this->fetchReleaseChecksum($version, $binaryName);
        if (null === $checksum) {
            throw new RuntimeException(
                sprintf(
                    'Unable to verify binary checksum for %s (%s). Use --checksum=<sha256> or --insecure-skip-checksum-verification.',
                    $binaryName,
                    $version
                )
            );
        }

        $this->assertChecksum($binaryPath, $checksum, sprintf('Tailwind binary %s', $binaryName));
    }

    private function assertChecksum(string $binaryPath, string $expectedChecksum, string $label): void
    {
        $actualChecksum = hash_file('sha256', $binaryPath);
        if (!is_string($actualChecksum) || !hash_equals(strtolower($expectedChecksum), strtolower($actualChecksum))) {
            throw new RuntimeException(
                sprintf(
                    'Checksum mismatch for %s. Expected %s but got %s.',
                    $label,
                    strtolower($expectedChecksum),
                    is_string($actualChecksum) ? strtolower($actualChecksum) : 'unknown'
                )
            );
        }

        $this->writeVerbose(sprintf('<info>[tailwind]</info> checksum verified for %s', $label));
    }

    private function fetchReleaseChecksum(string $version, string $binaryName): ?string
    {
        $releaseApiUrl = sprintf('https://api.github.com/repos/tailwindlabs/tailwindcss/releases/tags/%s', rawurlencode($version));

        try {
            $payload = $this->downloadContent($releaseApiUrl, ['Accept: application/vnd.github+json']);
        } catch (\Throwable $exception) {
            $this->writeVerbose(sprintf('<comment>[tailwind]</comment> checksum metadata fetch failed: %s', $exception->getMessage()));

            return null;
        }

        return self::extractChecksumFromReleasePayload($payload, $binaryName);
    }

    private function normalizeChecksum(string $checksum): ?string
    {
        $value = strtolower(trim($checksum));
        if (str_starts_with($value, 'sha256:')) {
            $value = substr($value, 7);
        }

        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function writeVerbose(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($message);
        }
    }

    /**
     * @param array<int, string> $headers
     */
    private function downloadContent(string $url, array $headers = []): string
    {
        $requestHeaders = array_merge(['User-Agent: composer-tailwind'], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if (false === $ch) {
                throw new RuntimeException('Unable to initialize cURL.');
            }

            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => $requestHeaders,
                //CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
            ]);

            $body = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if (false === $body || '' !== $error) {
                throw new RuntimeException('cURL download failed: ' . $error);
            }

            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 120,
                'header' => implode("\r\n", $requestHeaders) . "\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if (false === $body) {
            throw new RuntimeException('Unable to download binary with file_get_contents().');
        }

        return $body;
    }
}
