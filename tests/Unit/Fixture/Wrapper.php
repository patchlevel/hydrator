<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

/** @template T */
#[ObjectNormalizer]
final class Wrapper
{
    /**
     * @param T                    $value
     * @param Wrapper<Email>       $object
     * @param Wrapper<string>|null $scalar
     */
    public function __construct(
        public mixed $value,
        public Wrapper $object,
        public Wrapper|null $scalar = null,
    ) {
    }
}
