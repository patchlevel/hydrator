<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

use Patchlevel\Hydrator\Attribute\SensitiveData;

abstract class ChildWithSensitiveDataWithIdentifierDto
{
    public function __construct(
        #[EmailNormalizer]
        #[SensitiveData(subjectIdName: 'profile')]
        private Email $email,
    ) {
    }
}
