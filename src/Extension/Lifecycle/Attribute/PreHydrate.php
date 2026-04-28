<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class PreHydrate
{
}
