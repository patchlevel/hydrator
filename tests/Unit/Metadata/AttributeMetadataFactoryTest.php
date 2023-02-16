<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Metadata\DuplicatedFieldNameInMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\BrokenParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\DuplicateFieldNameDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ParentDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileIdNormalizer;
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
    }

    public function testWithProperties(): void
    {
        $object = new class {
            public ?string $name = null;
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

    public function testWithConstructorProperties(): void
    {
        $object = new class ('Foo') {
            public function __construct(
                public string $name
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
                public string $name
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
                public Email $email
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

    public function testExtends(): void
    {
        $metadataFactory = new AttributeMetadataFactory();
        $metadata = $metadataFactory->metadata(ParentDto::class);

        self::assertCount(2, $metadata->properties());

        $emailPropertyMetadata = $metadata->propertyForField('profileId');

        self::assertSame('profileId', $emailPropertyMetadata->propertyName());
        self::assertSame('profileId', $emailPropertyMetadata->fieldName());
        self::assertInstanceOf(ProfileIdNormalizer::class, $emailPropertyMetadata->normalizer());

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
}
