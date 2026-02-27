<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class ContextAwareDto
{
    public function __construct(
        #[ContextAwareNormalizer]
        public string $value,
    ) {
    }
}
