<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use ReflectionNamedType;
use ReflectionType;

use function class_exists;
use function is_a;

final class ReflectionTypeUtil
{
    public static function type(ReflectionType $reflectionType): string
    {
        if (!$reflectionType instanceof ReflectionNamedType) {
            throw InvalidType::unsupportedReflectionType(ReflectionNamedType::class, $reflectionType);
        }

        return $reflectionType->getName();
    }

    /** @return class-string */
    public static function classString(ReflectionType $reflectionType): string
    {
        $type = self::type($reflectionType);

        if (!class_exists($type)) {
            throw InvalidType::unsupportedType('class-string', $type);
        }

        return $type;
    }

    /**
     * @param class-string<T> $expected
     *
     * @return class-string<T>
     *
     * @template T
     */
    public static function classStringInstanceOf(ReflectionType $reflectionType, string $expected): string
    {
        $type = self::type($reflectionType);

        if (!is_a($type, $expected, true)) {
            throw InvalidType::unsupportedType('class-string<' . $expected . '>', $type);
        }

        return $type;
    }
}
