<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Metadata;

use Patchlevel\Hydrator\Metadata\ClassMetadata;
use Patchlevel\Hydrator\Metadata\PropertyMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(ClassMetadata::class)]
final class ClassMetadataTest extends TestCase
{
    public function testPropertiesHashmap(): void
    {
        $object = new class {
            public string $foo;
            public string $bar;
        };

        $reflection = new ReflectionClass($object);
        $fooReflection = new ReflectionProperty($object, 'foo');
        $barReflection = new ReflectionProperty($object, 'bar');

        $fooMetadata = new PropertyMetadata($fooReflection, 'foo_field');
        $barMetadata = new PropertyMetadata($barReflection, 'bar_field');

        $classMetadata = new ClassMetadata(
            $reflection,
            null,
            [$fooMetadata, $barMetadata],
        );

        self::assertCount(2, $classMetadata->properties);
        self::assertArrayHasKey('foo', $classMetadata->properties);
        self::assertArrayHasKey('bar', $classMetadata->properties);
        self::assertSame($fooMetadata, $classMetadata->properties['foo']);
        self::assertSame($barMetadata, $classMetadata->properties['bar']);
    }
}
