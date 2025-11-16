<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\Lazy;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;
use Patchlevel\Hydrator\Attribute\SensitiveData;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\DuplicatedFieldNameInMetadata;
use Patchlevel\Hydrator\Metadata\DuplicateSubjectIdIdentifier;
use Patchlevel\Hydrator\Metadata\MissingDataSubjectId;
use Patchlevel\Hydrator\Metadata\PropertyMetadataNotFound;
use Patchlevel\Hydrator\Metadata\SubjectIdAndSensitiveDataConflict;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\BrokenParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DistributionCreated;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DuplicateFieldNameDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IdNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IgnoreDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IgnoreParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\MissingSubjectIdDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentWithSensitiveDataDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentWithSensitiveDataWithIdentifierDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithGeneric;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Wrapper;
use PHPUnit\Framework\TestCase;

final class AttributeMetadataFactoryTest extends TestCase
{
    public function testEmptyObject(): void
    {
        $object = new class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertCount(0, $metadata->properties());
        self::assertCount(0, $metadata->preExtractCallbacks());
        self::assertCount(0, $metadata->postHydrateCallbacks());
    }

    public function testSameMetadata(): void
    {
        $object = new class {
        };

        $metadataFactory = new AttributeMetadataFactory();

        self::assertSame($metadataFactory->metadata($object::class), $metadataFactory->metadata($object::class));
    }

    public function testNotFoundProperty(): void
    {
        $this->expectException(PropertyMetadataNotFound::class);

        $object = new class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $metadata->propertyForField('email');
    }

    public function testWithProperties(): void
    {
        $object = new class {
            public string|null $name = null;
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('name');

        self::assertSame('name', $propertyMetadata->propertyName());
        self::assertSame('name', $propertyMetadata->fieldName());
        self::assertNull($propertyMetadata->normalizer());
    }

    public function testUnknownClass(): void
    {
        $this->expectException(ClassNotFound::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata('Unknown');
    }

    public function testSkipStaticProperties(): void
    {
        $object = new class {
            public static string $name = 'foo';
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(0, $properties);
    }

    public function testWithConstructorProperties(): void
    {
        $object = new class ('Foo') {
            public function __construct(
                public string $name,
            ) {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('name');

        self::assertSame('name', $propertyMetadata->propertyName());
        self::assertSame('name', $propertyMetadata->fieldName());
        self::assertNull($propertyMetadata->normalizer());
    }

    public function testNormalizedName(): void
    {
        $object = new class ('Foo') {
            public function __construct(
                #[NormalizedName('username')]
                public string $name,
            ) {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('username');

        self::assertSame('name', $propertyMetadata->propertyName());
        self::assertSame('username', $propertyMetadata->fieldName());
        self::assertNull($propertyMetadata->normalizer());
    }

    public function testDefineNormalizer(): void
    {
        $object = new class (Email::fromString('info@patchlevel.de')) {
            public function __construct(
                #[EmailNormalizer]
                public Email $email,
            ) {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $propertyMetadata->propertyName());
        self::assertSame('email', $propertyMetadata->fieldName());
        self::assertInstanceOf(EmailNormalizer::class, $propertyMetadata->normalizer());
    }

    public function testTypeAwareNormalizer(): void
    {
        $object = new class (Status::Draft) {
            public function __construct(
                #[EnumNormalizer]
                public Status $status,
            ) {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('status');

        self::assertSame('status', $propertyMetadata->propertyName());
        self::assertSame('status', $propertyMetadata->fieldName());

        $normalizer = $propertyMetadata->normalizer();

        self::assertInstanceOf(EnumNormalizer::class, $normalizer);
        self::assertSame(Status::class, $normalizer->getEnum());
    }

    public function testInferNormalizer(): void
    {
        $object = new class {
            public function __construct(
                public ProfileId|null $profileId = null,
            ) {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $properties = $metadata->properties();

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('profileId');

        self::assertEquals(new IdNormalizer(ProfileId::class), $propertyMetadata->normalizer());
    }

    public function testInferNormalizerWithGeneric(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileCreatedWithGeneric::class);

        self::assertCount(2, $metadata->properties());

        $propertyMetadata = $metadata->propertyForField('email');
        self::assertEquals(new ObjectNormalizer(Wrapper::class), $propertyMetadata->normalizer());
    }

    public function testInferNormalizerWithTemplate(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(Wrapper::class);

        self::assertCount(3, $metadata->properties());

        $propertyMetadata = $metadata->propertyForField('value');
        self::assertNull($propertyMetadata->normalizer());

        $propertyMetadata = $metadata->propertyForField('object');
        self::assertEquals(new ObjectNormalizer(Wrapper::class), $propertyMetadata->normalizer());

        $propertyMetadata = $metadata->propertyForField('scalar');
        self::assertEquals(new ObjectNormalizer(Wrapper::class), $propertyMetadata->normalizer());
    }

    public function testExtends(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ParentDto::class);

        self::assertCount(2, $metadata->properties());

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName());
        self::assertSame('profileId', $emailPropertyMetadata->fieldName());
        self::assertInstanceOf(IdNormalizer::class, $emailPropertyMetadata->normalizer());

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName());
        self::assertSame('email', $emailPropertyMetadata->fieldName());
        self::assertInstanceOf(EmailNormalizer::class, $emailPropertyMetadata->normalizer());
    }

    public function testExtendsDuplicatedFieldName(): void
    {
        $this->expectException(DuplicatedFieldNameInMetadata::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata(BrokenParentDto::class);
    }

    public function testBug70(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(DistributionCreated::class);

        self::assertCount(1, $metadata->properties());

        $property = $metadata->propertyForField('recordedDate');

        self::assertSame('recordedDate', $property->propertyName());
    }

    public function testSameClassDuplicatedFieldName(): void
    {
        $this->expectException(DuplicatedFieldNameInMetadata::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata(DuplicateFieldNameDto::class);
    }

    public function testExtendsWithIgnore(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(IgnoreParentDto::class);

        self::assertCount(2, $metadata->properties());

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName());
        self::assertSame('profileId', $emailPropertyMetadata->fieldName());
        self::assertInstanceOf(IdNormalizer::class, $emailPropertyMetadata->normalizer());

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName());
        self::assertSame('email', $emailPropertyMetadata->fieldName());
        self::assertInstanceOf(EmailNormalizer::class, $emailPropertyMetadata->normalizer());
    }

    public function testIgnore(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(IgnoreDto::class);

        self::assertCount(1, $metadata->properties());

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName());
        self::assertSame('profileId', $emailPropertyMetadata->fieldName());
        self::assertInstanceOf(IdNormalizer::class, $emailPropertyMetadata->normalizer());
    }

    public function testIgnoreNotFoundProperty(): void
    {
        $this->expectException(PropertyMetadataNotFound::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(IgnoreDto::class);

        $metadata->propertyForField('email');
    }

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

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertCount(2, $metadata->properties());

        self::assertSame(false, $metadata->propertyForField('_id')->isSensitiveData());
        self::assertSame(true, $metadata->propertyForField('_id')->isSubjectId());
        self::assertSame('default', $metadata->propertyForField('_id')->subjectIdName());
        self::assertSame(null, $metadata->propertyForField('_id')->sensitiveDataFallback());
        self::assertSame(null, $metadata->propertyForField('_id')->sensitiveDataSubjectIdName());

        self::assertSame(true, $metadata->propertyForField('_name')->isSensitiveData());
        self::assertSame(false, $metadata->propertyForField('_name')->isSubjectId());
        self::assertSame('fallback', $metadata->propertyForField('_name')->sensitiveDataFallback());
        self::assertSame('default', $metadata->propertyForField('_name')->sensitiveDataSubjectIdName());
    }

    public function testMissingDataSubjectId(): void
    {
        $this->expectException(MissingDataSubjectId::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata(MissingSubjectIdDto::class);
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

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata($event::class);
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

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata($event::class);
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

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertCount(4, $metadata->properties());

        $fooIdProperty = $metadata->propertyForField('_fooId');
        self::assertFalse($fooIdProperty->isSensitiveData());
        self::assertSame(null, $fooIdProperty->sensitiveDataFallback());
        self::assertTrue($fooIdProperty->isSubjectId());
        self::assertSame('foo', $fooIdProperty->subjectIdName());

        $fooNameProperty = $metadata->propertyForField('_fooName');
        self::assertSame(true, $fooNameProperty->isSensitiveData());
        self::assertSame('fallback', $fooNameProperty->sensitiveDataFallback());
        self::assertSame('foo', $fooNameProperty->sensitiveDataSubjectIdName());

        $barIdProperty = $metadata->propertyForField('_barId');
        self::assertFalse($barIdProperty->isSensitiveData());
        self::assertSame(null, $barIdProperty->sensitiveDataFallback());
        self::assertTrue($barIdProperty->isSubjectId());
        self::assertSame('bar', $barIdProperty->subjectIdName());

        $barNameProperty = $metadata->propertyForField('_barName');
        self::assertSame(true, $barNameProperty->isSensitiveData());
        self::assertSame('fallback', $barNameProperty->sensitiveDataFallback());
        self::assertSame('bar', $barNameProperty->sensitiveDataSubjectIdName());
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

        $metadataFactory = new AttributeMetadataFactory();

        $this->expectException(DuplicateSubjectIdIdentifier::class);
        $this->expectExceptionMessageMatches('/Duplicate subject id identifier found\. Used foo for .*::fooId and .*::barId\./');
        $metadataFactory->metadata($event::class);
    }

    public function testExtendsWithSensitiveData(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ParentWithSensitiveDataDto::class);

        self::assertCount(2, $metadata->properties());

        $idPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $idPropertyMetadata->propertyName());
        self::assertSame('profileId', $idPropertyMetadata->fieldName());
        self::assertTrue($idPropertyMetadata->isSubjectId());
        self::assertFalse($idPropertyMetadata->isSensitiveData());
        self::assertInstanceOf(IdNormalizer::class, $idPropertyMetadata->normalizer());

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName());
        self::assertSame('email', $emailPropertyMetadata->fieldName());
        self::assertFalse($emailPropertyMetadata->isSubjectId());
        self::assertTrue($emailPropertyMetadata->isSensitiveData());
        self::assertInstanceOf(EmailNormalizer::class, $emailPropertyMetadata->normalizer());
    }

    public function testExtendsWithSensitiveDataWithName(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ParentWithSensitiveDataWithIdentifierDto::class);

        self::assertCount(2, $metadata->properties());

        $idPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $idPropertyMetadata->propertyName());
        self::assertSame('profileId', $idPropertyMetadata->fieldName());
        self::assertTrue($idPropertyMetadata->isSubjectId());
        self::assertFalse($idPropertyMetadata->isSensitiveData());
        self::assertInstanceOf(IdNormalizer::class, $idPropertyMetadata->normalizer());

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName());
        self::assertSame('email', $emailPropertyMetadata->fieldName());
        self::assertFalse($emailPropertyMetadata->isSubjectId());
        self::assertTrue($emailPropertyMetadata->isSensitiveData());
        self::assertNull($emailPropertyMetadata->sensitiveDataFallback());
        self::assertSame('profile', $emailPropertyMetadata->sensitiveDataSubjectIdName());
        self::assertInstanceOf(EmailNormalizer::class, $emailPropertyMetadata->normalizer());
    }

    public function testHooks(): void
    {
        $object = new class {
            #[PreExtract]
            private function preExtract(): void
            {
            }

            #[PostHydrate]
            private function postHydrate(): void
            {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        $preExtract = $metadata->preExtractCallbacks();

        self::assertCount(1, $preExtract);
        self::assertSame('preExtract', $preExtract[0]->methodName());

        $postHydrate = $metadata->postHydrateCallbacks();

        self::assertCount(1, $postHydrate);
        self::assertSame('postHydrate', $postHydrate[0]->methodName());
    }

    public function testSkipStaticHook(): void
    {
        $object = new class {
            #[PreExtract]
            private static function preExtract(): void
            {
            }

            #[PostHydrate]
            private static function postHydrate(): void
            {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertCount(0, $metadata->preExtractCallbacks());
        self::assertCount(0, $metadata->postHydrateCallbacks());
    }

    public function testNoLazy(): void
    {
        $object = new class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertNull($metadata->lazy());
    }

    public function testLazy(): void
    {
        $object = new #[Lazy]
        class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertTrue($metadata->lazy());
    }
}
