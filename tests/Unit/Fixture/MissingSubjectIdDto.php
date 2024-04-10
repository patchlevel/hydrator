<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\PersonalData;

final class MissingSubjectIdDto
{
    public function __construct(
        #[PersonalData(fallback: 'fallback')]
        public Email $email,
    ) {
    }
}
