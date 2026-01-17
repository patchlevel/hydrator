<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface Extension
{
    public function configure(HydratorBuilder $builder): void;
}
