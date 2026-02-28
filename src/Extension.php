<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

/** @experimental */
interface Extension
{
    public function configure(StackHydratorBuilder $builder): void;
}
