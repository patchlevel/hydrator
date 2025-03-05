<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class NormalizerInBaseClassDefinedDto
{
    /**
     * @param StatusWithNormalizer[]                               $defaultArray
     * @param list<StatusWithNormalizer>                           $listArray
     * @param iterable<StatusWithNormalizer>                       $iterableArray
     * @param array<string, Skill>                                 $skillsHashMap
     * @param array{foo: string, bar: int, baz: list<string>}|null $jsonArray
     */
    public function __construct(
        public StatusWithNormalizer $status,
        public ProfileCreatedWithNormalizer $profileCreated,
        public array $defaultArray = [],
        public array $listArray = [],
        public iterable $iterableArray = [],
        public array $skillsHashMap = [],
        public array|null $jsonArray = null,
    ) {
    }
}
