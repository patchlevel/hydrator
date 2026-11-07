<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use Patchlevel\Hydrator\Extension\Cryptography\Cryptographer;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyExtension;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMetadataEnricher;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMiddleware;
use Patchlevel\Hydrator\StackHydratorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CryptographyExtension::class)]
final class CryptographyExtensionTest extends TestCase
{
    public function testConfigure(): void
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
}
