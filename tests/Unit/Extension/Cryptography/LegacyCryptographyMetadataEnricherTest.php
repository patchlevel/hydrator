<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography;

use Patchlevel\Hydrator\Attribute\DataSubjectId as LegacyDataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Extension\Cryptography\CryptographyMetadataEnricher;
use Patchlevel\Hydrator\Extension\Cryptography\DuplicateSubjectIdIdentifier;
use Patchlevel\Hydrator\Extension\Cryptography\LegacyCryptographyMetadataEnricher;
use Patchlevel\Hydrator\Extension\Cryptography\PersonalDataAndSensitiveDataOnSameProperty;
use Patchlevel\Hydrator\Extension\Cryptography\SensitiveDataInfo;
use Patchlevel\Hydrator\Extension\Cryptography\SubjectIdAndSensitiveDataConflict;
use Patchlevel\Hydrator\Extension\Cryptography\SubjectIdFieldMapping;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\MetadataEnricher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyCryptographyMetadataEnricher::class)]
final class LegacyCryptographyMetadataEnricherTest extends TestCase
{
    public function testSensitiveData(): void
    {
        $event = new class ('id', 'name') {
            public function __construct(
                #[LegacyDataSubjectId]
                #[NormalizedName('_id')]
                public string $id,
                #[PersonalData('fallback')]
                #[NormalizedName('_name')]
                public string $name,
            ) {
            }
        };

        $metadata = $this->metadata($event::class, new LegacyCryptographyMetadataEnricher());

        self::assertArrayHasKey(SubjectIdFieldMapping::class, $metadata->extras);
        $subjectIdFieldMapping = $metadata->extras[SubjectIdFieldMapping::class];
        self::assertInstanceOf(SubjectIdFieldMapping::class, $subjectIdFieldMapping);
        self::assertSame(['legacy' => '_id'], $subjectIdFieldMapping->nameToField);

        $property = $metadata->propertyForField('_name');
        self::assertArrayHasKey(SensitiveDataInfo::class, $property->extras);
        self::assertEquals(new SensitiveDataInfo('legacy', 'fallback'), $property->extras[SensitiveDataInfo::class]);
    }

    public function testSubjectIdAndSensitiveDataConflict(): void
    {
        $event = new class ('legacy', 'id', 'name') {
            public function __construct(
                #[LegacyDataSubjectId]
                public string $legacyId,
                #[DataSubjectId]
                #[PersonalData]
                public string $id,
                public string $name,
            ) {
            }
        };

        $this->expectException(SubjectIdAndSensitiveDataConflict::class);

        $this->metadata(
            $event::class,
            new CryptographyMetadataEnricher(),
            new LegacyCryptographyMetadataEnricher(),
        );
    }

    public function testMultipleDataSubjectIdWithSameIdentifier(): void
    {
        $event = new class ('legacyId', 'id', 'name') {
            public function __construct(
                #[LegacyDataSubjectId]
                public string $legacyId,
                #[DataSubjectId(name: 'legacy')]
                public string $id,
                public string $name,
            ) {
            }
        };

        $this->expectException(DuplicateSubjectIdIdentifier::class);

        $this->metadata(
            $event::class,
            new CryptographyMetadataEnricher(),
            new LegacyCryptographyMetadataEnricher(),
        );
    }

    public function testPersonalDataAndSensitiveDataOnSameProperty(): void
    {
        $event = new class ('id', 'name') {
            public function __construct(
                #[LegacyDataSubjectId]
                public string $id,
                #[SensitiveData]
                #[PersonalData]
                public string $name,
            ) {
            }
        };

        $this->expectException(PersonalDataAndSensitiveDataOnSameProperty::class);

        $this->metadata(
            $event::class,
            new CryptographyMetadataEnricher(),
            new LegacyCryptographyMetadataEnricher(),
        );
    }

    public function testNoLegacyAttributes(): void
    {
        $event = new class {
        };

        $metadata = $this->metadata($event::class, new LegacyCryptographyMetadataEnricher());

        self::assertArrayNotHasKey(SubjectIdFieldMapping::class, $metadata->extras);
    }

    /** @param class-string $class */
    private function metadata(string $class, MetadataEnricher ...$enricherList): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata($class);

        foreach ($enricherList as $enricher) {
            $enricher->enrich($metadata);
        }

        return $metadata;
    }
}
