<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Extension\Lifecycle\Attribute;

use Attribute;

/** @experimental */
#[Attribute(Attribute::TARGET_METHOD)]
final class PostHydrate
{
}
