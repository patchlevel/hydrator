<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class NormalizerInBaseClassDefinedDto
{
    /** @param array<string> $array */
    public function __construct(
        public StatusWithNormalizer $status,
        public ProfileCreatedWithNormalizer $profileCreated,
        /** @var StatusWithNormalizer[] */
        public array $defaultArray = [],
        /** @var list<StatusWithNormalizer> */
        public array $listArray = [],
        /** @var iterable<StatusWithNormalizer> */
        public array $iterableArray = [],
    ) {
    }
}
