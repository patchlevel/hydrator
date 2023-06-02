<?php

declare(strict_types=1);

namespace Patchlevel\Hydrator\Tests\Unit\Fixture;

final class WrongNormalizer
{
    public function __construct(
        #[EmailNormalizer]
        public bool $email,
    ) {
    }
}
