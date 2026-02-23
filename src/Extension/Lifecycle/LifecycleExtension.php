<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\HydratorBuilder;

final readonly class LifecycleExtension implements Extension
{
    public function configure(HydratorBuilder $builder): void
    {
        $builder->addMiddleware(new LifecycleMiddleware());
        $builder->addMetadataEnricher(new LifecycleMetadataEnricher());
    }
}
