<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\SensitiveData;

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
