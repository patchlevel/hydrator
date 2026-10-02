<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture;

use Patchlevel\Hydrator\Attribute\Context;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Tests\Unit\Fixture\ContextAwareNormalizer;

final class SensitiveDataWithContextDto
{
    public function __construct(
        #[ContextAwareNormalizer]
        #[Context(['prefix' => 'id-'])]
        #[DataSubjectId]
        public string $id,
        #[ContextAwareNormalizer]
        #[Context(['prefix' => 'p-', 'suffix' => '-s'])]
        #[SensitiveData(fallback: 'fallback')]
        public string $email,
    ) {
    }
}
