<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;
use Patchlevel\Hydrator\Tests\Unit\Fixture\PersonalDataProfileCreatedFallbackCallback;
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
            new ReflectionProperty(PersonalDataProfileCreatedFallbackCallback::class, 'email'),
            'email_field',
            new EmailNormalizer(),
            true,
            null,
            [PersonalDataProfileCreatedFallbackCallback::class, 'emailFallback'],
            ['foo' => 'bar'],
            Type::object(Email::class),
        );

        $unserialized = unserialize(serialize($propertyMetadata));

        self::assertInstanceOf(PropertyMetadata::class, $unserialized);
        self::assertSame('email', $unserialized->propertyName());
        self::assertEquals($propertyMetadata, $unserialized);
        $callback = $unserialized->personalDataFallbackCallback();

        self::assertNotNull($callback);
        self::assertEquals(new Email('foo@example.com'), $callback('foo', null));
    }
}
