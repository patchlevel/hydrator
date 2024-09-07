<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Attribute\PostHydrate;
use Patchlevel\Hydrator\Attribute\PreExtract;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\DuplicatedFieldNameInMetadata;
use Patchlevel\Hydrator\Metadata\MissingDataSubjectId;
use Patchlevel\Hydrator\Metadata\MultipleDataSubjectId;
use Patchlevel\Hydrator\Metadata\PropertyMetadataNotFound;
use Patchlevel\Hydrator\Metadata\SubjectIdAndPersonalDataConflict;
use Patchlevel\Hydrator\Normalizer\EnumNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\BrokenParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DuplicateFieldNameDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IdNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IgnoreDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\IgnoreParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\MissingSubjectIdDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentWithPersonalDataDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
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

    public function testEventWithFieldName(): void
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

    public function testEventWithNormalizer(): void
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

    public function testEventWithTypeAwareNormalizer(): void
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

    public function testPersonalData(): void
    {
        $event = new class ('id', 'name') {
            public function __construct(
                #[DataSubjectId]
                #[NormalizedName('_id')]
                public string $id,
                #[PersonalData('fallback')]
                #[NormalizedName('_name')]
                public string $name,
            ) {
            }
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('_id', $metadata->dataSubjectIdField());
        self::assertCount(2, $metadata->properties());

        self::assertSame(false, $metadata->propertyForField('_id')->isPersonalData());
        self::assertSame(null, $metadata->propertyForField('_id')->personalDataFallback());

        self::assertSame(true, $metadata->propertyForField('_name')->isPersonalData());
        self::assertSame('fallback', $metadata->propertyForField('_name')->personalDataFallback());
    }

    public function testMissingDataSubjectId(): void
    {
        $this->expectException(MissingDataSubjectId::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata(MissingSubjectIdDto::class);
    }

    public function testSubjectIdAndPersonalDataConflict(): void
    {
        $event = new class ('name') {
            public function __construct(
                #[DataSubjectId]
                #[PersonalData]
                public string $name,
            ) {
            }
        };

        $this->expectException(SubjectIdAndPersonalDataConflict::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testMultipleDataSubjectId(): void
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

        $this->expectException(MultipleDataSubjectId::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testExtendsWithPersonalData(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ParentWithPersonalDataDto::class);

        self::assertSame('profileId', $metadata->dataSubjectIdField());
        self::assertCount(2, $metadata->properties());

        $idPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $idPropertyMetadata->propertyName());
        self::assertSame('profileId', $idPropertyMetadata->fieldName());
        self::assertFalse($idPropertyMetadata->isPersonalData());
        self::assertInstanceOf(IdNormalizer::class, $idPropertyMetadata->normalizer());

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName());
        self::assertSame('email', $emailPropertyMetadata->fieldName());
        self::assertTrue($emailPropertyMetadata->isPersonalData());
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
}
