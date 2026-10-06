<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Tracing;

enum Operation: string
{
    case Hydrate = 'hydrate';
    case Extract = 'extract';
}
