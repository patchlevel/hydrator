<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use RuntimeException;

final class DecoratorsNotApplied extends RuntimeException implements HydratorException
{
    public function __construct()
    {
        parent::__construct('Decorators are registered but build() can not apply them, use buildHydrator() instead.');
    }
}
