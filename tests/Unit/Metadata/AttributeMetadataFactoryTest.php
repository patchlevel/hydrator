<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Attribute\NormalizedName;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
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
        self::assertArrayHasKey('name', $properties);

        $propertyMetadata = $properties['name'];

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
        self::assertArrayHasKey('name', $properties);

        $propertyMetadata = $properties['name'];

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
        self::assertArrayHasKey('name', $properties);

        $propertyMetadata = $properties['name'];

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
        self::assertArrayHasKey('email', $properties);

        $propertyMetadata = $properties['email'];

        self::assertSame('email', $propertyMetadata->propertyName());
        self::assertSame('email', $propertyMetadata->fieldName());
        self::assertInstanceOf(EmailNormalizer::class, $propertyMetadata->normalizer());
    }
}
