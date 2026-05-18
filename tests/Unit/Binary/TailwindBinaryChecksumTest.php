<?php

declare(strict_types=1);

namespace Aligny\TailwindBuilder\Tests\Unit\Binary;

use Aligny\TailwindBuilder\Binary\TailwindBinary;
use PHPUnit\Framework\TestCase;

final class TailwindBinaryChecksumTest extends TestCase
{
    public function testExtractChecksumFromReleasePayloadReturnsDigestForMatchingAsset(): void
    {
        $payload = json_encode([
            'assets' => [
                [
                    'name' => 'tailwindcss-windows-x64.exe',
                    'digest' => 'sha256:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
                ],
            ],
        ]);

        self::assertIsString($payload);

        $checksum = TailwindBinary::extractChecksumFromReleasePayload($payload, 'tailwindcss-windows-x64.exe');

        self::assertSame('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $checksum);
    }

    public function testExtractChecksumFromReleasePayloadReturnsNullWhenMissing(): void
    {
        $payload = json_encode([
            'assets' => [
                [
                    'name' => 'tailwindcss-linux-x64',
                    'digest' => 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                ],
            ],
        ]);

        self::assertIsString($payload);

        $checksum = TailwindBinary::extractChecksumFromReleasePayload($payload, 'tailwindcss-windows-x64.exe');

        self::assertNull($checksum);
    }
}
