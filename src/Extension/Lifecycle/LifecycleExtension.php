<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle;

use Patchlevel\Hydrator\Extension;
use Patchlevel\Hydrator\StackHydratorBuilder;

/** @experimental */
final readonly class LifecycleExtension implements Extension
{
    public function configure(StackHydratorBuilder $builder): void
    {
        $builder->addMiddleware(new LifecycleMiddleware(), Extension::PRIORITY_BEFORE_TRANSFORM);
        $builder->addMetadataEnricher(new LifecycleMetadataEnricher());
    }
}
