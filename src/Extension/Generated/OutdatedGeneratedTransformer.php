<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Generated;

use Patchlevel\Hydrator\HydratorException;
use RuntimeException;

use function sprintf;

/**
 * The generated code does not match the metadata of the class. The {@see GeneratedTransformerFactory} catches it and
 * falls back to reflection, it only reaches the user if the generated code is used directly.
 */
final class OutdatedGeneratedTransformer extends RuntimeException implements HydratorException
{
    public function __construct(string $class)
    {
        parent::__construct(sprintf(
            'The generated transformer does not match the current definition of class "%s": its properties, their visibility, defaults or normalizers changed since the code was generated.',
            $class,
        ));
    }
}
