<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator;

use Patchlevel\Hydrator\Guesser\BuiltInGuesser;
use Patchlevel\Hydrator\Middleware\TransformMiddleware;

/** @experimental */
final class CoreExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMiddleware(new TransformMiddleware(), -64);
        $builder->addGuesser(new BuiltInGuesser(), -64);
    }
}
