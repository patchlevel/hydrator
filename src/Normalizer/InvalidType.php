<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use InvalidArgumentException;
use Patchlevel\Hydrator\HydratorException;

use function sprintf;

final class InvalidType extends InvalidArgumentException implements HydratorException
{
    public static function unsupportedType(string $expectedType, string $name): self
    {
        return new self(sprintf('Unsupported type "%s", expected "%s".', $name, $expectedType));
    }

    public static function missingType(): self
    {
        return new self('Missing type. Please specify the type explicitly.');
    }
}
