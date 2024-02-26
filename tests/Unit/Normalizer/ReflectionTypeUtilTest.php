<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Normalizer;

use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeUtil;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ChildDto;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionType;
use RuntimeException;
use Serializable;

final class ReflectionTypeUtilTest extends TestCase
{
    public function testType(): void
    {
        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public string $string;
            public string|null $nullableString = null;

            public string|null $unionNullableString = null;
        };

        self::assertEquals(
            'string',
            ReflectionTypeUtil::type($this->reflectionType($object, 'string')),
        );

        self::assertEquals(
            'string',
            ReflectionTypeUtil::type($this->reflectionType($object, 'nullableString')),
        );

        self::assertEquals(
            'string',
            ReflectionTypeUtil::type($this->reflectionType($object, 'unionNullableString')),
        );
    }

    public function testUnionNotSupported(): void
    {
        $this->expectException(InvalidType::class);

        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public string|int $union;
        };

        ReflectionTypeUtil::type($this->reflectionType($object, 'union'));
    }

    public function testIntersectionNotSupported(): void
    {
        $this->expectException(InvalidType::class);

        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public ChildDto&Serializable $intersection;
        };

        ReflectionTypeUtil::type($this->reflectionType($object, 'intersection'));
    }

    public function testClassString(): void
    {
        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public ProfileCreated $object;

            public ProfileCreated|null $objectNullable = null;

            public ProfileCreated|null $objectUnionNullable = null;
        };

        self::assertEquals(
            ProfileCreated::class,
            ReflectionTypeUtil::classString($this->reflectionType($object, 'object')),
        );

        self::assertEquals(
            ProfileCreated::class,
            ReflectionTypeUtil::classString($this->reflectionType($object, 'objectNullable')),
        );

        self::assertEquals(
            ProfileCreated::class,
            ReflectionTypeUtil::classString($this->reflectionType($object, 'objectUnionNullable')),
        );
    }

    public function testNotAClassString(): void
    {
        $this->expectException(InvalidType::class);

        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public string $notAObject;
        };

        ReflectionTypeUtil::classString($this->reflectionType($object, 'notAObject'));
    }

    public function testClassStringInstanceOf(): void
    {
        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public ProfileCreated $object;

            public ProfileCreated|null $objectNullable = null;

            public ProfileCreated|null $objectUnionNullable = null;
        };

        self::assertEquals(
            ProfileCreated::class,
            ReflectionTypeUtil::classStringInstanceOf(
                $this->reflectionType($object, 'object'),
                ProfileCreated::class,
            ),
        );

        self::assertEquals(
            ProfileCreated::class,
            ReflectionTypeUtil::classStringInstanceOf(
                $this->reflectionType($object, 'objectNullable'),
                ProfileCreated::class,
            ),
        );

        self::assertEquals(
            ProfileCreated::class,
            ReflectionTypeUtil::classStringInstanceOf(
                $this->reflectionType($object, 'objectUnionNullable'),
                ProfileCreated::class,
            ),
        );
    }

    public function testNotAClassStringInstanceOf(): void
    {
        $this->expectException(InvalidType::class);

        $object = new class {
            /** @psalm-suppress PropertyNotSetInConstructor */
            public ProfileCreated $object;
        };

        ReflectionTypeUtil::classStringInstanceOf(
            $this->reflectionType($object, 'object'),
            ChildDto::class,
        );
    }

    private function reflectionType(object $object, string $property): ReflectionType
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($property);

        $type = $property->getType();

        if (!$type instanceof ReflectionType) {
            throw new RuntimeException('no type');
        }

        return $type;
    }
}
