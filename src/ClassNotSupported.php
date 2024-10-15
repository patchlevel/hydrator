<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;
use Throwable;

use function sprintf;

final class ClassNotSupported extends RuntimeException implements HydratorException
{
    /** @param class-string $className */
    public function __construct(string $className, Throwable|null $previous = null)
    {
        parent::__construct(sprintf('Class %s not supported', $className), 0, $previous);
    }
}
