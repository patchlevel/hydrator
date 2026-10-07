<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

/** Links to another object with a custom normalizer, which can build a circular reference. */
final class LinkedDto
{
    public function __construct(
        public string $name,
        #[LinkNormalizer]
        public LinkedDto|null $next = null,
    ) {
    }
}
