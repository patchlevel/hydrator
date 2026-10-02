<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\Context;

final class PropertyContextDto
{
    public function __construct(
        #[ContextAwareNormalizer]
        #[Context(['prefix' => 'attr-', 'suffix' => '-attr'])]
        public string $value,
        #[ContextAwareNormalizer]
        #[Context(['prefix' => 'first-'])]
        #[Context(['prefix' => 'second-', 'suffix' => '-second'])]
        public string $repeated,
        #[ContextAwareNormalizer]
        public string $plain,
    ) {
    }
}
