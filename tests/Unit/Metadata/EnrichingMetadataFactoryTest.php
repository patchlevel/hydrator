<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\EnrichingMetadataFactory;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use Patchlevel\Hydrator\Metadata\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

#[CoversClass(EnrichingMetadataFactory::class)]
final class EnrichingMetadataFactoryTest extends TestCase
{
    public function testMetadata(): void
    {
        $classMetadata = new ClassMetadata(new ReflectionClass(stdClass::class));

        $innerFactory = $this->createMock(MetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(stdClass::class)
            ->willReturn($classMetadata);

        $enricher1 = $this->createMock(MetadataEnricher::class);
        $enricher1->expects(self::once())
            ->method('enrich')
            ->with($classMetadata);

        $enricher2 = $this->createMock(MetadataEnricher::class);
        $enricher2->expects(self::once())
            ->method('enrich')
            ->with($classMetadata);

        $factory = new EnrichingMetadataFactory(
            $innerFactory,
            [$enricher1, $enricher2],
        );

        $result = $factory->metadata(stdClass::class);

        self::assertSame($classMetadata, $result);
    }
}
