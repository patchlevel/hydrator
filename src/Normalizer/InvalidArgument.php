<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Normalizer;

use InvalidArgumentException;
use Patchlevel\Hydrator\HydratorException;
use Throwable;

use function get_debug_type;
use function sprintf;

final class InvalidArgument extends InvalidArgumentException implements HydratorException
{
    public static function withWrongType(string $expected, mixed $value): self
    {
        return new self(
            sprintf(
                'type "%s" was expected but "%s" was passed.',
                $expected,
                get_debug_type($value),
            ),
        );
    }

    public static function fromThrowable(Throwable $exception): self
    {
        return new self($exception->getMessage(), 0, $exception);
    }
}
