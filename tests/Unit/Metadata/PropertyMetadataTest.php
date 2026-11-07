<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;

use function serialize;
use function unserialize;

#[CoversClass(PropertyMetadata::class)]
final class PropertyMetadataTest extends TestCase
{
    public function testSerialize(): void
    {
        $propertyMetadata = new PropertyMetadata(
            new ReflectionProperty(ProfileCreated::class, 'email'),
            Type::object(Email::class),
            'email_field',
            new EmailNormalizer(),
            ['foo' => 'bar'],
        );

        $unserialized = unserialize(serialize($propertyMetadata));

        self::assertInstanceOf(PropertyMetadata::class, $unserialized);
        self::assertSame('email', $unserialized->propertyName);
        self::assertEquals($propertyMetadata, $unserialized);
    }
}
