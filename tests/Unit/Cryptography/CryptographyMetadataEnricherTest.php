<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Cryptography;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\SensitiveData;
use Patchlevel\Hydrator\Cryptography\CryptographyMetadataEnricher;
use Patchlevel\Hydrator\Cryptography\DuplicateSubjectIdIdentifier;
use Patchlevel\Hydrator\Cryptography\SensitiveDataInfo;
use Patchlevel\Hydrator\Cryptography\SubjectIdAndSensitiveDataConflict;
use Patchlevel\Hydrator\Cryptography\SubjectIdFieldMapping;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentWithSensitiveDataDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentWithSensitiveDataWithIdentifierDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CryptographyMetadataEnricher::class)]
final class CryptographyMetadataEnricherTest extends TestCase
{
    public function testSensitiveData(): void
    {
        $event = new class ('id', 'name') {
            public function __construct(
                #[DataSubjectId]
                #[NormalizedName('_id')]
                public string $id,
                #[SensitiveData('fallback')]
                #[NormalizedName('_name')]
                public string $name,
            ) {
            }
        };

        $metadata = $this->metadata($event::class);

        self::assertArrayHasKey(SubjectIdFieldMapping::class, $metadata->extras);
        $subjectIdFieldMapping = $metadata->extras[SubjectIdFieldMapping::class];
        self::assertInstanceOf(SubjectIdFieldMapping::class, $subjectIdFieldMapping);
        self::assertEquals(['default' => '_id'], $subjectIdFieldMapping->nameToField);

        $property = $metadata->propertyForField('_name');

        self::assertArrayHasKey(SensitiveDataInfo::class, $property->extras);
        $sensitiveDataInfo = $property->extras[SensitiveDataInfo::class];
        self::assertInstanceOf(SensitiveDataInfo::class, $sensitiveDataInfo);

        self::assertSame('default', $sensitiveDataInfo->subjectIdName);
        self::assertSame('fallback', $sensitiveDataInfo->fallback);
    }

    public function testSubjectIdAndSensitiveDataConflict(): void
    {
        $event = new class ('name') {
            public function __construct(
                #[DataSubjectId]
                #[SensitiveData]
                public string $name,
            ) {
            }
        };

        $this->expectException(SubjectIdAndSensitiveDataConflict::class);

        $this->metadata($event::class);
    }

    public function testMultipleDataSubjectIdWithSameIdentifier(): void
    {
        $event = new class ('id', 'name') {
            public function __construct(
                #[DataSubjectId]
                public string $id,
                #[DataSubjectId]
                public string $name,
            ) {
            }
        };

        $this->expectException(DuplicateSubjectIdIdentifier::class);

        $this->metadata($event::class);
    }

    public function testSensitiveDataWithMultipleDataSubjectIdWithDifferentNames(): void
    {
        $event = new class ('fooId', 'fooName', 'barId', 'barName') {
            public function __construct(
                #[DataSubjectId(name: 'foo')]
                #[NormalizedName('_fooId')]
                public string $fooId,
                #[SensitiveData('fallback', subjectIdName: 'foo')]
                #[NormalizedName('_fooName')]
                public string $fooName,
                #[DataSubjectId(name: 'bar')]
                #[NormalizedName('_barId')]
                public string $barId,
                #[SensitiveData('fallback', subjectIdName: 'bar')]
                #[NormalizedName('_barName')]
                public string $barName,
            ) {
            }
        };

        $metadata = $this->metadata($event::class);

        self::assertArrayHasKey(SubjectIdFieldMapping::class, $metadata->extras);
        $subjectIdFieldMapping = $metadata->extras[SubjectIdFieldMapping::class];
        self::assertInstanceOf(SubjectIdFieldMapping::class, $subjectIdFieldMapping);
        self::assertEquals(['foo' => '_fooId', 'bar' => '_barId'], $subjectIdFieldMapping->nameToField);

        $property = $metadata->propertyForField('_fooName');

        self::assertArrayHasKey(SensitiveDataInfo::class, $property->extras);
        $sensitiveDataInfo = $property->extras[SensitiveDataInfo::class];
        self::assertInstanceOf(SensitiveDataInfo::class, $sensitiveDataInfo);

        self::assertSame('foo', $sensitiveDataInfo->subjectIdName);
        self::assertSame('fallback', $sensitiveDataInfo->fallback);

        $property = $metadata->propertyForField('_barName');

        self::assertArrayHasKey(SensitiveDataInfo::class, $property->extras);
        $sensitiveDataInfo = $property->extras[SensitiveDataInfo::class];
        self::assertInstanceOf(SensitiveDataInfo::class, $sensitiveDataInfo);

        self::assertSame('bar', $sensitiveDataInfo->subjectIdName);
        self::assertSame('fallback', $sensitiveDataInfo->fallback);
    }

    public function testDuplicateSubjectIdIdentifiers(): void
    {
        $event = new class ('fooId', 'fooName', 'barId', 'barName') {
            public function __construct(
                #[DataSubjectId(name: 'foo')]
                #[NormalizedName('_fooId')]
                public string $fooId,
                #[SensitiveData('fallback', subjectIdName: 'foo')]
                #[NormalizedName('_fooName')]
                public string $fooName,
                #[DataSubjectId(name: 'foo')]
                #[NormalizedName('_barId')]
                public string $barId,
                #[SensitiveData('fallback', subjectIdName: 'foo')]
                #[NormalizedName('_barName')]
                public string $barName,
            ) {
            }
        };

        $this->expectException(DuplicateSubjectIdIdentifier::class);
        $this->expectExceptionMessageMatches('/Duplicate subject id identifier found\. Used foo for .*::fooId and .*::barId\./');

        $this->metadata($event::class);
    }

    public function testExtendsWithSensitiveData(): void
    {
        $metadata = $this->metadata(ParentWithSensitiveDataDto::class);

        self::assertCount(2, $metadata->properties);

        self::assertArrayHasKey(SubjectIdFieldMapping::class, $metadata->extras);
        $subjectIdFieldMapping = $metadata->extras[SubjectIdFieldMapping::class];
        self::assertInstanceOf(SubjectIdFieldMapping::class, $subjectIdFieldMapping);
        self::assertEquals(['default' => 'profileId'], $subjectIdFieldMapping->nameToField);

        $property = $metadata->propertyForField('email');

        self::assertArrayHasKey(SensitiveDataInfo::class, $property->extras);
        $sensitiveDataInfo = $property->extras[SensitiveDataInfo::class];
        self::assertInstanceOf(SensitiveDataInfo::class, $sensitiveDataInfo);

        self::assertSame('default', $sensitiveDataInfo->subjectIdName);
        self::assertSame(null, $sensitiveDataInfo->fallback);
    }

    public function testExtendsWithSensitiveDataWithName(): void
    {
        $metadata = $this->metadata(ParentWithSensitiveDataWithIdentifierDto::class);

        self::assertCount(2, $metadata->properties);

        self::assertArrayHasKey(SubjectIdFieldMapping::class, $metadata->extras);
        $subjectIdFieldMapping = $metadata->extras[SubjectIdFieldMapping::class];
        self::assertInstanceOf(SubjectIdFieldMapping::class, $subjectIdFieldMapping);
        self::assertEquals(['profile' => 'profileId'], $subjectIdFieldMapping->nameToField);

        $property = $metadata->propertyForField('email');

        self::assertArrayHasKey(SensitiveDataInfo::class, $property->extras);
        $sensitiveDataInfo = $property->extras[SensitiveDataInfo::class];
        self::assertInstanceOf(SensitiveDataInfo::class, $sensitiveDataInfo);

        self::assertSame('profile', $sensitiveDataInfo->subjectIdName);
        self::assertSame(null, $sensitiveDataInfo->fallback);
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = (new AttributeMetadataFactory())->metadata($class);
        (new CryptographyMetadataEnricher())->enrich($metadata);

        return $metadata;
    }
}
