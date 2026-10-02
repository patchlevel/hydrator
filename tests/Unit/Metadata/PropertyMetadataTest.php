<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use Patchlevel\Hydrator\Tests\Unit\Fixture\PropertyContextDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\TypeInfo\Type;

use function serialize;
use function unserialize;

#[CoversClass(PropertyMetadata::class)]
final class PropertyMetadataTest extends TestCase
{
    public function testSerializeContext(): void
    {
        $metadata = new PropertyMetadata(
            new ReflectionProperty(PropertyContextDto::class, 'value'),
            Type::string(),
            'value',
            context: ['prefix' => 'attr-'],
        );

        $result = unserialize(serialize($metadata));

        self::assertInstanceOf(PropertyMetadata::class, $result);
        self::assertSame(['prefix' => 'attr-'], $result->context);
    }

    public function testUnserializeWithoutContext(): void
    {
        $metadata = new PropertyMetadata(
            new ReflectionProperty(PropertyContextDto::class, 'value'),
            Type::string(),
            'value',
            context: ['prefix' => 'attr-'],
        );

        // metadata cached by an older version has no context
        $data = $metadata->__serialize();
        unset($data['context']);

        $result = (new ReflectionClass(PropertyMetadata::class))->newInstanceWithoutConstructor();
        $result->__unserialize($data);

        self::assertSame([], $result->context);
    }
}
