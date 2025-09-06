<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\SensitiveData;

final class MissingSubjectIdDto
{
    public function __construct(
        #[SensitiveData(fallback: 'fallback')]
        public Email $email,
    ) {
    }
}
