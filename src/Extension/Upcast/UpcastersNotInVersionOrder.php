<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Upcast;

use InvalidArgumentException;
use Patchlevel\Hydrator\HydratorException;

use function sprintf;

final class UpcastersNotInVersionOrder extends InvalidArgumentException implements HydratorException
{
    /** @param class-string $class */
    public function __construct(string $class, int $previous, int $from)
    {
        parent::__construct(sprintf(
            'The upcasters of %s have to be registered in the order of their versions, the upcaster from version %d comes after the one from version %d.',
            $class,
            $from,
            $previous,
        ));
    }
}
