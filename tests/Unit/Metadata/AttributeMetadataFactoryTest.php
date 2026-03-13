<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Attribute\Lazy;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\ClassNotFound;
use Patchlevel\Hydrator\Metadata\DuplicatedFieldNameInMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadataNotFound;
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
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreatedWithGeneric;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileId;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Status;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Wrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(AttributeMetadataFactory::class)]
final class AttributeMetadataFactoryTest extends TestCase
{
    public function testEmptyObject(): void
    {
        $object = new class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertCount(0, $metadata->properties);
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

        $properties = $metadata->properties;

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('name');

        self::assertSame('name', $propertyMetadata->propertyName);
        self::assertSame('name', $propertyMetadata->fieldName);
        self::assertEquals(Type::nullable(Type::string()), $propertyMetadata->type);
        self::assertNull($propertyMetadata->normalizer);
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

        $properties = $metadata->properties;

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

        $properties = $metadata->properties;

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('name');

        self::assertSame('name', $propertyMetadata->propertyName);
        self::assertSame('name', $propertyMetadata->fieldName);
        self::assertEquals(Type::string(), $propertyMetadata->type);
        self::assertNull($propertyMetadata->normalizer);
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

        $properties = $metadata->properties;

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('username');

        self::assertSame('name', $propertyMetadata->propertyName);
        self::assertSame('username', $propertyMetadata->fieldName);
        self::assertNull($propertyMetadata->normalizer);
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

        $properties = $metadata->properties;

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $propertyMetadata->propertyName);
        self::assertSame('email', $propertyMetadata->fieldName);
        self::assertEquals(Type::object(Email::class), $propertyMetadata->type);
        self::assertInstanceOf(EmailNormalizer::class, $propertyMetadata->normalizer);
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

        $properties = $metadata->properties;

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('status');

        self::assertSame('status', $propertyMetadata->propertyName);
        self::assertSame('status', $propertyMetadata->fieldName);
        self::assertEquals(Type::enum(Status::class), $propertyMetadata->type);

        $normalizer = $propertyMetadata->normalizer;

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

        $properties = $metadata->properties;

        self::assertCount(1, $properties);

        $propertyMetadata = $metadata->propertyForField('profileId');

        self::assertEquals(new IdNormalizer(ProfileId::class), $propertyMetadata->normalizer);
    }

    public function testInferNormalizerWithGeneric(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileCreatedWithGeneric::class);

        self::assertCount(2, $metadata->properties);

        $propertyMetadata = $metadata->propertyForField('email');
        self::assertEquals(new ObjectNormalizer(Wrapper::class), $propertyMetadata->normalizer);
                self::assertEquals(
                    Type::generic(Type::object(Wrapper::class), Type::object(Email::class)),
                    $propertyMetadata->type,
                );
    }

    public function testInferNormalizerWithTemplate(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(Wrapper::class);

        self::assertCount(3, $metadata->properties);

        $propertyMetadata = $metadata->propertyForField('value');
        self::assertNull($propertyMetadata->normalizer);

        $propertyMetadata = $metadata->propertyForField('object');

        self::assertEquals(new ObjectNormalizer(Wrapper::class), $propertyMetadata->normalizer);
        self::assertEquals(
            Type::generic(Type::object(Wrapper::class), Type::object(Email::class)),
            $propertyMetadata->type,
        );

        $propertyMetadata = $metadata->propertyForField('scalar');
        self::assertEquals(new ObjectNormalizer(Wrapper::class), $propertyMetadata->normalizer);

        self::assertEquals(
            Type::nullable(
                Type::generic(
                    Type::object(Wrapper::class),
                    Type::string(),
                ),
            ),
            $propertyMetadata->type,
        );
    }

    public function testExtends(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ParentDto::class);

        self::assertCount(2, $metadata->properties);

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName);
        self::assertSame('profileId', $emailPropertyMetadata->fieldName);
        self::assertInstanceOf(IdNormalizer::class, $emailPropertyMetadata->normalizer);

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName);
        self::assertSame('email', $emailPropertyMetadata->fieldName);
        self::assertInstanceOf(EmailNormalizer::class, $emailPropertyMetadata->normalizer);
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

        self::assertCount(1, $metadata->properties);

        $property = $metadata->propertyForField('recordedDate');

        self::assertSame('recordedDate', $property->propertyName);
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

        self::assertCount(2, $metadata->properties);

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName);
        self::assertSame('profileId', $emailPropertyMetadata->fieldName);
        self::assertInstanceOf(IdNormalizer::class, $emailPropertyMetadata->normalizer);

        $emailPropertyMetadata = $metadata->propertyForField('email');

        self::assertSame('email', $emailPropertyMetadata->propertyName);
        self::assertSame('email', $emailPropertyMetadata->fieldName);
        self::assertInstanceOf(EmailNormalizer::class, $emailPropertyMetadata->normalizer);
    }

    public function testIgnore(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(IgnoreDto::class);

        self::assertCount(1, $metadata->properties);

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName);
        self::assertSame('profileId', $emailPropertyMetadata->fieldName);
        self::assertInstanceOf(IdNormalizer::class, $emailPropertyMetadata->normalizer);
    }

    public function testIgnoreNotFoundProperty(): void
    {
        $this->expectException(PropertyMetadataNotFound::class);

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(IgnoreDto::class);

        $metadata->propertyForField('email');
    }

    public function testNoLazy(): void
    {
        $object = new class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertNull($metadata->lazy);
    }

    public function testLazy(): void
    {
        $object = new #[Lazy]
        class {
        };

        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata($object::class);

        self::assertTrue($metadata->lazy);
    }

    public function testClassMetadataWithNormalizer(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileId::class);

        self::assertInstanceOf(IdNormalizer::class, $metadata->normalizer);
    }
}
