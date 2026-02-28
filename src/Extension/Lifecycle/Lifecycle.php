<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle;

/** @experimental */
final readonly class Lifecycle
{
    public function __construct(
        public string|null $preHydrate = null,
        public string|null $postHydrate = null,
        public string|null $preExtract = null,
        public string|null $postExtract = null,
    ) {
    }
}
