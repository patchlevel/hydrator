<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

abstract class ChildDto
{
    public function __construct(
        #[EmailNormalizer]
        private Email $email,
    ) {
    }
}
