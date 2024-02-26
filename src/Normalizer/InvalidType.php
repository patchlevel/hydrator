<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use InvalidArgumentException;
use Patchlevel\Hydrator\HydratorException;
use ReflectionType;

use function sprintf;

final class InvalidType extends InvalidArgumentException implements HydratorException
{
    /** @param class-string<ReflectionType> $expected */
    public static function unsupportedReflectionType(string $expected, ReflectionType $type): self
    {
        return new self(sprintf('Unsupported reflection type "%s", expected "%s".', $type::class, $expected));
    }

    public static function unsupportedType(string $expectedType, string $name): self
    {
        return new self(sprintf('Unsupported type "%s", expected "%s".', $name, $expectedType));
    }

    public static function missingType(): self
    {
        return new self('Missing type. Please specify the type explicitly.');
    }
}
