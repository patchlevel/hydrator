<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\SensitiveData;

abstract class ChildWithSensitiveDataDto
{
    public function __construct(
        #[EmailNormalizer]
        #[SensitiveData]
        public Email $email,
    ) {
    }
}
