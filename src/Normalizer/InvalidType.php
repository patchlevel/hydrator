<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use InvalidArgumentException;
use Patchlevel\Hydrator\HydratorException;
use Symfony\Component\TypeInfo\Type;

use function sprintf;

final class InvalidType extends InvalidArgumentException implements HydratorException
{
    public static function unsupportedType(Type $expectedType, Type $name): self
    {
        return new self(sprintf('Unsupported type "%s", expected "%s".', $name, $expectedType));
    }

    public static function missingType(): self
    {
        return new self('Missing type. Please specify the type explicitly.');
    }
}
