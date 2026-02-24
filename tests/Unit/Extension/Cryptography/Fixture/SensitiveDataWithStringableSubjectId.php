<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture;

use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Tests\Unit\Fixture\StringableSubjectId;

final class SensitiveDataWithStringableSubjectId
{
    public function __construct(
        #[DataSubjectId]
        public StringableSubjectId $subjectId,
        #[SensitiveData]
        public string $name,
    ) {
    }
}
