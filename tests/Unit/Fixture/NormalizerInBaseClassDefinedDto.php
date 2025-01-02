<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class NormalizerInBaseClassDefinedDto
{
    public function __construct(
        public StatusWithNormalizer $status,
        public ProfileCreatedWithNormalizer $profileCreated,
        /** @var StatusWithNormalizer[] */
        public array $defaultArray = [],
        /** @var list<StatusWithNormalizer> */
        public array $listArray = [],
        /** @var iterable<StatusWithNormalizer> */
        public iterable $iterableArray = [],
        /** @var array<string, Skill> */
        public array $skillsHashMap = [],
        /** @var array{foo: string, bar: int, baz: list<string>}|null */
        public array|null $jsonArray = null,
    ) {
    }
}
