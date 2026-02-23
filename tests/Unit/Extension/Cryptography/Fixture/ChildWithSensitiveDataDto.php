<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Extension\Cryptography\Fixture;

use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;
use Patchlevel\Hydrator\Tests\Unit\Fixture\Email;
use Patchlevel\Hydrator\Tests\Unit\Fixture\EmailNormalizer;

abstract class ChildWithSensitiveDataDto
{
    public function __construct(
        #[EmailNormalizer]
        #[SensitiveData]
        public Email $email,
    ) {
    }
}
