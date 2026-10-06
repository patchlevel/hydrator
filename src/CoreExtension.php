<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\BuiltInGuesser;

final class CoreExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addGuesser(new BuiltInGuesser(), -64);
    }
}
