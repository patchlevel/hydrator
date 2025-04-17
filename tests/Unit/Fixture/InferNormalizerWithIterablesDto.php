<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class InferNormalizerWithIterablesDto
{
    /**
     * @param Status[]                                             $defaultArray
     * @param list<Status>                                         $listArray
     * @param iterable<Status>                                     $iterableArray
     * @param array<string, Status>                                $hashMap
     * @param array<string, iterable<Status>>                      $nested
     * @param array{foo: string, bar: int, baz: list<string>}|null $jsonArray
     */
    public function __construct(
        public array $defaultArray = [],
        public array $listArray = [],
        public iterable $iterableArray = [],
        public array $hashMap = [],
        public iterable $nested = [],
        public array|null $jsonArray = null,
    ) {
    }
}
