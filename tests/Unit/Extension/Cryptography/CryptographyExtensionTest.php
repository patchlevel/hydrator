<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMetadataEnricher;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\Extension\Cryptography\LegacyCryptographyDecryptMiddleware;
use Patchlevel\Hydrator\Extension\Cryptography\LegacyCryptographyMetadataEnricher;
use Patchlevel\Hydrator\StackHydratorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CryptographyExtension::class)]
final class CryptographyExtensionTest extends TestCase
{
    public function testConfigureWithoutLegacyOptions(): void
    {
        $builder = new StackHydratorBuilder();
        $cryptographer = $this->createMock(Cryptographer::class);

        $extension = new CryptographyExtension($cryptographer);
        $extension->configure($builder);

        $middlewares = $builder->middlewares();
        self::assertCount(1, $middlewares);
        self::assertInstanceOf(CryptographyMiddleware::class, $middlewares[0]);

        $metadataEnrichers = $builder->metadataEnrichers();
        self::assertCount(1, $metadataEnrichers);
        self::assertInstanceOf(CryptographyMetadataEnricher::class, $metadataEnrichers[0]);
    }

    public function testConfigureWithLegacyMetadataMapping(): void
    {
        $builder = new StackHydratorBuilder();
        $cryptographer = $this->createMock(Cryptographer::class);

        $extension = new CryptographyExtension($cryptographer, legacyMetadataMapping: true);
        $extension->configure($builder);

        $metadataEnrichers = $builder->metadataEnrichers();
        self::assertCount(2, $metadataEnrichers);
        self::assertInstanceOf(CryptographyMetadataEnricher::class, $metadataEnrichers[0]);
        self::assertInstanceOf(LegacyCryptographyMetadataEnricher::class, $metadataEnrichers[1]);
    }

    public function testConfigureWithLegacyCryptographerAndMetadataMapping(): void
    {
        $builder = new StackHydratorBuilder();
        $cryptographer = $this->createMock(Cryptographer::class);
        $legacyCryptographer = $this->createMock(PayloadCryptographer::class);

        $extension = new CryptographyExtension($cryptographer, $legacyCryptographer, true);
        $extension->configure($builder);

        $middlewares = $builder->middlewares();
        self::assertCount(2, $middlewares);
        self::assertInstanceOf(LegacyCryptographyDecryptMiddleware::class, $middlewares[0]);
        self::assertInstanceOf(CryptographyMiddleware::class, $middlewares[1]);

        $metadataEnrichers = $builder->metadataEnrichers();
        self::assertCount(2, $metadataEnrichers);
        self::assertInstanceOf(CryptographyMetadataEnricher::class, $metadataEnrichers[0]);
        self::assertInstanceOf(LegacyCryptographyMetadataEnricher::class, $metadataEnrichers[1]);
    }
}
