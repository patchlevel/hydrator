<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

final readonly class LifecycleExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMiddleware(new LifecycleMiddleware());
        $builder->addMetadataEnricher(new LifecycleMetadataEnricher());
    }
}
