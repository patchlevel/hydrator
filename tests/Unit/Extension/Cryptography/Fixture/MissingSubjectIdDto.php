<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture;

use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;

final class MissingSubjectIdDto
{
    public function __construct(
        #[SensitiveData(fallback: 'fallback')]
        public Email $email,
    ) {
    }
}
