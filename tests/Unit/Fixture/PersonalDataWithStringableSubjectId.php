<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

class PersonalDataWithStringableSubjectId
{
    public function __construct(
        #[DataSubjectId]
        public StringableSubjectId $subjectId,
        #[PersonalData]
        public string $name,
    ) {
    }
}
