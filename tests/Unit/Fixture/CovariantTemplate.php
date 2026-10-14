<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ArrayNormalizer;
use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

/** @template-covariant T of object */
final readonly class CovariantTemplate
{
    /** @param list<T> $objects */
    public function __construct(
        #[ArrayNormalizer(new ObjectNormalizer())]
        public array $objects,
    ) {
    }
}
