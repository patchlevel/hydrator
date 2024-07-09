<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class InferNormalizerBrokenDto
{
    public function __construct(
        public ProfileCreated $profileCreated,
    ) {
    }
}
