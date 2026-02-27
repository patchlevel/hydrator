<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

interface Extension
{
    public function configure(StackHydratorBuilder $builder): void;
}
